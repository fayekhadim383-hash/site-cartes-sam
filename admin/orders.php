<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier l'authentification admin
checkAdminAuth();

$db = Database::getInstance();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtres
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Construire la requête avec filtres
$whereClauses = ["cc.status != 'draft'"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(cc.order_number LIKE ? OR c.company_name LIKE ? OR c.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status)) {
    $whereClauses[] = "cc.status = ?";
    $params[] = $status;
}

if (!empty($type)) {
    $whereClauses[] = "cc.card_type = ?";
    $params[] = $type;
}

if (!empty($date_from)) {
    $whereClauses[] = "DATE(cc.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereClauses[] = "DATE(cc.created_at) <= ?";
    $params[] = $date_to;
}

$whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);

// Compter le total des commandes
$countStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM client_cards cc
    JOIN clients c ON cc.client_id = c.id
    $whereSQL
");
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Récupérer les commandes
$query = "
    SELECT cc.*, 
           c.company_name, 
           c.email as client_email,
           c.phone as client_phone,
           p.name as product_name,
           p.category as product_category
    FROM client_cards cc
    JOIN clients c ON cc.client_id = c.id
    JOIN products p ON cc.product_id = p.id
    $whereSQL
    ORDER BY cc.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Récupérer les statistiques de statut
