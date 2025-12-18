<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier l'authentification admin
checkAdminAuth();

$db = Database::getInstance();

// Récupérer tous les produits
$stmt = $db->query("SELECT * FROM products ORDER BY category, base_price");
$products = $stmt->fetchAll();

// Messages flash
$message = $_SESSION['flash_message'] ?? '';
$message_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Traitement des actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $product_id = $_GET['id'] ?? 0;
    
    try {
        switch ($action) {
            case 'toggle_active':
                $stmt = $db->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$product_id]);
                $message = 'Statut du produit mis à jour';
                $message_type = 'success';
                break;
                
            case 'delete':
                // Vérifier si le produit est utilisé dans des commandes
                $stmt = $db->prepare("SELECT COUNT(*) FROM client_cards WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $used_count = $stmt->fetchColumn();
                
                if ($used_count > 0) {
                    $message = 'Impossible de supprimer ce produit car il est utilisé dans ' . $used_count . ' commande(s)';
                    $message_type = 'error';
                } else {
                    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $message = 'Produit supprimé avec succès';
                    $message_type = 'success';
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'Erreur: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Grouper les produits par catégorie
$products_by_category = [];
foreach ($products as $product) {
    $products_by_category[$product['category']][] = $product;
}

// Récupérer les statistiques des produits
$productStats = $db->query("
    SELECT p.id, p.name, COUNT(cc.id) as order_count, SUM(cc.total_price) as total_revenue
    FROM products p
    LEFT JOIN client_cards cc ON p.id = cc.product_id AND cc.status NOT IN ('draft', 'cancelled')
    GROUP BY p.id
    ORDER BY order_count DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Admin CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        .products-categories {
            display: flex;
            flex-direction: column;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .category-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid;
        }
        
        .category-header h3 {
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-header h3 i {
            font-size: 1.2rem;
        }
        
        .category-standard .category-header {
            border-color: #f1c40f;
        }
        
        .category-nfc .category-header {
            border-color: #9b59b6;
        }
        
        .category-premium .category-header {
            border-color: #e74c3c;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .product-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #eee;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .product-title h4 {
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        
        .product-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2ecc71;
        }
        
        .product-features {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 5px;
        }
        
        .product-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .product-features li {
            padding: 5px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .product-features i {
            color: #2ecc71;
            font-size: 0.9rem;
        }
        
        .product-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .product-actions .btn {
            flex: 1;
        }
        
        .empty-category {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        
        .empty-category i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #bdc3c7;
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .badge-popular {
            background: #e74c3c;
            color: white;
        }
        
        .badge-new {
            background: #2ecc71;
            color: white;
        }
        
        .badge-sale {
            background: #f39c12;
            color: white;
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
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
                <li class="active"><a href="products.php"><i class="fas fa-box"></i> Produits</a></li>
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
                <h1>Gestion des Produits</h1>
                <p><?php echo count($products); ?> produits disponibles</p>
            </div>
            <div class="admin-header-right">
                <a href="product-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouveau produit
                </a>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques produits -->
        <div class="admin-stats">
            <?php
            // Compter les produits par catégorie
            $categoryCounts = [];
            $categoryRevenue = [];
            
            foreach ($productStats as $stat) {
                if (!isset($categoryCounts[$stat['category'] ?? ''])) {
                    $categoryCounts[$stat['category'] ?? ''] = 0;
                    $categoryRevenue[$stat['category'] ?? ''] = 0;
                }
                $categoryCounts[$stat['category'] ?? '']++;
                $categoryRevenue[$stat['category'] ?? ''] += $stat['total_revenue'];
            }
            
            // Produits les plus populaires
            usort($productStats, function($a, $b) {
                return $b['order_count'] - $a['order_count'];
            });
            $mostPopular = array_slice($productStats, 0, 1)[0] ?? null;
            ?>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon" style="background-color: #f1c40f;">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo $categoryCounts['standard'] ?? 0; ?></h3>
                    <p>Cartes avec QR Code</p>
                </div>
                <div class="admin-stat-change">
                    <?php echo number_format($categoryRevenue['standard'] ?? 0, 0, ',', ' '); ?>FCFA
                </div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon" style="background-color: #9b59b6;">
                    <i class="fas fa-wifi"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo $categoryCounts['nfc'] ?? 0; ?></h3>
                    <p>Cartes NFC</p>
                </div>
                <div class="admin-stat-change">
                    <?php echo number_format($categoryRevenue['nfc'] ?? 0, 0, ',', ' '); ?>FCFA
                </div>
            </div>
        </div>

        <!-- Liste des produits par catégorie -->
        <div class="products-categories">
            <!-- Cartes QR Code -->
            <div class="category-section category-standard">
                <div class="category-header">
                    <h3><i class="fas fa-id-card" style="color: #f1c40f;"></i> Cartes avec QR Code</h3>
                    <a href="product-add.php?category=standard" class="btn btn-outline">
                        <i class="fas fa-plus"></i> Ajouter une carte QR Code
                    </a>
                </div>
                
                <?php if (empty($products_by_category['standard'])): ?>
                    <div class="empty-category">
                        <i class="fas fa-id-card"></i>
                        <h4>Aucune carte QR Code</h4>
                        <p>Créez votre première carte QR Code</p>
                        <a href="product-add.php?category=standard" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Créer une carte QR Code
                        </a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products_by_category['standard'] as $product): ?>
                            <?php 
                            $features = $product['features'] ? json_decode($product['features'], true) : [];
                            $options = $product['options'] ? json_decode($product['options'], true) : [];
                            $productStat = array_filter($productStats, function($stat) use ($product) {
                                return $stat['id'] == $product['id'];
                            });
                            $productStat = reset($productStat);
                            ?>
                            
                            <div class="product-card">
                                <?php if ($productStat && $productStat['order_count'] > 10): ?>
                                    <span class="product-badge badge-popular">Populaire</span>
                                <?php endif; ?>
                                
                                <?php if ($product['is_active'] === false): ?>
                                    <span class="product-badge" style="background: #95a5a6;">Inactif</span>
                                <?php endif; ?>
                                
                                <div class="product-header">
                                    <div class="product-title">
                                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                        <small>Délai: <?php echo $product['delivery_days']; ?> jours</small>
                                    </div>
                                    <div class="product-status">
                                        <span class="product-price">
                                            <?php echo number_format($product['base_price'], 2, ',', ' '); ?>FCFA
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="product-description">
                                    <p><?php echo htmlspecialchars($product['description']); ?></p>
                                </div>
                                
                                <?php if (!empty($features)): ?>
                                    <div class="product-features">
                                        <ul>
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($feature); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="product-stats">
                                    <div>
                                        <strong><?php echo $productStat['order_count'] ?? 0; ?></strong>
                                        <div>Commandes</div>
                                    </div>
                                    <div>
                                        <strong><?php echo number_format($productStat['total_revenue'] ?? 0, 0, ',', ' '); ?>FCFA</strong>
                                        <div>Revenu</div>
                                    </div>
                                    <div>
                                        <strong><?php echo $product['stock_quantity']; ?></strong>
                                        <div>Stock</div>
                                    </div>
                                </div>
                                
                                <div class="product-actions">
                                    <a href="product-edit.php?id=<?php echo $product['id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="products.php?action=toggle_active&id=<?php echo $product['id']; ?>" 
                                       class="btn btn-<?php echo $product['is_active'] ? 'warning' : 'success'; ?>"
                                       onclick="return confirm('<?php echo $product['is_active'] ? 'Désactiver' : 'Activer'; ?> ce produit ?')">
                                        <i class="fas fa-<?php echo $product['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                        <?php echo $product['is_active'] ? 'Désactiver' : 'Activer'; ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cartes NFC -->
            <div class="category-section category-nfc">
                <div class="category-header">
                    <h3><i class="fas fa-wifi" style="color: #9b59b6;"></i> Cartes NFC</h3>
                    <a href="product-add.php?category=nfc" class="btn btn-outline">
                        <i class="fas fa-plus"></i> Ajouter une carte NFC
                    </a>
                </div>
                
                <?php if (empty($products_by_category['nfc'])): ?>
                    <div class="empty-category">
                        <i class="fas fa-wifi"></i>
                        <h4>Aucune carte NFC</h4>
                        <p>Créez votre première carte NFC</p>
                        <a href="product-add.php?category=nfc" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Créer une carte NFC
                        </a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products_by_category['nfc'] as $product): ?>
                            <?php 
                            $features = $product['features'] ? json_decode($product['features'], true) : [];
                            $options = $product['options'] ? json_decode($product['options'], true) : [];
                            $productStat = array_filter($productStats, function($stat) use ($product) {
                                return $stat['id'] == $product['id'];
                            });
                            $productStat = reset($productStat);
                            ?>
                            
                            <div class="product-card">
                                <?php if ($productStat && $productStat['order_count'] > 5): ?>
                                    <span class="product-badge badge-popular">Populaire</span>
                                <?php endif; ?>
                                
                                <?php if ($product['is_active'] === false): ?>
                                    <span class="product-badge" style="background: #95a5a6;">Inactif</span>
                                <?php endif; ?>
                                
                                <div class="product-header">
                                    <div class="product-title">
                                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                        <small>Délai: <?php echo $product['delivery_days']; ?> jours</small>
                                    </div>
                                    <div class="product-status">
                                        <span class="product-price">
                                            <?php echo number_format($product['base_price'], 2, ',', ' '); ?>FCFA
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="product-description">
                                    <p><?php echo htmlspecialchars($product['description']); ?></p>
                                </div>
                                
                                <?php if (!empty($features)): ?>
                                    <div class="product-features">
                                        <ul>
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($feature); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($options['nfc_types'])): ?>
                                    <div class="product-options">
                                        <small><strong>Types NFC:</strong> <?php echo implode(', ', $options['nfc_types']); ?></small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="product-stats">
                                    <div>
                                        <strong><?php echo $productStat['order_count'] ?? 0; ?></strong>
                                        <div>Commandes</div>
                                    </div>
                                    <div>
                                        <strong><?php echo number_format($productStat['total_revenue'] ?? 0, 0, ',', ' '); ?>FCFA</strong>
                                        <div>Revenu</div>
                                    </div>
                                    <div>
                                        <strong><?php echo $product['stock_quantity']; ?></strong>
                                        <div>Stock</div>
                                    </div>
                                </div>
                                
                                <div class="product-actions">
                                    <a href="product-edit.php?id=<?php echo $product['id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="products.php?action=toggle_active&id=<?php echo $product['id']; ?>" 
                                       class="btn btn-<?php echo $product['is_active'] ? 'warning' : 'success'; ?>"
                                       onclick="return confirm('<?php echo $product['is_active'] ? 'Désactiver' : 'Activer'; ?> ce produit ?')">
                                        <i class="fas fa-<?php echo $product['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                        <?php echo $product['is_active'] ? 'Désactiver' : 'Activer'; ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tableau des statistiques -->
        <div class="recent-table">
            <div class="table-header">
                <h3>Statistiques des produits</h3>
                <div class="table-actions">
                    <button class="table-action-btn active" onclick="filterStats('all')">Tous</button>
                    <button class="table-action-btn" onclick="filterStats('popular')">Populaires</button>
                    <button class="table-action-btn" onclick="filterStats('inactive')">Inactifs</button>
                </div>
            </div>
            
            <div class="table-container">
                <table id="productStatsTable" class="display">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Commandes</th>
                            <th>Revenu</th>
                            <th>Stock</th>
                            <th>Statut</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productStats as $stat): ?>
                            <?php 
                            $product = array_filter($products, function($p) use ($stat) {
                                return $p['id'] == $stat['id'];
                            });
                            $product = reset($product);
                            if (!$product) continue;
                            
                            $performance = $stat['order_count'] > 10 ? 'excellent' : 
                                         ($stat['order_count'] > 5 ? 'bon' : 
                                         ($stat['order_count'] > 0 ? 'moyen' : 'faible'));
                            ?>
                            
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($stat['name']); ?></strong>
                                    <br>
                                    <small>Délai: <?php echo $product['delivery_days']; ?> jours</small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $product['category']; ?>">
                                        <?php 
                                        $category_labels = [
                                            'standard' => 'Standard',
                                            'nfc' => 'NFC',
                                            'premium' => 'Premium'
                                        ];
                                        echo $category_labels[$product['category']] ?? $product['category'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo number_format($product['base_price'], 2, ',', ' '); ?>FCFA
                                </td>
                                <td>
                                    <strong><?php echo $stat['order_count']; ?></strong>
                                    <br>
                                    <small>
                                        <?php 
                                        $totalOrders = array_sum(array_column($productStats, 'order_count'));
                                        $percentage = $totalOrders > 0 ? ($stat['order_count'] / $totalOrders) * 100 : 0;
                                        echo number_format($percentage, 1); ?>%
                                    </small>
                                </td>
                                <td>
                                    <strong><?php echo number_format($categoryRevenue[$stat['category'] ?? ''], 0, ',', ' '); ?>FCFA</strong>
                                    <br>
                                    <small>
                                        <?php 
                                        $totalRevenue = array_sum(array_column($productStats, 'total_revenue'));
                                        $percentage = $totalRevenue > 0 ? ($stat['total_revenue'] / $totalRevenue) * 100 : 0;
                                        echo number_format($percentage, 1); ?>%
                                    </small>
                                </td>
                                <td>
                                    <span class="<?php echo $product['stock_quantity'] < 10 ? 'text-danger' : ($product['stock_quantity'] < 50 ? 'text-warning' : 'text-success'); ?>">
                                        <?php echo $product['stock_quantity']; ?>
                                    </span>
                                    <?php if ($product['stock_quantity'] < 10): ?>
                                        <br>
                                        <small class="text-danger">Stock faible</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $product['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="performance-bar">
                                        <div class="performance-fill performance-<?php echo $performance; ?>"
                                             style="width: <?php echo min(100, $stat['order_count'] * 10); ?>%">
                                            <?php 
                                            $performance_labels = [
                                                'excellent' => 'Excellent',
                                                'bon' => 'Bon',
                                                'moyen' => 'Moyen',
                                                'faible' => 'Faible'
                                            ];
                                            echo $performance_labels[$performance];
                                            ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
                        </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialiser DataTable pour les statistiques
            $('#productStatsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
                },
                "order": [[3, 'desc']],
                "pageLength": 10
            });
            
            // Afficher/fermer les notifications
            $('#notificationsBell').click(function() {
                $('#notificationsModal').toggleClass('active');
            });
            
            // Gestion du dropdown utilisateur
            $('#userDropdown').click(function() {
                $('#userDropdownMenu').toggleClass('show');
            });
            
            // Fermer les menus en cliquant à l'extérieur
            $(document).click(function(e) {
                if (!$(e.target).closest('#userDropdown, #userDropdownMenu').length) {
                    $('#userDropdownMenu').removeClass('show');
                }
                if (!$(e.target).closest('#notificationsBell, .modal-content').length) {
                    $('#notificationsModal').removeClass('active');
                }
            });
        });
        
        function filterStats(filter) {
            const table = $('#productStatsTable').DataTable();
            
            switch(filter) {
                case 'all':
                    table.search('').draw();
                    break;
                case 'popular':
                    table.column(3).search('^[5-9]|1[0-9]|2[0-9]', true, false).draw();
                    break;
                case 'inactive':
                    table.column(6).search('inactive', true, false).draw();
                    break;
            }
            
            // Mettre à jour les boutons actifs
            $('.table-action-btn').removeClass('active');
            event.target.classList.add('active');
        }
        
        function confirmDelete(productId, productName) {
            if (confirm('Êtes-vous sûr de vouloir supprimer le produit "' + productName + '" ?')) {
                window.location.href = 'products.php?action=delete&id=' + productId;
            }
        }
    </script>
</body>
</html>