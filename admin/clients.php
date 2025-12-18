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
$subscription = $_GET['subscription'] ?? '';

// Construire la requête avec filtres
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(company_name LIKE ? OR email LIKE ? OR contact_person LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status)) {
    if ($status === 'active') {
        $whereClauses[] = "is_active = 1";
    } elseif ($status === 'inactive') {
        $whereClauses[] = "is_active = 0";
    }
}

if (!empty($subscription)) {
    $whereClauses[] = "subscription_type = ?";
    $params[] = $subscription;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Compter le total des clients
$countStmt = $db->prepare("SELECT COUNT(*) FROM clients $whereSQL");
$countStmt->execute($params);
$totalClients = $countStmt->fetchColumn();
$totalPages = ceil($totalClients / $limit);

// Récupérer les clients
$query = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM client_cards WHERE client_id = c.id) as total_cards,
           (SELECT COUNT(*) FROM client_cards WHERE client_id = c.id AND card_type = 'nfc') as nfc_cards,
           (SELECT COUNT(*) FROM demos WHERE client_id = c.id) as demo_requests,
           (SELECT MAX(created_at) FROM client_cards WHERE client_id = c.id) as last_order_date
    FROM clients c
    $whereSQL
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Messages flash
$message = $_SESSION['flash_message'] ?? '';
$message_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Traitement des actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $client_id = $_GET['id'] ?? 0;
    
    if ($action === 'toggle_active' && $client_id) {
        try {
            $stmt = $db->prepare("UPDATE clients SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$client_id]);
            
            $_SESSION['flash_message'] = 'Statut du client mis à jour';
            $_SESSION['flash_type'] = 'success';
            
            header('Location: clients.php');
            exit();
        } catch (Exception $e) {
            $message = 'Erreur: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients - Admin CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
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
                <li class="active"><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
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
                <h1>Gestion des Clients</h1>
                <p>Total: <?php echo $totalClients; ?> clients</p>
            </div>
            <div class="admin-header-right">
                <a href="clients.php?action=export" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Exporter
                </a>
                <a href="client-add.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Nouveau client
                </a>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="search-filter">
            <form method="GET" action="">
                <div class="form-group">
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" placeholder="Nom, email, contact..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <option value="">Tous les statuts</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Actif</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subscription">Abonnement</label>
                    <select id="subscription" name="subscription">
                        <option value="">Tous les abonnements</option>
                        <option value="free" <?php echo $subscription === 'free' ? 'selected' : ''; ?>>Gratuit</option>
                        <option value="basic" <?php echo $subscription === 'basic' ? 'selected' : ''; ?>>Basique</option>
                        <option value="premium" <?php echo $subscription === 'premium' ? 'selected' : ''; ?>>Premium</option>
                        <option value="enterprise" <?php echo $subscription === 'enterprise' ? 'selected' : ''; ?>>Entreprise</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="clients.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Tableau des clients -->
        <div class="recent-table">
            <div class="table-header">
                <h3>Liste des clients</h3>
                <div class="table-actions">
                    <span class="table-info">
                        Affichage <?php echo min(($page-1)*$limit+1, $totalClients); ?>-<?php echo min($page*$limit, $totalClients); ?> sur <?php echo $totalClients; ?>
                    </span>
                </div>
            </div>
            
            <div class="table-container">
                <table id="clientsTable" class="display">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Entreprise</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Cartes</th>
                            <th>Abonnement</th>
                            <th>Statut</th>
                            <th>Inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>#<?php echo str_pad($client['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($client['company_name']); ?></strong>
                                    <?php if ($client['vat_number']): ?>
                                        <br><small>TVA: <?php echo htmlspecialchars($client['vat_number']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($client['contact_person']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($client['email']); ?>
                                    <?php if (!$client['email_verified']): ?>
                                        <span class="badge badge-warning" title="Email non vérifié">NV</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($client['phone'] ?? '-'); ?></td>
                                <td>
                                    <div class="client-stats">
                                        <span class="stat-item" title="Total cartes">
                                            <i class="fas fa-id-card"></i> <?php echo $client['total_cards']; ?>
                                        </span>
                                        <span class="stat-item" title="Cartes NFC">
                                            <i class="fas fa-wifi"></i> <?php echo $client['nfc_cards']; ?>
                                        </span>
                                        <span class="stat-item" title="Démos">
                                            <i class="fas fa-play-circle"></i> <?php echo $client['demo_requests']; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-subscription-<?php echo $client['subscription_type']; ?>">
                                        <?php 
                                        $subscription_labels = [
                                            'free' => 'Gratuit',
                                            'basic' => 'Basique',
                                            'premium' => 'Premium',
                                            'enterprise' => 'Entreprise'
                                        ];
                                        echo $subscription_labels[$client['subscription_type']] ?? $client['subscription_type'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $client['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $client['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                    <?php if ($client['last_login']): ?>
                                        <br>
                                        <small title="Dernière connexion">
                                            <?php echo date('d/m/Y H:i', strtotime($client['last_login'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($client['created_at'])); ?>
                                    <?php if ($client['last_order_date']): ?>
                                        <br>
                                        <small title="Dernière commande">
                                            <?php echo date('d/m/Y', strtotime($client['last_order_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btn-group">
                                        <a href="client-view.php?id=<?php echo $client['id']; ?>" class="action-btn view" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="client-edit.php?id=<?php echo $client['id']; ?>" class="action-btn edit" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="clients.php?action=toggle_active&id=<?php echo $client['id']; ?>" 
                                           class="action-btn <?php echo $client['is_active'] ? 'deactivate' : 'activate'; ?>"
                                           title="<?php echo $client['is_active'] ? 'Désactiver' : 'Activer'; ?>"
                                           onclick="return confirm('<?php echo $client['is_active'] ? 'Désactiver' : 'Activer'; ?> ce client ?')">
                                            <i class="fas fa-<?php echo $client['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                        </a>
                                        <button class="action-btn delete" 
                                                title="Supprimer"
                                                onclick="deleteClient(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars(addslashes($client['company_name'])); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                            onclick="window.location.href='clients.php?page=<?php echo $page-1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>'">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </button>
                    
                    <div class="page-numbers">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $page-2 && $i <= $page+2)): ?>
                                <a href="clients.php?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>"
                                   class="page-number <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page-3 || $i == $page+3): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <button <?php echo $page >= $totalPages ? 'disabled' : ''; ?> 
                            onclick="window.location.href='clients.php?page=<?php echo $page+1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>'">
                        Suivant <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistiques clients -->
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Statistiques clients</h3>
                </div>
                <div class="chart-body">
                    <div class="stats-grid">
                        <?php
                        // Statistiques par abonnement
                        $subscriptionStats = $db->query("
                            SELECT subscription_type, COUNT(*) as count 
                            FROM clients 
                            WHERE is_active = 1 
                            GROUP BY subscription_type
                        ")->fetchAll(PDO::FETCH_KEY_PAIR);
                        
                        // Nouveaux clients ce mois
                        $newClientsMonth = $db->query("
                            SELECT COUNT(*) 
                            FROM clients 
                            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                            AND YEAR(created_at) = YEAR(CURRENT_DATE())
                        ")->fetchColumn();
                        
                        // Taux d'activation email
                        $emailVerified = $db->query("SELECT COUNT(*) FROM clients WHERE email_verified = 1")->fetchColumn();
                        $emailVerificationRate = $totalClients > 0 ? ($emailVerified / $totalClients) * 100 : 0;
                        ?>
                        
                        <div class="stat-card mini">
                            <div class="stat-icon" style="background-color: #3498db;">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $newClientsMonth; ?></h3>
                                <p>Nouveaux ce mois</p>
                            </div>
                        </div>
                        
                        <div class="stat-card mini">
                            <div class="stat-icon" style="background-color: #2ecc71;">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($emailVerificationRate, 1); ?>%</h3>
                                <p>Emails vérifiés</p>
                            </div>
                        </div>
                        
                        <div class="stat-card mini">
                            <div class="stat-icon" style="background-color: #9b59b6;">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $subscriptionStats['premium'] ?? 0; ?></h3>
                                <p>Clients Premium</p>
                            </div>
                        </div>
                        
                        <div class="stat-card mini">
                            <div class="stat-icon" style="background-color: #f39c12;">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $subscriptionStats['enterprise'] ?? 0; ?></h3>
                                <p>Entreprises</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Répartition par abonnement</h3>
                </div>
                <div class="chart-body">
                    <div class="subscription-chart" id="subscriptionChart">
                        <!-- Le graphique sera généré par JavaScript -->
                        <div class="loading">
                            <i class="fas fa-spinner fa-spin"></i> Chargement du graphique...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de suppression -->
    <div class="modal" id="deleteModal">
        <div class="modal-overlay" onclick="closeDeleteModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmer la suppression</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Êtes-vous sûr de vouloir supprimer ce client ?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Attention :</strong> Cette action est irréversible. Toutes les données du client seront supprimées.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                <button class="btn btn-danger" id="confirmDelete">Supprimer définitivement</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Initialiser DataTable
            $('#clientsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
                },
                "pageLength": 20,
                "order": [[0, 'desc']],
                "dom": '<"top"f>rt<"bottom"lip><"clear">',
                "buttons": [
                    {
                        extend: 'copy',
                        text: '<i class="fas fa-copy"></i> Copier',
                        className: 'btn btn-secondary'
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-secondary'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-secondary'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer',
                        className: 'btn btn-secondary'
                    }
                ]
            });
            
            // Charger le graphique
            loadSubscriptionChart();
        });
        
        let clientToDelete = null;
        
        function deleteClient(clientId, clientName) {
            clientToDelete = clientId;
            $('#deleteMessage').text(`Êtes-vous sûr de vouloir supprimer le client "${clientName}" ?`);
            $('#deleteModal').addClass('active');
        }
        
        function closeDeleteModal() {
            $('#deleteModal').removeClass('active');
            clientToDelete = null;
        }
        
        $('#confirmDelete').click(function() {
            if (!clientToDelete) return;
            
            $.ajax({
                url: '../api/admin/delete-client.php',
                method: 'DELETE',
                data: JSON.stringify({ id: clientToDelete }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + response.message);
                        closeDeleteModal();
                    }
                },
                error: function() {
                    alert('Erreur de connexion');
                    closeDeleteModal();
                }
            });
        });
        
        function loadSubscriptionChart() {
            $.ajax({
                url: '../api/admin/get-subscription-stats.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        createSubscriptionChart(response.data);
                    }
                }
            });
        }
        
        function createSubscriptionChart(data) {
            const ctx = document.createElement('canvas');
            $('#subscriptionChart').html(ctx);
            
            const labels = [];
            const values = [];
            const colors = {
                'free': '#95a5a6',
                'basic': '#3498db',
                'premium': '#9b59b6',
                'enterprise': '#f39c12'
            };
            
            const backgroundColors = [];
            
            data.forEach(item => {
                labels.push(item.label);
                values.push(item.value);
                backgroundColors.push(colors[item.key] || '#3498db');
            });
            
            new Chart(ctx, {
                type: 'doughnut',
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
        
        // Auto-refresh toutes les 5 minutes
        setInterval(function() {
            // Ne rafraîchir que si l'utilisateur est actif
            if (!document.hidden) {
                $.ajax({
                    url: '../api/admin/get-clients-count.php',
                    success: function(response) {
                        if (response.success && response.count != <?php echo $totalClients; ?>) {
                            if (confirm('De nouveaux clients ont été ajoutés. Rafraîchir la page ?')) {
                                location.reload();
                            }
                        }
                    }
                });
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>