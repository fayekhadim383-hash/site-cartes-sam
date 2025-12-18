<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier l'authentification admin
checkAdminAuth();

$db = Database::getInstance();

$message = '';
$message_type = '';

// Récupérer l'ID du produit
$product_id = $_GET['id'] ?? 0;

if (!$product_id) {
    header('Location: products.php');
    exit;
}

// Récupérer le produit
$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

// Décoder les JSON
$features = $product['features'] ? json_decode($product['features'], true) : [];
$options = $product['options'] ? json_decode($product['options'], true) : [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_price = floatval($_POST['base_price']);
        $delivery_days = intval($_POST['delivery_days']);
        $stock_quantity = intval($_POST['stock_quantity']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Récupérer les caractéristiques
        $new_features = [];
        if (!empty($_POST['features'])) {
            $new_features = array_filter(array_map('trim', explode("\n", $_POST['features'])));
        }
        
        // Récupérer les options selon la catégorie
        $new_options = $options;
        if ($product['category'] === 'nfc') {
            $nfc_types = [];
            if (isset($_POST['nfc_types'])) {
                $nfc_types = array_map('trim', $_POST['nfc_types']);
            }
            $new_options['nfc_types'] = $nfc_types;
            $new_options['nfc_range'] = trim($_POST['nfc_range'] ?? '');
            $new_options['programming_interface'] = trim($_POST['programming_interface'] ?? '');
        } elseif ($product['category'] === 'premium') {
            $special_finishes = [];
            if (isset($_POST['special_finishes'])) {
                $special_finishes = array_map('trim', $_POST['special_finishes']);
            }
            $new_options['special_finishes'] = $special_finishes;
            $new_options['premium_materials'] = trim($_POST['premium_materials'] ?? '');
        }
        
        // Validation
        if (empty($name) || empty($description) || $base_price <= 0) {
            throw new Exception('Veuillez remplir tous les champs obligatoires');
        }
        
        // Mettre à jour le produit
        $stmt = $db->prepare("
            UPDATE products 
            SET name = ?, description = ?, base_price = ?, delivery_days = ?,
                stock_quantity = ?, is_active = ?, features = ?, options = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $features_json = json_encode($new_features);
        $options_json = json_encode($new_options);
        
        $stmt->execute([
            $name, $description, $base_price, $delivery_days,
            $stock_quantity, $is_active, $features_json, $options_json, $product_id
        ]);
        
        $_SESSION['flash_message'] = 'Produit mis à jour avec succès';
        $_SESSION['flash_type'] = 'success';
        
        header('Location: products.php');
        exit;
        
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
    <title>Modifier produit - Admin CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid;
        }
        
        .category-standard .form-section-header {
            border-color: #f1c40f;
        }
        
        .category-nfc .form-section-header {
            border-color: #9b59b6;
        }
        
        .category-premium .form-section-header {
            border-color: #e74c3c;
        }
        
        .form-section-header h2 {
            margin-bottom: 0;
        }
        
        .category-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .badge-standard {
            background: #f1c40f;
            color: #000;
        }
        
        .badge-nfc {
            background: #9b59b6;
            color: white;
        }
        
        .badge-premium {
            background: #e74c3c;
            color: white;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .features-list {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .feature-item:last-child {
            border-bottom: none;
        }
        
        .feature-item i {
            color: #2ecc71;
        }
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .product-info-sidebar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #7f8c8d;
        }
        
        .info-value {
            text-align: right;
        }
    </style>
</head>
<body class="admin-dashboard category-<?php echo $product['category']; ?>">
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
                <h1>Modifier le produit</h1>
                <p><?php echo htmlspecialchars($product['name']); ?></p>
            </div>
            <div class="admin-header-right">
                <a href="products.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Retour aux produits
                </a>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Informations sur le produit -->
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Statistiques du produit</h3>
                </div>
                <div class="chart-body">
                    <?php 
                    // Récupérer les statistiques du produit
                    $stmt = $db->prepare("
                        SELECT COUNT(cc.id) as order_count, 
                               SUM(cc.total_price) as total_revenue,
                               AVG(cc.total_price) as avg_order_value
                        FROM client_cards cc
                        WHERE cc.product_id = ? 
                        AND cc.status NOT IN ('draft', 'cancelled')
                    ");
                    $stmt->execute([$product_id]);
                    $stats = $stmt->fetch();
                    ?>
                    
                    <div class="product-info-sidebar">
                        <div class="info-item">
                            <span class="info-label">ID:</span>
                            <span class="info-value">#<?php echo str_pad($product_id, 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Créé le:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($product['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Dernière modification:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($product['updated_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Commandes totales:</span>
                            <span class="info-value"><?php echo $stats['order_count'] ?? 0; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Revenu total:</span>
                            <span class="info-value"><?php echo number_format($stats['total_revenue'] ?? 0, 2, ',', ' '); ?>€</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Valeur moyenne:</span>
                            <span class="info-value"><?php echo number_format($stats['avg_order_value'] ?? 0, 2, ',', ' '); ?>€</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire -->
        <form method="POST" action="">
            <!-- Informations de base -->
            <div class="form-section">
                <div class="form-section-header">
                    <?php 
                    $icons = [
                        'standard' => 'fa-id-card',
                        'nfc' => 'fa-wifi',
                        'premium' => 'fa-crown'
                    ];
                    $labels = [
                        'standard' => 'Standard',
                        'nfc' => 'NFC',
                        'premium' => 'Premium'
                    ];
                    ?>
                    <i class="fas <?php echo $icons[$product['category']]; ?>" style="font-size: 1.5rem;"></i>
                    <h2>Informations de base</h2>
                    <span class="category-badge badge-<?php echo $product['category']; ?>">
                        <?php echo $labels[$product['category']]; ?>
                    </span>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nom du produit *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="base_price">Prix de base (€) *</label>
                        <input type="number" id="base_price" name="base_price" 
                               step="0.01" min="0" required 
                               value="<?php echo number_format($product['base_price'], 2, '.', ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="delivery_days">Délai de livraison (jours) *</label>
                        <input type="number" id="delivery_days" name="delivery_days" 
                               min="1" max="30" required 
                               value="<?php echo $product['delivery_days']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_quantity">Quantité en stock</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" 
                               min="0" value="<?php echo $product['stock_quantity']; ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" rows="3" required
                                  ><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                            Produit actif
                        </label>
                        <small class="form-text">Décocher pour masquer temporairement le produit</small>
                    </div>
                </div>
            </div>

            <!-- Caractéristiques -->
            <div class="form-section">
                <div class="form-section-header">
                    <i class="fas fa-list-check" style="font-size: 1.5rem;"></i>
                    <h2>Caractéristiques</h2>
                </div>
                
                <div class="form-group full-width">
                    <label for="features">Liste des caractéristiques (une par ligne)</label>
                    <textarea id="features" name="features" rows="6"><?php 
                        echo implode("\n", $features);
                    ?></textarea>
                    <small class="form-text">Saisissez une caractéristique par ligne</small>
                </div>
            </div>

            <!-- Options spécifiques -->
            <?php if ($product['category'] === 'nfc'): ?>
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-sliders-h" style="font-size: 1.5rem;"></i>
                        <h2>Options NFC</h2>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Types NFC supportés</label>
                            <div class="checkbox-group">
                                <?php 
                                $nfc_types = $options['nfc_types'] ?? [];
                                $all_nfc_types = ['NTAG213', 'NTAG215', 'NTAG216', 'MIFARE Classic', 'MIFARE Ultralight'];
                                foreach ($all_nfc_types as $type): 
                                ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="nfc_types[]" value="<?php echo $type; ?>"
                                            <?php echo in_array($type, $nfc_types) ? 'checked' : ''; ?>>
                                        <?php echo $type; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="nfc_range">Portée NFC</label>
                            <input type="text" id="nfc_range" name="nfc_range" 
                                   value="<?php echo htmlspecialchars($options['nfc_range'] ?? ''); ?>"
                                   placeholder="Ex: 5-10 cm">
                        </div>
                        
                        <div class="form-group">
                            <label for="programming_interface">Interface de programmation</label>
                            <input type="text" id="programming_interface" name="programming_interface" 
                                   value="<?php echo htmlspecialchars($options['programming_interface'] ?? ''); ?>"
                                   placeholder="Ex: Web interface">
                        </div>
                    </div>
                </div>
                
            <?php elseif ($product['category'] === 'premium'): ?>
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-gem" style="font-size: 1.5rem;"></i>
                        <h2>Options Premium</h2>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Finitions spéciales</label>
                            <div class="checkbox-group">
                                <?php 
                                $finishes = $options['special_finishes'] ?? [];
                                $all_finishes = ['Dorure', 'Argenté', 'Brossé', 'Satiné', 'Gravure laser', 'Biseautage'];
                                foreach ($all_finishes as $finish): 
                                ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="special_finishes[]" value="<?php echo $finish; ?>"
                                            <?php echo in_array($finish, $finishes) ? 'checked' : ''; ?>>
                                        <?php echo $finish; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="premium_materials">Matériaux premium disponibles</label>
                            <input type="text" id="premium_materials" name="premium_materials" 
                                   value="<?php echo htmlspecialchars($options['premium_materials'] ?? ''); ?>"
                                   placeholder="Ex: Bois d'olivier, acier inoxydable, acrylique, cuir">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions du formulaire -->
            <div class="form-actions">
                <div>
                    <a href="products.php?action=delete&id=<?php echo $product_id; ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('Supprimer définitivement ce produit ?')">
                        <i class="fas fa-trash"></i> Supprimer
                    </a>
                </div>
                <div>
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Confirmation avant suppression
        function confirmDelete() {
            return confirm('Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.');
        }
        
        // Ajustement automatique des checkboxes pour NFC
        document.addEventListener('DOMContentLoaded', function() {
            const category = '<?php echo $product['category']; ?>';
            
            if (category === 'nfc') {
                // Ajouter un événement pour sélectionner/déselectionner tous les types NFC
                const selectAllBtn = document.createElement('button');
                selectAllBtn.type = 'button';
                selectAllBtn.className = 'btn btn-sm btn-outline';
                selectAllBtn.innerHTML = '<i class="fas fa-check-double"></i> Tout sélectionner';
                selectAllBtn.style.marginBottom = '10px';
                
                const checkboxGroup = document.querySelector('.checkbox-group');
                if (checkboxGroup) {
                    checkboxGroup.parentNode.insertBefore(selectAllBtn, checkboxGroup);
                    
                    selectAllBtn.addEventListener('click', function() {
                        const checkboxes = checkboxGroup.querySelectorAll('input[type="checkbox"]');
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                        
                        checkboxes.forEach(cb => {
                            cb.checked = !allChecked;
                        });
                        
                        selectAllBtn.innerHTML = allChecked ? 
                            '<i class="fas fa-check-double"></i> Tout sélectionner' :
                            '<i class="fas fa-times"></i> Tout désélectionner';
                    });
                }
            }
        });
    </script>
</body>
</html>