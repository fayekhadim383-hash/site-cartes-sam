<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier l'authentification admin
checkAdminAuth();

$db = Database::getInstance();

// Récupérer les statistiques générales
$stats = [];

// Nombre total de clients
$stmt = $db->query("SELECT COUNT(*) FROM clients WHERE is_active = 1");
$stats['total_clients'] = $stmt->fetchColumn();

// Nombre total de commandes
$stmt = $db->query("SELECT COUNT(*) FROM client_cards WHERE status NOT IN ('draft', 'cancelled')");
$stats['total_orders'] = $stmt->fetchColumn();

// Revenu total
$stmt = $db->query("SELECT SUM(total_price) FROM client_cards WHERE status IN ('active', 'delivered', 'shipped')");
$stats['total_revenue'] = $stmt->fetchColumn() ?: 0;

// Commandes en attente
$stmt = $db->query("SELECT COUNT(*) FROM client_cards WHERE status = 'pending'");
$stats['pending_orders'] = $stmt->fetchColumn();

// Démos à venir
$stmt = $db->query("SELECT COUNT(*) FROM demos WHERE status = 'scheduled' AND demo_date >= CURDATE()");
$stats['upcoming_demos'] = $stmt->fetchColumn();

// Demandes de mise à niveau en attente
$stmt = $db->query("SELECT COUNT(*) FROM upgrade_requests WHERE status = 'pending'");
$stats['pending_upgrades'] = $stmt->fetchColumn();

// Nouveaux clients ce mois-ci
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM clients 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$stats['new_clients_month'] = $stmt->fetchColumn();

// Commandes ce mois-ci
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM client_cards 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
    AND status NOT IN ('draft', 'cancelled')
");
$stmt->execute();
$stats['orders_month'] = $stmt->fetchColumn();

