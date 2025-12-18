<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier l'authentification
checkClientAuth();

$db = Database::getInstance();
$client_id = $_SESSION['client_id'];

// Récupérer les informations du client
$stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

// Récupérer les produits disponibles
$stmt = $db->query("SELECT * FROM products WHERE is_active = 1 ORDER BY category, base_price");
$products = $stmt->fetchAll();

// Grouper les produits par catégorie
$products_by_category = [];
foreach ($products as $product) {
    $products_by_category[$product['category']][] = $product;
}

// Récupérer les dernières cartes du client pour suggérer des modifications
$stmt = $db->prepare("
    SELECT cc.*, p.name as product_name 
    FROM client_cards cc 
    JOIN products p ON cc.product_id = p.id 
    WHERE cc.client_id = ? 
    AND cc.status != 'cancelled'
    ORDER BY cc.created_at DESC 
    LIMIT 3
");
$stmt->execute([$client_id]);
$recent_cards = $stmt->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $design_data = [];
        $options = [];
        
        // Récupérer le produit sélectionné
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception('Produit non trouvé');
        }
        
        // Récupérer les données de conception
        $design_data = [
            'company_name' => trim($_POST['company_name']),
            'contact_name' => trim($_POST['contact_name']),
            'title' => trim($_POST['title'] ?? ''),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email']),
            'website' => trim($_POST['website'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'tagline' => trim($_POST['tagline'] ?? ''),
            'social_links' => [
                'linkedin' => trim($_POST['linkedin'] ?? ''),
                'twitter' => trim($_POST['twitter'] ?? ''),
                'facebook' => trim($_POST['facebook'] ?? ''),
                'instagram' => trim($_POST['instagram'] ?? ''),
            ]
        ];
        
        // Récupérer les options selon le type de produit
        if ($product['category'] === 'standard' || $product['category'] === 'premium') {
            $options = [
                'paper_type' => $_POST['paper_type'] ?? 'mat',
                'finish' => $_POST['finish'] ?? 'coins_droits',
                'special_finish' => $_POST['special_finish'] ?? 'none'
            ];
        } elseif ($product['category'] === 'nfc') {
            $options = [
                'nfc_type' => $_POST['nfc_type'] ?? 'standard',
                'nfc_url' => trim($_POST['nfc_url'] ?? ''),
                'nfc_vcard' => isset($_POST['nfc_vcard']) ? true : false,
                'nfc_custom' => isset($_POST['nfc_custom']) ? true : false
            ];
        }
        
        // Validation
        if (empty($design_data['company_name']) || empty($design_data['contact_name']) || 
            empty($design_data['phone']) || empty($design_data['email'])) {
            throw new Exception('Veuillez remplir tous les champs obligatoires');
        }
        
        if ($quantity < 1 || $quantity > 5000) {
            throw new Exception('Quantité invalide');
        }
        
        // Calculer le prix
        $unit_price = $product['base_price'];
        $total_price = $unit_price * ($quantity / 100); // Prix pour 100 unités
        
        // Ajouter des suppléments pour les options premium
        if ($product['category'] === 'premium' && isset($options['special_finish'])) {
            switch ($options['special_finish']) {
                case 'dorure':
                    $total_price += 20;
                    break;
                case 'gaufrage':
                    $total_price += 15;
                    break;
                case 'vernis':
                    $total_price += 10;
                    break;
            }
        }
        
        // Générer un numéro de commande
        $order_number = 'CMD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insérer la nouvelle carte
        $stmt = $db->prepare("
            INSERT INTO client_cards (
                client_id, product_id, order_number, card_type, design_data, 
                nfc_data, status, quantity, unit_price, total_price, 
                delivery_address, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $nfc_data = $product['category'] === 'nfc' ? json_encode(['url' => $options['nfc_url']]) : null;
        $delivery_address = json_encode([
            'company' => $design_data['company_name'],
            'contact' => $design_data['contact_name'],
            'address' => $client['address'] ?? '',
            'city' => $client['city'] ?? '',
            'postal_code' => $client['postal_code'] ?? '',
            'country' => $client['country'] ?? 'France'
        ]);
        
        $stmt->execute([
            $client_id,
            $product_id,
            $order_number,
            $product['category'],
            json_encode($design_data),
            $nfc_data,
            $quantity,
            $unit_price,
            $total_price,
            $delivery_address
        ]);
        
        $card_id = $db->lastInsertId();
        
        // Créer une activité
        $activity_stmt = $db->prepare("
            INSERT INTO activities (user_type, user_id, type, description) 
            VALUES ('client', ?, 'create', 'Nouvelle carte créée: " . $product['name'] . "')
        ");
        $activity_stmt->execute([$client_id]);
        
        // Rediriger vers la page de confirmation
        $_SESSION['flash_message'] = 'Votre carte a été créée avec succès !';
        $_SESSION['flash_type'] = 'success';
        $_SESSION['new_card_id'] = $card_id;
        
        header('Location: card-confirmation.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une Carte - CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/client.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .creation-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        
        .creation-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            color: #7f8c8d;
            transition: all 0.3s;
        }
        
        .step.active .step-number {
            background: #3498db;
            color: white;
        }
        
        .step.completed .step-number {
            background: #2ecc71;
            color: white;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .step.active .step-label {
            color: #3498db;
            font-weight: 600;
        }
        
        .creation-form {
            display: none;
        }
        
        .creation-form.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
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
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-section-header h3 {
            margin-bottom: 0;
        }
        
        .form-section-header i {
            font-size: 1.5rem;
            color: #3498db;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .card-preview-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .card-preview {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 400px;
            margin: 0 auto;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .card-company {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .card-contact {
            font-size: 1.1rem;
            color: #34495e;
            margin-bottom: 5px;
        }
        
        .card-title {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        
        .card-details {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        
        .card-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .card-detail i {
            color: #3498db;
            width: 20px;
        }
        
        .product-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .product-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .product-card:hover {
            border-color: #3498db;
            transform: translateY(-5px);
        }
        
        .product-card.selected {
            border-color: #3498db;
            background: #f8fafc;
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.1);
        }
        
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .product-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .icon-standard {
            background: #f1c40f;
            color: white;
        }
        
        .icon-nfc {
            background: #9b59b6;
            color: white;
        }
        
        .icon-premium {
            background: #e74c3c;
            color: white;
        }
        
        .product-price {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2ecc71;
        }
        
        .product-features {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .product-features li {
            padding: 5px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .product-features i {
            color: #2ecc71;
        }
        
        .product-duration {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .price-summary {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .price-item.total {
            border-top: 2px solid #3498db;
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
            color: #2c3e50;
            margin-top: 10px;
            padding-top: 15px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .social-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .social-input-group {
            position: relative;
        }
        
        .social-input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }
        
        .social-input-group input {
            padding-left: 45px;
        }
        
        .nfc-features {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .nfc-feature {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .nfc-feature i {
            color: #9b59b6;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .form-tab {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            color: #7f8c8d;
            font-weight: 500;
            position: relative;
        }
        
        .form-tab.active {
            color: #3498db;
        }
        
        .form-tab.active::after {
            content: '';
            position: absolute;
            bottom: -11px;
            left: 0;
            right: 0;
            height: 3px;
            background: #3498db;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="client-dashboard">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../index.html" class="logo">
                <img src="../assets/images/logo.png" alt="CartesVisitePro">
                <span>CartesVisitePro</span>
            </a>
        </div>
        
        <div class="client-info">
            <div class="client-avatar">
                <i class="fas fa-building"></i>
            </div>
            <h3><?php echo htmlspecialchars($_SESSION['client_name']); ?></h3>
            <p><?php echo htmlspecialchars($_SESSION['client_email']); ?></p>
        </div>
        
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li><a href="my-cards.php"><i class="fas fa-id-card"></i> Mes cartes</a></li>
                <li class="active"><a href="create-card.php"><i class="fas fa-plus-circle"></i> Créer une carte</a></li>
                <li><a href="update-card.php"><i class="fas fa-edit"></i> Mettre à jour</a></li>
                <li><a href="upgrade-card.php"><i class="fas fa-level-up-alt"></i> Mettre à niveau</a></li>
                <li><a href="demo.php"><i class="fas fa-play-circle"></i> Démonstration</a></li>
                <li><a href="profile.php"><i class="fas fa-user-cog"></i> Mon profil</a></li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>Créer une nouvelle carte</h1>
                <p>Personnalisez votre carte de visite en quelques étapes</p>
            </div>
            <div class="header-right">
                <a href="my-cards.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </header>

        <!-- Message d'erreur -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Étapes de création -->
        <div class="creation-steps">
            <div class="step active" id="step1">
                <div class="step-number">1</div>
                <div class="step-label">Type de carte</div>
            </div>
            <div class="step" id="step2">
                <div class="step-number">2</div>
                <div class="step-label">Informations</div>
            </div>
            <div class="step" id="step3">
                <div class="step-number">3</div>
                <div class="step-label">Options</div>
            </div>
            <div class="step" id="step4">
                <div class="step-number">4</div>
                <div class="step-label">Confirmation</div>
            </div>
        </div>

        <!-- Formulaire de création -->
        <form method="POST" action="" id="cardCreationForm">
            <!-- Étape 1 : Sélection du type de carte -->
            <div class="creation-form active" id="formStep1">
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-th-large"></i>
                        <h3>Choisissez votre type de carte</h3>
                    </div>
                    
                    <div class="product-cards">
                        <?php foreach ($products_by_category as $category => $category_products): ?>
                            <?php foreach ($category_products as $product): ?>
                                <?php 
                                $features = $product['features'] ? json_decode($product['features'], true) : [];
                                $icons = [
                                    'standard' => 'fa-id-card',
                                    'nfc' => 'fa-wifi',
                                    'premium' => 'fa-crown'
                                ];
                                $icon_classes = [
                                    'standard' => 'icon-standard',
                                    'nfc' => 'icon-nfc',
                                    'premium' => 'icon-premium'
                                ];
                                ?>
                                
                                <div class="product-card" onclick="selectProduct(<?php echo $product['id']; ?>, '<?php echo $product['category']; ?>')" 
                                     id="product-<?php echo $product['id']; ?>">
                                    <div class="product-header">
                                        <div>
                                            <div class="product-icon <?php echo $icon_classes[$product['category']]; ?>">
                                                <i class="fas <?php echo $icons[$product['category']]; ?>"></i>
                                            </div>
                                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                        </div>
                                        <div class="product-price">
                                            <?php echo number_format($product['base_price'], 2, ',', ' '); ?>€
                                        </div>
                                    </div>
                                    
                                    <p><?php echo htmlspecialchars($product['description']); ?></p>
                                    
                                    <?php if (!empty($features)): ?>
                                        <ul class="product-features">
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($feature); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    
                                    <div class="product-duration">
                                        <i class="fas fa-clock"></i> 
                                        Délai de livraison : <?php echo $product['delivery_days']; ?> jours
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="product_id" id="selectedProductId" required>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="window.location.href='my-cards.php'">
                            Annuler
                        </button>
                        <button type="button" class="btn btn-primary" onclick="goToStep(2)" id="nextStep1">
                            Suivant <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Étape 2 : Informations de la carte -->
            <div class="creation-form" id="formStep2">
                <div class="charts-container">
                    <div class="chart-card">
                        <div class="form-section">
                            <div class="form-section-header">
                                <i class="fas fa-info-circle"></i>
                                <h3>Informations de votre carte</h3>
                            </div>
                            
                            <div class="form-tabs">
                                <button type="button" class="form-tab active" onclick="switchTab('tabBasic')">Informations de base</button>
                                <button type="button" class="form-tab" onclick="switchTab('tabSocial')">Réseaux sociaux</button>
                                <button type="button" class="form-tab" onclick="switchTab('tabLogo')">Logo & Visuel</button>
                            </div>
                            
                            <!-- Onglet Informations de base -->
                            <div class="tab-content active" id="tabBasic">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="company_name">Nom de l'entreprise *</label>
                                        <input type="text" id="company_name" name="company_name" 
                                               value="<?php echo htmlspecialchars($client['company_name']); ?>"
                                               required oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="contact_name">Nom du contact *</label>
                                        <input type="text" id="contact_name" name="contact_name" 
                                               value="<?php echo htmlspecialchars($client['contact_person']); ?>"
                                               required oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="title">Fonction / Titre</label>
                                        <input type="text" id="title" name="title" 
                                               placeholder="ex: Directeur Commercial"
                                               oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="phone">Téléphone *</label>
                                        <input type="tel" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($client['phone']); ?>"
                                               required oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">Email *</label>
                                        <input type="email" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($client['email']); ?>"
                                               required oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="website">Site web</label>
                                        <input type="url" id="website" name="website" 
                                               placeholder="https://www.votre-entreprise.com"
                                               oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="address">Adresse</label>
                                        <input type="text" id="address" name="address" 
                                               value="<?php echo htmlspecialchars($client['address'] ?? ''); ?>"
                                               oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label for="tagline">Slogan / Description courte</label>
                                        <input type="text" id="tagline" name="tagline" 
                                               placeholder="ex: Votre partenaire en solutions innovantes"
                                               oninput="updateCardPreview()">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Onglet Réseaux sociaux -->
                            <div class="tab-content" id="tabSocial">
                                <div class="form-grid">
                                    <div class="form-group social-input-group">
                                        <i class="fab fa-linkedin"></i>
                                        <input type="url" id="linkedin" name="linkedin" 
                                               placeholder="https://linkedin.com/in/votre-profil"
                                               oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group social-input-group">
                                        <i class="fab fa-twitter"></i>
                                        <input type="url" id="twitter" name="twitter" 
                                               placeholder="https://twitter.com/votre-compte"
                                               oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group social-input-group">
                                        <i class="fab fa-facebook"></i>
                                        <input type="url" id="facebook" name="facebook" 
                                               placeholder="https://facebook.com/votre-page"
                                               oninput="updateCardPreview()">
                                    </div>
                                    
                                    <div class="form-group social-input-group">
                                        <i class="fab fa-instagram"></i>
                                        <input type="url" id="instagram" name="instagram" 
                                               placeholder="https://instagram.com/votre-compte"
                                               oninput="updateCardPreview()">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Onglet Logo & Visuel -->
                            <div class="tab-content" id="tabLogo">
                                <div class="form-group full-width">
                                    <label>Téléchargez votre logo (optionnel)</label>
                                    <div class="file-upload-area">
                                        <input type="file" id="logo_upload" name="logo_upload" 
                                               accept="image/png, image/jpeg, image/svg+xml" 
                                               onchange="previewLogo(event)">
                                        <div class="upload-placeholder">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p>Glissez-déposez votre logo ici ou cliquez pour sélectionner</p>
                                            <small>Formats acceptés: PNG, JPG, SVG (max 2MB)</small>
                                        </div>
                                        <div id="logoPreview" style="display: none; margin-top: 20px;">
                                            <img id="previewImage" src="" alt="Logo preview" style="max-width: 200px;">
                                            <button type="button" class="btn btn-sm btn-outline" onclick="removeLogo()">
                                                <i class="fas fa-times"></i> Supprimer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="form-section">
                            <div class="form-section-header">
                                <i class="fas fa-eye"></i>
                                <h3>Aperçu de votre carte</h3>
                            </div>
                            
                            <div class="card-preview-section">
                                <div class="card-preview" id="cardPreview">
                                    <div class="card-company" id="previewCompany">Nom de l'entreprise</div>
                                    <div class="card-contact" id="previewContact">Prénom Nom</div>
                                    <div class="card-title" id="previewTitle">Fonction</div>
                                    
                                    <div class="card-details">
                                        <div class="card-detail">
                                            <i class="fas fa-phone"></i>
                                            <span id="previewPhone">+33 1 23 45 67 89</span>
                                        </div>
                                        <div class="card-detail">
                                            <i class="fas fa-envelope"></i>
                                            <span id="previewEmail">contact@entreprise.com</span>
                                        </div>
                                        <div class="card-detail">
                                            <i class="fas fa-globe"></i>
                                            <span id="previewWebsite">www.entreprise.com</span>
                                        </div>
                                        <div class="card-detail">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span id="previewAddress">123 Rue de Paris</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="text-align: center; margin-top: 20px;">
                                    <button type="button" class="btn btn-outline" onclick="rotateCardPreview()">
                                        <i class="fas fa-sync-alt"></i> Voir le verso
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="goToStep(1)">
                        <i class="fas fa-arrow-left"></i> Retour
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                        Suivant <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Étape 3 : Options -->
            <div class="creation-form" id="formStep3">
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-sliders-h"></i>
                        <h3>Options de personnalisation</h3>
                    </div>
                    
                    <!-- Options pour toutes les cartes -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="quantity">Quantité *</label>
                            <select id="quantity" name="quantity" required onchange="updatePrice()">
                                <option value="100">100 cartes</option>
                                <option value="250">250 cartes</option>
                                <option value="500" selected>500 cartes</option>
                                <option value="1000">1000 cartes</option>
                            </select>
                        </div>
                        
                        <!-- Options spécifiques par type -->
                        <div id="standardOptions" style="display: none;">
                            <div class="form-group">
                                <label for="paper_type">Type de papier</label>
                                <select id="paper_type" name="paper_type">
                                    <option value="mat">Mat 300g</option>
                                    <option value="brillant" selected>Brillant 300g</option>
                                    <option value="recycle">Recyclé 300g</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="finish">Finition des coins</label>
                                <select id="finish" name="finish">
                                    <option value="coins_droits">Coins droits</option>
                                    <option value="coins_ronds" selected>Coins arrondis</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="nfcOptions" style="display: none;">
                            <div class="nfc-features">
                                <h4>Options NFC</h4>
                                <div class="nfc-feature">
                                    <i class="fas fa-check"></i>
                                    <span>Redirection vers une URL personnalisée</span>
                                </div>
                                <div class="nfc-feature">
                                    <i class="fas fa-check"></i>
                                    <span>Transmission automatique des coordonnées (vCard)</span>
                                </div>
                                <div class="nfc-feature">
                                    <i class="fas fa-check"></i>
                                    <span>Mise à jour à distance des informations</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="nfc_type">Type de puce NFC</label>
                                <select id="nfc_type" name="nfc_type">
                                    <option value="standard">Standard (NTAG213)</option>
                                    <option value="premium" selected>Premium (NTAG215)</option>
                                    <option value="advanced">Avancé (NTAG216)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="nfc_url">URL de redirection NFC</label>
                                <input type="url" id="nfc_url" name="nfc_url" 
                                       placeholder="https://votre-site.com/contact">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="nfc_vcard" checked>
                                    Inclure les informations en format vCard
                                </label>
                            </div>
                        </div>
                        
                        <div id="premiumOptions" style="display: none;">
                            <div class="form-group">
                                <label for="paper_type_premium">Type de papier</label>
                                <select id="paper_type_premium" name="paper_type">
                                    <option value="mat_400g">Mat 400g</option>
                                    <option value="brillant_400g" selected>Brillant 400g</option>
                                    <option value="recycle_400g">Recyclé 400g</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="special_finish">Finition spéciale</label>
                                <select id="special_finish" name="special_finish" onchange="updatePrice()">
                                    <option value="none">Aucune finition spéciale</option>
                                    <option value="dorure">Dorure à chaud (+20€)</option>
                                    <option value="gaufrage">Gaufrage (+15€)</option>
                                    <option value="vernis">Vernis sélectif (+10€)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="finish_premium">Finition des coins</label>
                                <select id="finish_premium" name="finish">
                                    <option value="coins_droits">Coins droits</option>
                                    <option value="coins_ronds" selected>Coins arrondis</option>
                                    <option value="biseautes">Coins biseautés (+5€)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Récapitulatif du prix -->
                    <div class="price-summary">
                        <h3>Récapitulatif de votre commande</h3>
                        <div class="price-details">
                            <div class="price-item">
                                <span>Type de carte:</span>
                                <span id="priceType">-</span>
                            </div>
                            <div class="price-item">
                                <span>Quantité:</span>
                                <span id="priceQuantity">-</span>
                            </div>
                            <div class="price-item">
                                <span>Options:</span>
                                <span id="priceOptions">Aucune</span>
                            </div>
                            <div class="price-item">
                                <span>Sous-total:</span>
                                <span id="priceSubtotal">-</span>
                            </div>
                            <div class="price-item">
                                <span>TVA (20%):</span>
                                <span id="priceVAT">-</span>
                            </div>
                            <div class="price-item total">
                                <span>Total:</span>
                                <span id="priceTotal">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="goToStep(2)">
                            <i class="fas fa-arrow-left"></i> Retour
                        </button>
                        <button type="button" class="btn btn-primary" onclick="goToStep(4)">
                            Suivant <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Étape 4 : Confirmation -->
            <div class="creation-form" id="formStep4">
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-check-circle"></i>
                        <h3>Confirmation de votre commande</h3>
                    </div>
                    
                    <div class="charts-container">
                        <div class="chart-card">
                            <div style="text-align: center; padding: 40px 20px;">
                                <i class="fas fa-check-circle" style="font-size: 5rem; color: #2ecc71; margin-bottom: 20px;"></i>
                                <h3 style="margin-bottom: 20px;">Votre carte est prête !</h3>
                                <p style="margin-bottom: 30px;">Vérifiez les détails ci-dessous avant de finaliser votre commande.</p>
                                
                                <div class="confirmation-details">
                                    <div class="info-item">
                                        <strong>Type de carte:</strong>
                                        <span id="confirmType">-</span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Quantité:</strong>
                                        <span id="confirmQuantity">-</span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Options sélectionnées:</strong>
                                        <span id="confirmOptions">Aucune</span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Délai estimé:</strong>
                                        <span id="confirmDelivery">-</span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Prix total:</strong>
                                        <span id="confirmPrice" style="font-weight: bold; font-size: 1.2rem;">-</span>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                                    <p><i class="fas fa-info-circle"></i> 
                                    Votre carte sera produite dans les délais indiqués et vous serez notifié à chaque étape du processus.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="goToStep(3)">
                            <i class="fas fa-arrow-left"></i> Modifier
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitForm">
                            <i class="fas fa-check"></i> Confirmer et commander
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../assets/js/client.js"></script>
    <script>
        let currentStep = 1;
        let selectedProduct = null;
        let selectedCategory = '';
        let selectedProductName = '';
        let selectedBasePrice = 0;
        
        function goToStep(step) {
            // Validation de l'étape actuelle
            if (step > currentStep) {
                if (!validateStep(currentStep)) {
                    return;
                }
            }
            
            // Mettre à jour les étapes
            document.querySelectorAll('.step').forEach((s, index) => {
                if (index + 1 < step) {
                    s.classList.remove('active');
                    s.classList.add('completed');
                } else if (index + 1 === step) {
                    s.classList.add('active');
                    s.classList.remove('completed');
                } else {
                    s.classList.remove('active', 'completed');
                }
            });
            
            // Afficher/masquer les formulaires
            document.querySelectorAll('.creation-form').forEach(form => {
                form.classList.remove('active');
            });
            document.getElementById(`formStep${step}`).classList.add('active');
            
            // Mettre à jour le suivi
            currentStep = step;
            
            // Mettre à jour les données si on arrive à l'étape 4
            if (step === 4) {
                updateConfirmation();
            }
        }
        
        function validateStep(step) {
            switch(step) {
                case 1:
                    if (!selectedProduct) {
                        alert('Veuillez sélectionner un type de carte');
                        return false;
                    }
                    return true;
                    
                case 2:
                    const requiredFields = ['company_name', 'contact_name', 'phone', 'email'];
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        const input = document.getElementById(field);
                        if (!input.value.trim()) {
                            isValid = false;
                            input.style.borderColor = '#e74c3c';
                        } else {
                            input.style.borderColor = '';
                        }
                    });
                    
                    if (!isValid) {
                        alert('Veuillez remplir tous les champs obligatoires');
                        return false;
                    }
                    return true;
                    
                case 3:
                    const quantity = document.getElementById('quantity');
                    if (!quantity.value || parseInt(quantity.value) < 1) {
                        alert('Veuillez sélectionner une quantité valide');
                        return false;
                    }
                    return true;
                    
                default:
                    return true;
            }
        }
        
        function selectProduct(productId, category) {
            // Retirer la sélection précédente
            document.querySelectorAll('.product-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Ajouter la sélection actuelle
            const selectedCard = document.getElementById(`product-${productId}`);
            selectedCard.classList.add('selected');
            
            // Mettre à jour les variables
            selectedProduct = productId;
            selectedCategory = category;
            selectedProductName = selectedCard.querySelector('h4').textContent;
            selectedBasePrice = parseFloat(selectedCard.querySelector('.product-price').textContent.replace('€', '').replace(',', '.'));
            
            // Mettre à jour le champ caché
            document.getElementById('selectedProductId').value = productId;
            
            // Activer le bouton suivant
            document.getElementById('nextStep1').disabled = false;
            
            // Mettre à jour les options spécifiques
            updateProductOptions();
        }
        
        function updateProductOptions() {
            // Masquer toutes les options
            document.getElementById('standardOptions').style.display = 'none';
            document.getElementById('nfcOptions').style.display = 'none';
            document.getElementById('premiumOptions').style.display = 'none';
            
            // Afficher les options correspondantes
            if (selectedCategory === 'standard') {
                document.getElementById('standardOptions').style.display = 'block';
            } else if (selectedCategory === 'nfc') {
                document.getElementById('nfcOptions').style.display = 'block';
            } else if (selectedCategory === 'premium') {
                document.getElementById('premiumOptions').style.display = 'block';
            }
            
            // Mettre à jour le prix
            updatePrice();
        }
        
        function updatePrice() {
            if (!selectedProduct) return;
            
            const quantity = parseInt(document.getElementById('quantity').value) || 100;
            let unitPrice = selectedBasePrice;
            let optionsPrice = 0;
            let optionsText = [];
            
            // Calculer les suppléments selon le type
            if (selectedCategory === 'premium') {
                const specialFinish = document.getElementById('special_finish')?.value;
                const finishPremium = document.getElementById('finish_premium')?.value;
                
                if (specialFinish === 'dorure') {
                    optionsPrice += 20;
                    optionsText.push('Dorure à chaud');
                } else if (specialFinish === 'gaufrage') {
                    optionsPrice += 15;
                    optionsText.push('Gaufrage');
                } else if (specialFinish === 'vernis') {
                    optionsPrice += 10;
                    optionsText.push('Vernis sélectif');
                }
                
                if (finishPremium === 'biseautes') {
                    optionsPrice += 5;
                    optionsText.push('Coins biseautés');
                }
            }
            
            // Calcul des prix
            const quantityMultiplier = quantity / 100;
            const subtotal = (unitPrice * quantityMultiplier) + optionsPrice;
            const vat = subtotal * 0.20;
            const total = subtotal + vat;
            
            // Mettre à jour l'affichage
            document.getElementById('priceType').textContent = selectedProductName;
            document.getElementById('priceQuantity').textContent = `${quantity} cartes`;
            document.getElementById('priceOptions').textContent = optionsText.length > 0 ? optionsText.join(', ') : 'Aucune';
            document.getElementById('priceSubtotal').textContent = subtotal.toFixed(2) + '€';
            document.getElementById('priceVAT').textContent = vat.toFixed(2) + '€';
            document.getElementById('priceTotal').textContent = total.toFixed(2) + '€';
        }
        
        function updateCardPreview() {
            // Mettre à jour les informations de l'aperçu
            document.getElementById('previewCompany').textContent = 
                document.getElementById('company_name').value || 'Nom de l\'entreprise';
            
            document.getElementById('previewContact').textContent = 
                document.getElementById('contact_name').value || 'Prénom Nom';
            
            document.getElementById('previewTitle').textContent = 
                document.getElementById('title').value || 'Fonction';
            
            document.getElementById('previewPhone').textContent = 
                document.getElementById('phone').value || '+33 1 23 45 67 89';
            
            document.getElementById('previewEmail').textContent = 
                document.getElementById('email').value || 'contact@entreprise.com';
            
            document.getElementById('previewWebsite').textContent = 
                document.getElementById('website').value || 'www.entreprise.com';
            
            document.getElementById('previewAddress').textContent = 
                document.getElementById('address').value || '123 Rue de Paris';
        }
        
        function updateConfirmation() {
            // Récupérer les informations
            const quantity = document.getElementById('quantity').value;
            const deliveryDays = 5; // À récupérer de la base de données
            
            // Mettre à jour l'affichage de confirmation
            document.getElementById('confirmType').textContent = selectedProductName;
            document.getElementById('confirmQuantity').textContent = `${quantity} cartes`;
            document.getElementById('confirmDelivery').textContent = `${deliveryDays} jours ouvrables`;
            document.getElementById('confirmPrice').textContent = document.getElementById('priceTotal').textContent;
            
            // Récupérer les options
            const options = [];
            if (selectedCategory === 'premium') {
                const specialFinish = document.getElementById('special_finish')?.value;
                if (specialFinish && specialFinish !== 'none') {
                    options.push(specialFinish);
                }
            }
            
            document.getElementById('confirmOptions').textContent = 
                options.length > 0 ? options.join(', ') : 'Aucune';
        }
        
        function switchTab(tabId) {
            // Retirer la classe active de tous les onglets
            document.querySelectorAll('.form-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Ajouter la classe active à l'onglet sélectionné
            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }
        
        function previewLogo(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('previewImage');
            const previewContainer = document.getElementById('logoPreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }
        
        function removeLogo() {
            document.getElementById('logo_upload').value = '';
            document.getElementById('logoPreview').style.display = 'none';
        }
        
        function rotateCardPreview() {
            const preview = document.getElementById('cardPreview');
            preview.classList.toggle('flipped');
        }
        
        // Initialiser l'aperçu
        document.addEventListener('DOMContentLoaded', function() {
            updateCardPreview();
        });
    </script>
</body>
</html>