$statusStats = $db->query("
    SELECT status, COUNT(*) as count 
    FROM client_cards 
    WHERE status != 'draft'
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Récupérer les statistiques de type
$typeStats = $db->query("
    SELECT card_type, COUNT(*) as count 
    FROM client_cards 
    WHERE status != 'draft'
    GROUP BY card_type
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Messages flash
$message = $_SESSION['flash_message'] ?? '';
$message_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Traitement des actions rapides
if (isset($_POST['quick_action'])) {
    $action = $_POST['quick_action'];
    $order_id = $_POST['order_id'] ?? 0;
    
    try {
        switch ($action) {
            case 'mark_processing':
                $stmt = $db->prepare("UPDATE client_cards SET status = 'processing' WHERE id = ?");
                $stmt->execute([$order_id]);
                $message = 'Commande marquée comme en cours de traitement';
                break;
                
            case 'mark_shipped':
                $tracking = $_POST['tracking_number'] ?? '';
                $stmt = $db->prepare("UPDATE client_cards SET status = 'shipped', tracking_number = ? WHERE id = ?");
                $stmt->execute([$tracking, $order_id]);
                $message = 'Commande marquée comme expédiée';
                break;
                
            case 'mark_delivered':
                $stmt = $db->prepare("UPDATE client_cards SET status = 'delivered', actual_delivery = CURDATE() WHERE id = ?");
                $stmt->execute([$order_id]);
                $message = 'Commande marquée comme livrée';
                break;
                
            case 'cancel':
                $stmt = $db->prepare("UPDATE client_cards SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$order_id]);
                $message = 'Commande annulée';
                break;
        }
        
        $message_type = 'success';
        
        // Ajouter une activité
        $activity_desc = "Commande #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " - " . 
                        ["mark_processing" => "Marquée comme en traitement",
                         "mark_shipped" => "Marquée comme expédiée",
                         "mark_delivered" => "Marquée comme livrée",
                         "cancel" => "Annulée"][$action];
        
        $stmt = $db->prepare("
            INSERT INTO activities (user_type, user_id, type, description, ip_address, user_agent)
            VALUES ('admin', ?, 'order', ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $activity_desc,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
    } catch (Exception $e) {
        $message = 'Erreur: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - Admin CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .status-filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .status-filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 2px solid #ddd;
            background: white;
            color: #666;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-filter-btn:hover,
        .status-filter-btn.active {
            border-color: #3498db;
            color: #3498db;
        }
        
        .status-filter-btn .count {
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        .quick-actions-dropdown {
            position: relative;
        }
        
        .quick-actions-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 100;
            min-width: 200px;
        }
        
        .quick-actions-menu.show {
            display: block;
        }
        
        .quick-action-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .quick-action-item:hover {
            background: #f8f9fa;
        }
        
        .quick-action-item i {
            margin-right: 10px;
            width: 20px;
        }
        
        .order-timeline {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
        }
        
        .timeline-step {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ddd;
            position: relative;
        }
        
        .timeline-step.completed {
            background: #2ecc71;
        }
        
        .timeline-step.active {
            background: #3498db;
            animation: pulse 2s infinite;
        }
        
        .timeline-step::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 20px;
            height: 2px;
            background: #ddd;
            transform: translateY(-50%);
        }
        
        .timeline-step:last-child::after {
            display: none;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
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
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                <li class="active"><a href="orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
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
                <h1>Gestion des Commandes</h1>
                <p>Total: <?php echo $totalOrders; ?> commandes</p>
            </div>
            <div class="admin-header-right">
                <a href="orders.php?action=export" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Exporter
                </a>
                <a href="order-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouvelle commande
                </a>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filtres rapides par statut -->
        <div class="status-filter-buttons">
            <button class="status-filter-btn <?php echo empty($status) ? 'active' : ''; ?>" 
                    onclick="window.location.href='orders.php'">
                Toutes <span class="count"><?php echo array_sum($statusStats); ?></span>
            </button>
            
            <?php 
            $statusLabels = [
                'pending' => ['icon' => 'clock', 'label' => 'En attente', 'color' => '#f39c12'],
                'processing' => ['icon' => 'sync-alt', 'label' => 'En traitement', 'color' => '#3498db'],
                'shipped' => ['icon' => 'truck', 'label' => 'Expédiées', 'color' => '#9b59b6'],
                'delivered' => ['icon' => 'check-circle', 'label' => 'Livrées', 'color' => '#2ecc71'],
                'active' => ['icon' => 'check', 'label' => 'Actives', 'color' => '#27ae60'],
                'cancelled' => ['icon' => 'times', 'label' => 'Annulées', 'color' => '#e74c3c']
            ];
            
            foreach ($statusLabels as $key => $info):
                if (isset($statusStats[$key])): ?>
                    <button class="status-filter-btn <?php echo $status === $key ? 'active' : ''; ?>" 
                            onclick="window.location.href='orders.php?status=<?php echo $key; ?>'"
                            style="border-color: <?php echo $info['color']; ?>; color: <?php echo $status === $key ? $info['color'] : '#666'; ?>">
                        <i class="fas fa-<?php echo $info['icon']; ?>"></i>
                        <?php echo $info['label']; ?> 
                        <span class="count"><?php echo $statusStats[$key]; ?></span>
                    </button>
                <?php endif;
            endforeach; ?>
        </div>

        <!-- Filtres avancés -->
        <div class="search-filter">
            <form method="GET" action="">
                <div class="form-group">
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" 
                           placeholder="N° commande, client, email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="type">Type de carte</label>
                    <select id="type" name="type">
                        <option value="">Tous les types</option>
                        <option value="standard" <?php echo $type === 'standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="nfc" <?php echo $type === 'nfc' ? 'selected' : ''; ?>>NFC</option>
                        <option value="premium" <?php echo $type === 'premium' ? 'selected' : ''; ?>>Premium</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">Date de début</label>
                    <input type="date" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">Date de fin</label>
                    <input type="date" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="orders.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Tableau des commandes -->
        <div class="recent-table">
            <div class="table-header">
                <h3>Liste des commandes</h3>
                <div class="table-actions">
                    <span class="table-info">
                        Affichage <?php echo min(($page-1)*$limit+1, $totalOrders); ?>-<?php echo min($page*$limit, $totalOrders); ?> sur <?php echo $totalOrders; ?>
                    </span>
                </div>
            </div>
            
            <div class="table-container">
                <table id="ordersTable" class="display">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
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
                        <?php foreach ($orders as $order): ?>
                            <?php 
                            // Déterminer les étapes de la timeline
                            $timeline = [];
                            $currentStep = '';
                            
                            switch ($order['status']) {
                                case 'pending':
                                    $currentStep = 'pending';
                                    $timeline = ['pending' => 'active'];
                                    break;
                                case 'processing':
                                    $currentStep = 'processing';
                                    $timeline = ['pending' => 'completed', 'processing' => 'active'];
                                    break;
                                case 'shipped':
                                    $currentStep = 'shipped';
                                    $timeline = ['pending' => 'completed', 'processing' => 'completed', 'shipped' => 'active'];
                                    break;
                                case 'delivered':
                                case 'active':
                                    $currentStep = 'delivered';
                                    $timeline = ['pending' => 'completed', 'processing' => 'completed', 'shipped' => 'completed', 'delivered' => 'completed'];
                                    break;
                            }
                            ?>
                            
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_number'] ?? 'CMD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT)); ?></strong>
                                    <br>
                                    <small>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['company_name']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($order['client_email']); ?></small>
                                    <?php if ($order['client_phone']): ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($order['client_phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['product_name']); ?>
                                    <br>
                                    <small>Quantité: <?php echo $order['quantity']; ?> unités</small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $order['card_type']; ?>">
                                        <?php echo strtoupper($order['card_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $order['status']; ?>">
                                        <?php 
                                        $status_labels = [
                                            'pending' => 'En attente',
                                            'processing' => 'En traitement',
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
                                    
                                    <?php if (!empty($timeline)): ?>
                                        <div class="order-timeline">
                                            <?php 
                                            $steps = ['pending' => 'Attente', 'processing' => 'Traitement', 'shipped' => 'Expédition', 'delivered' => 'Livraison'];
                                            foreach ($steps as $step => $label): 
                                                if (isset($timeline[$step])): ?>
                                                    <div class="timeline-step <?php echo $timeline[$step]; ?>" 
                                                         title="<?php echo $label; ?>"></div>
                                                <?php endif;
                                            endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['tracking_number']): ?>
                                        <br>
                                        <small>Tracking: <?php echo htmlspecialchars($order['tracking_number']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo number_format($order['total_price'], 2, ',', ' '); ?>€</strong>
                                    <br>
                                    <small>TTC</small>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($order['created_at'])); ?>
                                    <?php if ($order['estimated_delivery']): ?>
                                        <br>
                                        <small title="Livraison estimée">
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($order['estimated_delivery'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btn-group">
                                        <a href="order-view.php?id=<?php echo $order['id']; ?>" class="action-btn view" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="order-edit.php?id=<?php echo $order['id']; ?>" class="action-btn edit" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <div class="quick-actions-dropdown">
                                            <button class="action-btn quick-actions" title="Actions rapides">
                                                <i class="fas fa-bolt"></i>
                                            </button>
                                            <div class="quick-actions-menu">
                                                <?php if ($order['status'] == 'pending'): ?>
                                                    <div class="quick-action-item" onclick="quickAction(<?php echo $order['id']; ?>, 'mark_processing')">
                                                        <i class="fas fa-sync-alt"></i> Marquer comme en traitement
                                                    </div>
                                                <?php elseif ($order['status'] == 'processing'): ?>
                                                    <div class="quick-action-item" onclick="showTrackingModal(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-truck"></i> Marquer comme expédié
                                                    </div>
                                                <?php elseif ($order['status'] == 'shipped'): ?>
                                                    <div class="quick-action-item" onclick="quickAction(<?php echo $order['id']; ?>, 'mark_delivered')">
                                                        <i class="fas fa-check-circle"></i> Marquer comme livré
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                                                    <div class="quick-action-item text-danger" onclick="quickAction(<?php echo $order['id']; ?>, 'cancel')">
                                                        <i class="fas fa-times"></i> Annuler la commande
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <button <?php echo $page <= 1 ? 'disabled' : ''; ?> 
                            onclick="window.location.href='orders.php?page=<?php echo $page-1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?><?php echo $type ? '&type=' . urlencode($type) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>'">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </button>
                    
                    <div class="page-numbers">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $page-2 && $i <= $page+2)): ?>
                                <a href="orders.php?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?><?php echo $type ? '&type=' . urlencode($type) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>"
                                   class="page-number <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page-3 || $i == $page+3): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <button <?php echo $page >= $totalPages ? 'disabled' : ''; ?> 
                            onclick="window.location.href='orders.php?page=<?php echo $page+1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?><?php echo $type ? '&type=' . urlencode($type) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>'">
                        Suivant <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistiques -->
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Statistiques des commandes</h3>
                    <button class="chart-action-btn" onclick="refreshStats()">
                        <i class="fas fa-sync-alt"></i> Actualiser
                    </button>
                </div>
                <div class="chart-body">
                    <div class="stats-grid">
                        <?php
                        // Revenu total
                        $totalRevenue = $db->query("SELECT SUM(total_price) FROM client_cards WHERE status IN ('active', 'delivered', 'shipped')")->fetchColumn();
                        
                        // Revenu ce mois
                        $revenueMonth = $db->query("
                            SELECT SUM(total_price) 
                            FROM client_cards 
                            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                            AND YEAR(created_at) = YEAR(CURRENT_DATE())
                            AND status IN ('active', 'delivered', 'shipped')
                        ")->fetchColumn();
                        
                        // Panier moyen
                        $avgCart = $db->query("SELECT AVG(total_price) FROM client_cards WHERE status IN ('active', 'delivered', 'shipped')")->fetchColumn();
                        ?>
                        
                        <div class="stat-card mini">
                            <div class="stat-icon" style="background-color: #2ecc71;">
                                <i class="fas fa-euro-sign"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($totalRevenue, 0, ',', ' '); ?>€</h3>
                                <p>Revenu total</p>
                            </div>
                        </div>
                        
                        <div class="stat-card mini">
                            <div class="stat-icon" style="background-color: #3498db;">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($revenueMonth, 0, ',', ' '); ?>€</h3>
                                <p>Revenu ce mois</p>
                            </div>
                        </div>
                        
                        <div class="stat-card mini">
                            <div class="stat-icon" style="background-color: #9b59b6;">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($avgCart, 0, ',', ' '); ?>€</h3>
                                <p>Panier moyen</p>
                            </div>
                        </div>
                        
                        <div class="stat-card mini">
                            <div class="stat-icon" style="background-color: #f39c12;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-info">
                                <h3>
                                    <?php 
                                    // Taux de conversion
                                    $ordersThisMonth = $db->query("
                                        SELECT COUNT(*) 
                                        FROM client_cards 
                                        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                                        AND YEAR(created_at) = YEAR(CURRENT_DATE())
                                        AND status NOT IN ('draft', 'cancelled')
                                    ")->fetchColumn();
                                    
                                    $visitorsMonth = 1000; // À remplacer par des stats réelles
                                    $conversionRate = $visitorsMonth > 0 ? ($ordersThisMonth / $visitorsMonth) * 100 : 0;
                                    echo number_format($conversionRate, 1);
                                    ?>%
                                </h3>
                                <p>Taux de conversion</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Répartition par type</h3>
                </div>
                <div class="chart-body">
                    <div class="type-chart" id="typeChart">
                        <!-- Le graphique sera généré par JavaScript -->
                        <div class="loading">
                            <i class="fas fa-spinner fa-spin"></i> Chargement du graphique...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour le numéro de suivi -->
    <div class="modal" id="trackingModal">
        <div class="modal-overlay" onclick="closeTrackingModal()"></div>
        <div class="modal-content">
            <form id="trackingForm" method="POST" action="">
                <div class="modal-header">
                    <h3>Numéro de suivi</h3>
                    <button type="button" class="modal-close" onclick="closeTrackingModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="trackingOrderId" name="order_id">
                    <input type="hidden" name="quick_action" value="mark_shipped">
                    
                    <div class="form-group">
                        <label for="tracking_number">Numéro de suivi *</label>
                        <input type="text" id="tracking_number" name="tracking_number" required
                               placeholder="Ex: 1Z999AA1234567890">
                        <small>Entrez le numéro de suivi fourni par le transporteur</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="carrier">Transporteur</label>
                        <select id="carrier" name="carrier">
                            <option value="chronopost">Chronopost</option>
                            <option value="colissimo">Colissimo</option>
                            <option value="dhl">DHL</option>
                            <option value="ups">UPS</option>
                            <option value="fedex">FedEx</option>
                            <option value="other">Autre</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeTrackingModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer et marquer comme expédié</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Initialiser DataTable
            $('#ordersTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
                },
                "pageLength": 20,
                "order": [[0, 'desc']],
                "dom": '<"top"f>rt<"bottom"lip><"clear">'
            });
            
            // Initialiser Flatpickr pour les dates
            flatpickr("#date_from, #date_to", {
                locale: "fr",
                dateFormat: "Y-m-d",
            });
            
            // Gestion des menus d'actions rapides
            $('.quick-actions').click(function(e) {
                e.stopPropagation();
                const menu = $(this).siblings('.quick-actions-menu');
                $('.quick-actions-menu').not(menu).removeClass('show');
                menu.toggleClass('show');
            });
            
            // Fermer les menus en cliquant à l'extérieur
            $(document).click(function() {
                $('.quick-actions-menu').removeClass('show');
            });
            
            // Charger le graphique
            loadTypeChart();
        });
        
        function quickAction(orderId, action) {
            if (action === 'mark_shipped') {
                showTrackingModal(orderId);
                return;
            }
            
            if (action === 'cancel' && !confirm('Êtes-vous sûr de vouloir annuler cette commande ?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('quick_action', action);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    alert('Erreur lors de l\'exécution de l\'action');
                }
            })
            .catch(error => {
                alert('Erreur de connexion');
            });
        }
        
        function showTrackingModal(orderId) {
            $('#trackingOrderId').val(orderId);
            $('#trackingModal').addClass('active');
            $('#tracking_number').focus();
        }
        
        function closeTrackingModal() {
            $('#trackingModal').removeClass('active');
            $('#trackingForm')[0].reset();
        }
        
        $('#trackingForm').submit(function(e) {
            e.preventDefault();
            
            const trackingNumber = $('#tracking_number').val();
            if (!trackingNumber) {
                alert('Veuillez entrer un numéro de suivi');
                return;
            }
            
            this.submit();
        });
        
        function loadTypeChart() {
            const data = [
                { key: 'standard', label: 'Standard', value: <?php echo $typeStats['standard'] ?? 0; ?>, color: '#f1c40f' },
                { key: 'nfc', label: 'NFC', value: <?php echo $typeStats['nfc'] ?? 0; ?>, color: '#9b59b6' },
                { key: 'premium', label: 'Premium', value: <?php echo $typeStats['premium'] ?? 0; ?>, color: '#e74c3c' }
            ];
            
            createTypeChart(data);
        }
        
        function createTypeChart(data) {
            const ctx = document.createElement('canvas');
            $('#typeChart').html(ctx);
            
            const labels = data.map(item => item.label);
            const values = data.map(item => item.value);
            const backgroundColors = data.map(item => item.color);
            
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: backgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function refreshStats() {
            // Implémenter le rafraîchissement des statistiques
            location.reload();
        }
        
        // Auto-refresh toutes les 3 minutes pour les nouvelles commandes
        setInterval(function() {
            if (!document.hidden) {
                $.ajax({
                    url: '../api/admin/get-new-orders-count.php',
                    success: function(response) {
                        if (response.success && response.count > 0) {
                            if (confirm(`${response.count} nouvelle(s) commande(s) disponible(s). Rafraîchir la page ?`)) {
                                location.reload();
                            }
                        }
                    }
                });
            }
        }, 180000); // 3 minutes
    </script>
</body>
</html>