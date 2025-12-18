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

// Récupérer la catégorie depuis l'URL
$category = $_GET['category'] ?? '';
$categories = ['standard', 'nfc', 'premium'];

if (!in_array($category, $categories)) {
    header('Location: products.php');
    exit;
}

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
        $features = [];
        if (!empty($_POST['features'])) {
            $features = array_filter(array_map('trim', explode("\n", $_POST['features'])));
        }
        
        // Récupérer les options selon la catégorie
        $options = [];
        if ($category === 'nfc') {
            $nfc_types = [];
            if (isset($_POST['nfc_types'])) {
                $nfc_types = array_map('trim', $_POST['nfc_types']);
            }
            $options['nfc_types'] = $nfc_types;
            $options['nfc_range'] = trim($_POST['nfc_range'] ?? '');
            $options['programming_interface'] = trim($_POST['programming_interface'] ?? '');
        } elseif ($category === 'premium') {
            $special_finishes = [];
            if (isset($_POST['special_finishes'])) {
                $special_finishes = array_map('trim', $_POST['special_finishes']);
            }
            $options['special_finishes'] = $special_finishes;
            $options['premium_materials'] = trim($_POST['premium_materials'] ?? '');
        }
        
        // Validation
        if (empty($name) || empty($description) || $base_price <= 0) {
            throw new Exception('Veuillez remplir tous les champs obligatoires');
        }
        
        // Insérer le produit
        $stmt = $db->prepare("
            INSERT INTO products (name, description, base_price, category, delivery_days, 
                                 stock_quantity, is_active, features, options, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $features_json = json_encode($features);
        $options_json = json_encode($options);
        
        $stmt->execute([
            $name, $description, $base_price, $category, $delivery_days,
            $stock_quantity, $is_active, $features_json, $options_json
        ]);
        
        $_SESSION['flash_message'] = 'Produit créé avec succès';
        $_SESSION['flash_type'] = 'success';
        
        header('Location: products.php');
        exit;
        
    } catch (Exception $e) {
        $message = 'Erreur: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Données par défaut selon la catégorie
$default_features = [];
$default_options = [];

switch ($category) {
    case 'standard':
        $default_features = [
            'Impression recto-verso',
            'Papier premium 350g',
            'Finition brillante ou mate',
            'Coins arrondis optionnels',
            'Livraison standard'
        ];
        break;
        
    case 'nfc':
        $default_features = [
            'Carte NFC programmable',
            'Compatibilité smartphone',
            'Application dédiée',
            'Redirection URL personnalisée',
            'Mise à jour à distance'
        ];
        $default_options = [
            'nfc_types' => ['NTAG213', 'NTAG215', 'NTAG216'],
            'nfc_range' => '5-10 cm',
            'programming_interface' => 'Web interface'
        ];
        break;
        
    case 'premium':
        $default_features = [
            'Matériaux premium (bois, métal, acrylique)',
            'Gravure laser de précision',
            'Finition sur mesure',
            'Packaging luxe',
            'Livraison express'
        ];
        $default_options = [
            'special_finishes' => ['Dorure', 'Argenté', 'Brossé', 'Satiné'],
            'premium_materials' => 'Bois d\'olivier, acier inoxydable'
        ];
        break;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Admin CartesVisitePro</title>
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
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body class="admin-dashboard category-<?php echo $category; ?>">
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
                <h1>Ajouter un produit</h1>
                <p>Créer une nouvelle carte de visite</p>
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
                    <i class="fas <?php echo $icons[$category]; ?>" style="font-size: 1.5rem;"></i>
                    <h2>Informations de base - Carte <?php echo $labels[$category]; ?></h2>
                    <span class="category-badge badge-<?php echo $category; ?>">
                        <?php echo $labels[$category]; ?>
                    </span>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nom du produit *</label>
                        <input type="text" id="name" name="name" required 
                               placeholder="Ex: Carte Standard Élégance">
                    </div>
                    
                    <div class="form-group">
                        <label for="base_price">Prix de base (€) *</label>
                        <input type="number" id="base_price" name="base_price" 
                               step="0.01" min="0" required 
                               placeholder="Ex: 49.99">
                    </div>
                    
                    <div class="form-group">
                        <label for="delivery_days">Délai de livraison (jours) *</label>
                        <input type="number" id="delivery_days" name="delivery_days" 
                               min="1" max="30" required value="7">
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_quantity">Quantité en stock</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" 
                               min="0" value="100">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" rows="3" required
                                  placeholder="Décrivez votre produit..."><?php 
                            if ($category === 'standard') {
                                echo "Carte de visite standard de haute qualité, parfaite pour les professionnels qui veulent faire une excellente première impression.";
                            } elseif ($category === 'nfc') {
                                echo "Carte de visite NFC intelligente qui permet de partager vos informations de contact instantanément via smartphone.";
                            } else {
                                echo "Carte de visite premium en matériaux luxueux, pour ceux qui veulent se démarquer avec élégance.";
                            }
                        ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" checked>
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
                    <textarea id="features" name="features" rows="6" 
                              placeholder="Exemple:&#10;Impression recto-verso&#10;Papier premium 350g&#10;Finition brillante"><?php 
                        echo implode("\n", $default_features);
                    ?></textarea>
                    <small class="form-text">Saisissez une caractéristique par ligne</small>
                </div>
                
                <div class="features-list">
                    <h4>Exemple de caractéristiques :</h4>
                    <?php foreach ($default_features as $feature): ?>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span><?php echo htmlspecialchars($feature); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Options spécifiques -->
            <?php if ($category === 'nfc'): ?>
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-sliders-h" style="font-size: 1.5rem;"></i>
                        <h2>Options NFC</h2>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Types NFC supportés</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="nfc_types[]" value="NTAG213" checked>
                                    NTAG213
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="nfc_types[]" value="NTAG215" checked>
                                    NTAG215
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="nfc_types[]" value="NTAG216" checked>
                                    NTAG216
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="nfc_types[]" value="MIFARE Classic">
                                    MIFARE Classic
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="nfc_types[]" value="MIFARE Ultralight">
                                    MIFARE Ultralight
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="nfc_range">Portée NFC</label>
                            <input type="text" id="nfc_range" name="nfc_range" 
                                   value="<?php echo $default_options['nfc_range']; ?>"
                                   placeholder="Ex: 5-10 cm">
                        </div>
                        
                        <div class="form-group">
                            <label for="programming_interface">Interface de programmation</label>
                            <input type="text" id="programming_interface" name="programming_interface" 
                                   value="<?php echo $default_options['programming_interface']; ?>"
                                   placeholder="Ex: Web interface">
                        </div>
                    </div>
                </div>
                
            <?php elseif ($category === 'premium'): ?>
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-gem" style="font-size: 1.5rem;"></i>
                        <h2>Options Premium</h2>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Finitions spéciales</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="special_finishes[]" value="Dorure" checked>
                                    Dorure
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="special_finishes[]" value="Argenté" checked>
                                    Argenté
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="special_finishes[]" value="Brossé" checked>
                                    Brossé
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="special_finishes[]" value="Satiné" checked>
                                    Satiné
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="special_finishes[]" value="Gravure laser">
                                    Gravure laser
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="special_finishes[]" value="Biseautage">
                                    Biseautage
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="premium_materials">Matériaux premium disponibles</label>
                            <input type="text" id="premium_materials" name="premium_materials" 
                                   value="<?php echo $default_options['premium_materials']; ?>"
                                   placeholder="Ex: Bois d'olivier, acier inoxydable, acrylique, cuir">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions du formulaire -->
            <div class="form-actions">
                <a href="products.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Créer le produit
                </button>
            </div>
        </form>
    </div>

    <script>
        // Auto-générer un nom basé sur la catégorie
        document.addEventListener('DOMContentLoaded', function() {
            const category = '<?php echo $category; ?>';
            const nameInput = document.getElementById('name');
            
            if (nameInput && !nameInput.value) {
                const baseName = category === 'standard' ? 'Carte Standard ' :
                               category === 'nfc' ? 'Carte NFC ' :
                               'Carte Premium ';
                nameInput.value = baseName + 'Premium';
            }
            
            // Ajuster le prix par défaut selon la catégorie
            const priceInput = document.getElementById('base_price');
            if (priceInput && !priceInput.value) {
                const defaultPrice = category === 'standard' ? '49.99' :
                                   category === 'nfc' ? '99.99' :
                                   '149.99';
                priceInput.value = defaultPrice;
            }
        });
    </script>
</body>
</html>