// Revenu ce mois-ci
$stmt = $db->prepare("
    SELECT SUM(total_price) 
    FROM client_cards 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
    AND status IN ('active', 'delivered', 'shipped')
");
$stmt->execute();
$stats['revenue_month'] = $stmt->fetchColumn() ?: 0;

// Récupérer les activités récentes
$stmt = $db->query("
    SELECT a.*, 
           CASE 
               WHEN a.user_type = 'client' THEN c.company_name
               WHEN a.user_type = 'admin' THEN ad.username
           END as user_name
    FROM activities a
    LEFT JOIN clients c ON a.user_type = 'client' AND a.user_id = c.id
    LEFT JOIN admins ad ON a.user_type = 'admin' AND a.user_id = ad.id
    ORDER BY a.created_at DESC 
    LIMIT 10
");
$recent_activities = $stmt->fetchAll();

// Récupérer les commandes récentes
$stmt = $db->query("
    SELECT cc.*, c.company_name, p.name as product_name
    FROM client_cards cc
    JOIN clients c ON cc.client_id = c.id
    JOIN products p ON cc.product_id = p.id
    WHERE cc.status NOT IN ('draft')
    ORDER BY cc.created_at DESC 
    LIMIT 10
");
$recent_orders = $stmt->fetchAll();

// Récupérer les notifications non lues
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE admin_id IS NULL AND is_read = 0");
$stmt->execute();
$unread_notifications = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin - CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="admin-dashboard">
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="admin-sidebar-header">
            <a href="dashboard.php" class="logo">
                <img src="../assets/images/logo.png" alt="CartesVisitePro">
                <span>Admin Panel</span>
            </a>
        </div>
        
        <div class="admin-info">
            <div class="admin-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <h3><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h3>
            <p><?php echo htmlspecialchars($_SESSION['admin_email']); ?></p>
            <p class="admin-role">Administrateur</p>
        </div>
        
        <nav class="admin-sidebar-nav">
            <ul>
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
                <li><a href="products.php"><i class="fas fa-box"></i> Produits</a></li>
                <li><a href="demos.php"><i class="fas fa-play-circle"></i> Démonstrations</a></li>
                <li><a href="upgrades.php"><i class="fas fa-level-up-alt"></i> Mises à niveau</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Rapports</a></li>
                <li><a href="settings.php"><i class="fas fa-cogs"></i> Paramètres</a></li>
            </ul>
        </nav>
        
        <div class="admin-sidebar-footer">
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="admin-main-content">
        <!-- Header -->
        <header class="admin-header">
            <div class="admin-header-left">
                <h1>Tableau de bord</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</p>
            </div>
            <div class="admin-header-right">
                <div class="admin-notifications" id="notificationsBell">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="admin-notification-count"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </div>
                <div class="admin-user-dropdown" id="userDropdown">
                    <img src="../assets/images/default-avatar.png" alt="Admin">
                    <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </header>

        <!-- Stats -->
        <div class="admin-stats">
            <div class="admin-stat-card">
                <div class="admin-stat-icon clients">
                    <i class="fas fa-users"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo $stats['total_clients']; ?></h3>
                    <p>Clients actifs</p>
                </div>
                <div class="admin-stat-change positive">
                    <i class="fas fa-arrow-up"></i> +<?php echo $stats['new_clients_month']; ?> ce mois
                </div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon orders">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo $stats['total_orders']; ?></h3>
                    <p>Commandes totales</p>
                </div>
                <div class="admin-stat-change positive">
                    <i class="fas fa-arrow-up"></i> +<?php echo $stats['orders_month']; ?> ce mois
                </div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon revenue">
                    <i class="fas fa-euro-sign"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo number_format($stats['total_revenue'], 2, ',', ' '); ?>FCFA</h3>
                    <p>Revenu total</p>
                </div>
                <div class="admin-stat-change positive">
                    <i class="fas fa-arrow-up"></i> +<?php echo number_format($stats['revenue_month'], 2, ',', ' '); ?>€ ce mois
                </div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo $stats['pending_orders']; ?></h3>
                    <p>Commandes en attente</p>
                </div>
                <div class="admin-stat-change <?php echo $stats['pending_orders'] > 10 ? 'negative' : 'positive'; ?>">
                    <?php if ($stats['pending_orders'] > 10): ?>
                        <i class="fas fa-arrow-up"></i> À traiter
                    <?php else: ?>
                        <i class="fas fa-check"></i> Sous contrôle
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Charts & Recent Orders -->
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Commandes récentes</h3>
                    <div class="chart-actions">
                        <button class="chart-action-btn active" onclick="filterOrders('all')">Toutes</button>
                        <button class="chart-action-btn" onclick="filterOrders('pending')">En attente</button>
                        <button class="chart-action-btn" onclick="filterOrders('processing')">En cours</button>
                    </div>
                </div>
                <div class="chart-body">
                    <div class="table-container">
                        <table id="recentOrdersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Produit</th>
                                    <th>Type</th>
                                    <th>Statut</th>
                                    <th>Montant</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($order['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['card_type']; ?>">
                                                <?php echo strtoupper($order['card_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $order['status']; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'draft' => 'Brouillon',
                                                    'pending' => 'En attente',
                                                    'processing' => 'En cours',
                                                    'shipped' => 'Expédié',
                                                    'delivered' => 'Livré',
                                                    'active' => 'Actif',
                                                    'expired' => 'Expiré',
                                                    'upgrading' => 'Mise à niveau',
                                                    'cancelled' => 'Annulé'
                                                ];
                                                echo $status_labels[$order['status']] ?? $order['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($order['total_price'], 2, ',', ' '); ?>€</td>
                                        <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <div class="action-btn-group">
                                                <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" class="action-btn view" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="orders.php?action=edit&id=<?php echo $order['id']; ?>" class="action-btn edit" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Statistiques rapides</h3>
                </div>
                <div class="chart-body">
                    <div class="quick-stats">
                        <div class="quick-stat-item">
                            <div class="quick-stat-icon" style="background-color: #9b59b6;">
                                <i class="fas fa-wifi"></i>
                            </div>
                            <div class="quick-stat-info">
                                <h4>Cartes NFC</h4>
                                <p><?php 
                                    $stmt = $db->query("SELECT COUNT(*) FROM client_cards WHERE card_type = 'nfc'");
                                    echo $stmt->fetchColumn();
                                ?> actives</p>
                            </div>
                        </div>
                        
                        <div class="quick-stat-item">
                            <div class="quick-stat-icon" style="background-color: #f39c12;">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="quick-stat-info">
                                <h4>Démos à venir</h4>
                                <p><?php echo $stats['upcoming_demos']; ?> cette semaine</p>
                            </div>
                        </div>
                        
                        <div class="quick-stat-item">
                            <div class="quick-stat-icon" style="background-color: #3498db;">
                                <i class="fas fa-level-up-alt"></i>
                            </div>
                            <div class="quick-stat-info">
                                <h4>Mises à niveau</h4>
                                <p><?php echo $stats['pending_upgrades']; ?> en attente</p>
                            </div>
                        </div>
                        
                        <div class="quick-stat-item">
                            <div class="quick-stat-icon" style="background-color: #2ecc71;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="quick-stat-info">
                                <h4>Taux de conversion</h4>
                                <p>
                                    <?php 
                                    $demo_to_order = $db->query("
                                        SELECT COUNT(DISTINCT d.client_id) as demo_clients,
                                               COUNT(DISTINCT cc.client_id) as order_clients
                                        FROM demos d
                                        LEFT JOIN client_cards cc ON d.client_id = cc.client_id
                                        WHERE d.status = 'completed'
                                    ")->fetch();
                                    
                                    if ($demo_to_order['demo_clients'] > 0) {
                                        $conversion_rate = ($demo_to_order['order_clients'] / $demo_to_order['demo_clients']) * 100;
                                        echo number_format($conversion_rate, 1) . '%';
                                    } else {
                                        echo '0%';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity & To Do -->
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Activité récente</h3>
                    <button class="chart-action-btn" onclick="refreshActivities()">
                        <i class="fas fa-sync-alt"></i> Actualiser
                    </button>
                </div>
                <div class="chart-body">
                    <div class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php 
                                    $icons = [
                                        'login' => 'sign-in-alt',
                                        'order' => 'shopping-cart',
                                        'update' => 'sync-alt',
                                        'create' => 'plus-circle',
                                        'delete' => 'trash',
                                        'upgrade' => 'level-up-alt',
                                        'demo' => 'play-circle',
                                        'settings' => 'cogs'
                                    ];
                                    $icon = $icons[$activity['type']] ?? 'circle';
                                    ?>
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <p>
                                        <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </p>
                                    <span class="activity-time">
                                        <?php echo date('H:i', strtotime($activity['created_at'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($activity['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recent_activities)): ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <p>Aucune activité récente</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Tâches à faire</h3>
                    <button class="chart-action-btn" onclick="showAddTaskModal()">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
                <div class="chart-body">
                    <div class="todo-list" id="todoList">
                        <!-- Les tâches seront chargées via JavaScript -->
                        <div class="loading">
                            <i class="fas fa-spinner fa-spin"></i> Chargement des tâches...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Panel -->
    <div class="modal" id="notificationsModal">
        <div class="modal-overlay" onclick="closeNotifications()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Notifications</h3>
                <button class="modal-close" onclick="closeNotifications()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="notifications-list" id="notificationsList">
                    <!-- Notifications chargées via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="markAllNotificationsAsRead()">
                    <i class="fas fa-check-double"></i> Tout marquer comme lu
                </button>
                <a href="notifications.php" class="btn btn-primary">Voir toutes</a>
            </div>
        </div>
    </div>

    <!-- User Dropdown Menu -->
    <div class="dropdown-menu" id="userDropdownMenu">
        <div class="dropdown-header">
            <img src="../assets/images/default-avatar.png" alt="Admin">
            <div>
                <h4><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h4>
                <p><?php echo htmlspecialchars($_SESSION['admin_email']); ?></p>
            </div>
        </div>
        <div class="dropdown-body">
            <a href="profile.php"><i class="fas fa-user"></i> Mon profil</a>
            <a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a>
            <a href="activity.php"><i class="fas fa-history"></i> Mon activité</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div class="modal" id="addTaskModal">
        <div class="modal-overlay" onclick="closeAddTaskModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nouvelle tâche</h3>
                <button class="modal-close" onclick="closeAddTaskModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addTaskForm">
                    <div class="form-group">
                        <label for="taskTitle">Titre *</label>
                        <input type="text" id="taskTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="taskDescription">Description</label>
                        <textarea id="taskDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="taskPriority">Priorité</label>
                        <select id="taskPriority" name="priority">
                            <option value="low">Basse</option>
                            <option value="medium" selected>Moyenne</option>
                            <option value="high">Haute</option>
                            <option value="critical">Critique</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="taskDueDate">Date d'échéance</label>
                        <input type="date" id="taskDueDate" name="due_date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAddTaskModal()">Annuler</button>
                <button class="btn btn-primary" onclick="saveTask()">Enregistrer</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Initialiser DataTable
            $('#recentOrdersTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
                },
                "pageLength": 5,
                "order": [[6, 'desc']]
            });
            
            // Charger les tâches
            loadTasks();
            
            // Charger les notifications
            loadNotifications();
            
            // Initialiser Flatpickr pour les dates
            flatpickr("#taskDueDate", {
                locale: "fr",
                minDate: "today",
                dateFormat: "Y-m-d",
            });
            
            // Gestion des événements
            $('#notificationsBell').click(openNotifications);
            $('#userDropdown').click(toggleUserDropdown);
        });
        
        function filterOrders(filter) {
            const table = $('#recentOrdersTable').DataTable();
            if (filter === 'all') {
                table.search('').draw();
            } else {
                table.search(filter).draw();
            }
            
            // Mettre à jour les boutons actifs
            $('.chart-action-btn').removeClass('active');
            event.target.classList.add('active');
        }
        
        function refreshActivities() {
            // Implémenter le rafraîchissement des activités
            location.reload();
        }
        
        function loadTasks() {
            $.ajax({
                url: '../api/admin/get-tasks.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        displayTasks(response.tasks);
                    } else {
                        $('#todoList').html('<div class="empty-state">Erreur de chargement</div>');
                    }
                },
                error: function() {
                    $('#todoList').html('<div class="empty-state">Erreur de connexion</div>');
                }
            });
        }
        
        function displayTasks(tasks) {
            const container = $('#todoList');
            
            if (tasks.length === 0) {
                container.html('<div class="empty-state"><p>Aucune tâche en cours</p></div>');
                return;
            }
            
            let html = '';
            tasks.forEach(task => {
                const priorityClass = `priority-${task.priority}`;
                const checked = task.completed ? 'checked' : '';
                
                html += `
                    <div class="todo-item ${priorityClass}">
                        <label class="todo-checkbox">
                            <input type="checkbox" ${checked} onchange="toggleTask(${task.id}, this.checked)">
                            <span class="checkmark"></span>
                        </label>
                        <div class="todo-content">
                            <h4 class="todo-title ${task.completed ? 'completed' : ''}">${task.title}</h4>
                            ${task.description ? `<p class="todo-desc">${task.description}</p>` : ''}
                            <div class="todo-meta">
                                <span class="todo-priority">${getPriorityLabel(task.priority)}</span>
                                ${task.due_date ? `<span class="todo-due">${formatDate(task.due_date)}</span>` : ''}
                            </div>
                        </div>
                        <div class="todo-actions">
                            <button class="btn-action" onclick="editTask(${task.id})" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-action" onclick="deleteTask(${task.id})" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.html(html);
        }
        
        function getPriorityLabel(priority) {
            const labels = {
                'low': 'Basse',
                'medium': 'Moyenne',
                'high': 'Haute',
                'critical': 'Critique'
            };
            return labels[priority] || priority;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }
        
        function showAddTaskModal() {
            $('#addTaskModal').addClass('active');
        }
        
        function closeAddTaskModal() {
            $('#addTaskModal').removeClass('active');
            $('#addTaskForm')[0].reset();
        }
        
        function saveTask() {
            const formData = {
                title: $('#taskTitle').val(),
                description: $('#taskDescription').val(),
                priority: $('#taskPriority').val(),
                due_date: $('#taskDueDate').val()
            };
            
            if (!formData.title) {
                alert('Le titre est obligatoire');
                return;
            }
            
            $.ajax({
                url: '../api/admin/save-task.php',
                method: 'POST',
                data: JSON.stringify(formData),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        closeAddTaskModal();
                        loadTasks();
                        showNotification('Tâche ajoutée avec succès', 'success');
                    } else {
                        alert('Erreur: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erreur de connexion');
                }
            });
        }
        
        function toggleTask(taskId, completed) {
            $.ajax({
                url: '../api/admin/toggle-task.php',
                method: 'POST',
                data: JSON.stringify({ id: taskId, completed: completed }),
                contentType: 'application/json',
                success: function(response) {
                    if (!response.success) {
                        alert('Erreur: ' + response.message);
                    }
                }
            });
        }
        
        function editTask(taskId) {
            // Implémenter l'édition de tâche
            alert('Édition de la tâche #' + taskId);
        }
        
        function deleteTask(taskId) {
            if (confirm('Supprimer cette tâche ?')) {
                $.ajax({
                    url: '../api/admin/delete-task.php',
                    method: 'DELETE',
                    data: JSON.stringify({ id: taskId }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success) {
                            loadTasks();
                            showNotification('Tâche supprimée', 'success');
                        } else {
                            alert('Erreur: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Erreur de connexion');
                    }
                });
            }
        }
        
        function openNotifications() {
            $('#notificationsModal').addClass('active');
        }
        
        function closeNotifications() {
            $('#notificationsModal').removeClass('active');
        }
        
        function loadNotifications() {
            $.ajax({
                url: '../api/admin/get-notifications.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        displayNotifications(response.notifications);
                        updateNotificationCount(response.unread_count);
                    }
                }
            });
        }
        
        function displayNotifications(notifications) {
            const container = $('#notificationsList');
            
            if (notifications.length === 0) {
                container.html('<div class="empty-state"><p>Aucune notification</p></div>');
                return;
            }
            
            let html = '';
            notifications.forEach(notif => {
                const readClass = notif.is_read ? 'read' : 'unread';
                const icon = getNotificationIcon(notif.type);
                
                html += `
                    <div class="notification-item ${readClass}">
                        <div class="notification-icon">
                            <i class="fas fa-${icon}"></i>
                        </div>
                        <div class="notification-content">
                            <h4>${notif.title}</h4>
                            <p>${notif.message}</p>
                            <span class="notification-time">${formatTimeAgo(notif.created_at)}</span>
                        </div>
                        ${!notif.is_read ? '<div class="notification-dot"></div>' : ''}
                    </div>
                `;
            });
            
            container.html(html);
        }
        
        function getNotificationIcon(type) {
            const icons = {
                'order': 'shopping-cart',
                'update': 'sync-alt',
                'support': 'headset',
                'system': 'cog',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            return icons[type] || 'bell';
        }
        
        function formatTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'À l\'instant';
            if (diffMins < 60) return `Il y a ${diffMins} min`;
            if (diffHours < 24) return `Il y a ${diffHours} h`;
            if (diffDays < 7) return `Il y a ${diffDays} j`;
            
            return date.toLocaleDateString('fr-FR');
        }
        
        function updateNotificationCount(count) {
            const counter = $('.admin-notification-count');
            if (count > 0) {
                counter.text(count).show();
            } else {
                counter.hide();
            }
        }
        
        function markAllNotificationsAsRead() {
            $.ajax({
                url: '../api/admin/mark-notifications-read.php',
                method: 'POST',
                success: function(response) {
                    if (response.success) {
                        loadNotifications();
                        closeNotifications();
                        showNotification('Toutes les notifications marquées comme lues', 'success');
                    }
                }
            });
        }
        
        function toggleUserDropdown() {
            const menu = $('#userDropdownMenu');
            menu.toggleClass('show');
        }
        
        // Fermer les menus en cliquant à l'extérieur
        $(document).click(function(e) {
            if (!$(e.target).closest('#userDropdown').length && !$(e.target).closest('#userDropdownMenu').length) {
                $('#userDropdownMenu').removeClass('show');
            }
        });
        
        function showNotification(message, type = 'info') {
            // Implémenter un système de notification
            alert(message);
        }
    </script>
</body>
</html>