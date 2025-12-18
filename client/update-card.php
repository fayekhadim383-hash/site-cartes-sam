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

$card_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$card = null;

if ($card_id > 0) {
    // Récupérer les détails de la carte
    $stmt = $db->prepare("
        SELECT cc.*, p.name as product_name, p.category 
        FROM client_cards cc 
        JOIN products p ON cc.product_id = p.id 
        WHERE cc.id = ? AND cc.client_id = ?
    ");
    $stmt->execute([$card_id, $client_id]);
    $card = $stmt->fetch();
    
    if (!$card) {
        header('Location: my-cards.php');
        exit();
    }
}

// Récupérer toutes les cartes du client pour le sélecteur
$stmt = $db->prepare("
    SELECT cc.id, p.name, p.category, cc.status 
    FROM client_cards cc 
    JOIN products p ON cc.product_id = p.id 
    WHERE cc.client_id = ? 
    ORDER BY cc.created_at DESC
");
$stmt->execute([$client_id]);
$all_cards = $stmt->fetchAll();

$message = '';
$message_type = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_id = intval($_POST['card_id']);
    $update_type = $_POST['update_type'];
    
    // Vérifier que la carte appartient au client
    $stmt = $db->prepare("SELECT id FROM client_cards WHERE id = ? AND client_id = ?");
    $stmt->execute([$card_id, $client_id]);
    
    if ($stmt->fetch()) {
        try {
            if ($update_type === 'contact_info') {
                // Mise à jour des informations de contact
                $design_data = json_encode([
                    'name' => trim($_POST['contact_name']),
                    'title' => trim($_POST['contact_title']),
                    'email' => trim($_POST['contact_email']),
                    'phone' => trim($_POST['contact_phone']),
                    'website' => trim($_POST['contact_website']),
                    'address' => trim($_POST['contact_address'])
                ]);
                
                $stmt = $db->prepare("UPDATE client_cards SET design_data = ? WHERE id = ?");
                $stmt->execute([$design_data, $card_id]);
                
                $message = 'Informations de contact mises à jour avec succès';
                $message_type = 'success';
                
            } elseif ($update_type === 'design') {
                // Mise à jour du design
                $design_data = json_encode([
                    'logo' => $_POST['design_logo'] ?? '',
                    'primary_color' => $_POST['design_primary_color'],
                    'secondary_color' => $_POST['design_secondary_color'],
                    'font_family' => $_POST['design_font'],
                    'layout' => $_POST['design_layout']
                ]);
                
                $stmt = $db->prepare("UPDATE client_cards SET design_data = ? WHERE id = ?");
                $stmt->execute([$design_data, $card_id]);
                
                $message = 'Design mis à jour avec succès';
                $message_type = 'success';
                
            } elseif ($update_type === 'nfc_content') {
                // Mise à jour du contenu NFC
                $nfc_data = json_encode([
                    'actions' => [
                        'website' => trim($_POST['nfc_website']),
                        'email' => trim($_POST['nfc_email']),
                        'phone' => trim($_POST['nfc_phone']),
                        'vcard' => isset($_POST['nfc_vcard']),
                        'wifi' => isset($_POST['nfc_wifi']) ? [
                            'ssid' => trim($_POST['wifi_ssid']),
                            'password' => trim($_POST['wifi_password'])
                        ] : null
                    ]
                ]);
                
                $stmt = $db->prepare("UPDATE client_cards SET nfc_data = ? WHERE id = ?");
                $stmt->execute([$nfc_data, $card_id]);
                
                $message = 'Contenu NFC mis à jour avec succès';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Erreur lors de la mise à jour: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Carte non trouvée';
        $message_type = 'error';
    }
    
    // Recharger les données de la carte
    if ($card_id > 0) {
        $stmt = $db->prepare("
            SELECT cc.*, p.name as product_name, p.category 
            FROM client_cards cc 
            JOIN products p ON cc.product_id = p.id 
            WHERE cc.id = ? AND cc.client_id = ?
        ");
        $stmt->execute([$card_id, $client_id]);
        $card = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mettre à jour - CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/client.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css">
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
                <li class="active"><a href="update-card.php"><i class="fas fa-edit"></i> Mettre à jour</a></li>
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
                <h1>Mettre à jour une carte</h1>
                <p>Modifiez les informations de vos cartes de visite</p>
            </div>
            <div class="header-right">
                <a href="my-cards.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour aux cartes
                </a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Card Selector -->
        <div class="card-selector">
            <div class="selector-header">
                <h2>Sélectionnez une carte</h2>
                <?php if ($card): ?>
                    <span class="selected-card">Sélectionnée: <?php echo htmlspecialchars($card['product_name']); ?> (<?php echo strtoupper($card['category']); ?>)</span>
                <?php endif; ?>
            </div>
            <form method="GET" action="" class="selector-form">
                <div class="form-group">
                    <select name="id" onchange="this.form.submit()" required>
                        <option value="">Sélectionnez une carte...</option>
                        <?php foreach ($all_cards as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $card_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?> (<?php echo strtoupper($c['category']); ?>) - <?php echo $c['status']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($card): ?>
            <!-- Update Tabs -->
            <div class="update-tabs">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="contact">Informations de contact</button>
                    <button class="tab-btn" data-tab="design">Design</button>
                    <?php if ($card['category'] === 'nfc'): ?>
                        <button class="tab-btn" data-tab="nfc">Contenu NFC</button>
                    <?php endif; ?>
                    <button class="tab-btn" data-tab="preview">Aperçu</button>
                </div>

                <!-- Contact Info Tab -->
                <div class="tab-content active" id="contact-tab">
                    <form method="POST" action="">
                        <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                        <input type="hidden" name="update_type" value="contact_info">
                        
                        <?php
                        $design_data = $card['design_data'] ? json_decode($card['design_data'], true) : [];
                        ?>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="contact_name">Nom complet *</label>
                                <input type="text" id="contact_name" name="contact_name" 
                                       value="<?php echo htmlspecialchars($design_data['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_title">Poste/Fonction *</label>
                                <input type="text" id="contact_title" name="contact_title" 
                                       value="<?php echo htmlspecialchars($design_data['title'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_email">Email *</label>
                                <input type="email" id="contact_email" name="contact_email" 
                                       value="<?php echo htmlspecialchars($design_data['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_phone">Téléphone *</label>
                                <input type="tel" id="contact_phone" name="contact_phone" 
                                       value="<?php echo htmlspecialchars($design_data['phone'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_website">Site web</label>
                                <input type="url" id="contact_website" name="contact_website" 
                                       value="<?php echo htmlspecialchars($design_data['website'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="contact_address">Adresse</label>
                                <textarea id="contact_address" name="contact_address"><?php echo htmlspecialchars($design_data['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Design Tab -->
                <div class="tab-content" id="design-tab">
                    <form method="POST" action="">
                        <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                        <input type="hidden" name="update_type" value="design">
                        
                        <div class="design-editor">
                            <div class="design-preview">
                                <h3>Aperçu du design</h3>
                                <div class="card-preview" id="cardPreview">
                                    <!-- Preview généré par JavaScript -->
                                </div>
                            </div>
                            
                            <div class="design-controls">
                                <div class="form-group">
                                    <label for="design_layout">Mise en page</label>
                                    <select id="design_layout" name="design_layout">
                                        <option value="classic">Classique</option>
                                        <option value="modern">Moderne</option>
                                        <option value="minimalist">Minimaliste</option>
                                        <option value="creative">Créatif</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="design_font">Police de caractère</label>
                                    <select id="design_font" name="design_font">
                                        <option value="Arial">Arial</option>
                                        <option value="Helvetica">Helvetica</option>
                                        <option value="Times New Roman">Times New Roman</option>
                                        <option value="Georgia">Georgia</option>
                                        <option value="Montserrat">Montserrat</option>
                                        <option value="Open Sans">Open Sans</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="design_primary_color">Couleur principale</label>
                                    <input type="text" id="design_primary_color" name="design_primary_color" 
                                           class="color-picker" value="<?php echo htmlspecialchars($design_data['primary_color'] ?? '#2c3e50'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="design_secondary_color">Couleur secondaire</label>
                                    <input type="text" id="design_secondary_color" name="design_secondary_color" 
                                           class="color-picker" value="<?php echo htmlspecialchars($design_data['secondary_color'] ?? '#3498db'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="logo_upload">Logo (optionnel)</label>
                                    <div class="upload-area" id="logoUpload">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Glissez-déposez votre logo ou cliquez pour sélectionner</p>
                                        <input type="file" id="logo_file" accept="image/*" style="display: none;">
                                        <input type="hidden" id="design_logo" name="design_logo" value="<?php echo htmlspecialchars($design_data['logo'] ?? ''); ?>">
                                    </div>
                                    <div class="upload-preview" id="logoPreview" style="<?php echo isset($design_data['logo']) ? 'display: block;' : 'display: none;'; ?>">
                                        <img src="<?php echo htmlspecialchars($design_data['logo'] ?? ''); ?>" alt="Logo">
                                        <button type="button" onclick="removeLogo()">×</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer le design
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($card['category'] === 'nfc'): ?>
                <!-- NFC Content Tab -->
                <div class="tab-content" id="nfc-tab">
                    <form method="POST" action="">
                        <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                        <input type="hidden" name="update_type" value="nfc_content">
                        
                        <?php
                        $nfc_data = $card['nfc_data'] ? json_decode($card['nfc_data'], true) : ['actions' => []];
                        $actions = $nfc_data['actions'] ?? [];
                        ?>
                        
                        <div class="nfc-actions">
                            <h3>Actions NFC configurées</h3>
                            <p>Définissez les actions qui seront déclenchées lorsque votre carte est scannée</p>
                            
                            <div class="action-item">
                                <div class="action-header">
                                    <label class="checkbox">
                                        <input type="checkbox" name="nfc_website_enabled" checked>
                                        <span>Site web</span>
                                    </label>
                                </div>
                                <div class="action-content">
                                    <input type="url" name="nfc_website" placeholder="https://www.votresite.com" 
                                           value="<?php echo htmlspecialchars($actions['website'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="action-item">
                                <div class="action-header">
                                    <label class="checkbox">
                                        <input type="checkbox" name="nfc_email_enabled" checked>
                                        <span>Email</span>
                                    </label>
                                </div>
                                <div class="action-content">
                                    <input type="email" name="nfc_email" placeholder="contact@entreprise.com" 
                                           value="<?php echo htmlspecialchars($actions['email'] ?? ''); ?>">
                                    <div class="form-group">
                                        <label for="email_subject">Sujet (optionnel)</label>
                                        <input type="text" id="email_subject" name="email_subject" placeholder="Demande d'information">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="action-item">
                                <div class="action-header">
                                    <label class="checkbox">
                                        <input type="checkbox" name="nfc_phone_enabled" checked>
                                        <span>Téléphone</span>
                                    </label>
                                </div>
                                <div class="action-content">
                                    <input type="tel" name="nfc_phone" placeholder="+33 1 23 45 67 89" 
                                           value="<?php echo htmlspecialchars($actions['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="action-item">
                                <div class="action-header">
                                    <label class="checkbox">
                                        <input type="checkbox" name="nfc_vcard" <?php echo isset($actions['vcard']) && $actions['vcard'] ? 'checked' : ''; ?>>
                                        <span>vCard (carte de contact numérique)</span>
                                    </label>
                                </div>
                                <div class="action-content">
                                    <p>Ajoute automatiquement vos informations de contact dans le carnet d'adresses</p>
                                </div>
                            </div>
                            
                            <div class="action-item">
                                <div class="action-header">
                                    <label class="checkbox">
                                        <input type="checkbox" name="nfc_wifi" <?php echo isset($actions['wifi']) ? 'checked' : ''; ?>>
                                        <span>Connexion Wi-Fi</span>
                                    </label>
                                </div>
                                <div class="action-content">
                                    <div class="form-group">
                                        <label for="wifi_ssid">Nom du réseau (SSID)</label>
                                        <input type="text" id="wifi_ssid" name="wifi_ssid" 
                                               value="<?php echo htmlspecialchars($actions['wifi']['ssid'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="wifi_password">Mot de passe</label>
                                        <input type="text" id="wifi_password" name="wifi_password" 
                                               value="<?php echo htmlspecialchars($actions['wifi']['password'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="wifi_encryption">Type de sécurité</label>
                                        <select id="wifi_encryption" name="wifi_encryption">
                                            <option value="WPA">WPA/WPA2</option>
                                            <option value="WEP">WEP</option>
                                            <option value="none">Aucune</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Mettre à jour les actions NFC
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Preview Tab -->
                <div class="tab-content" id="preview-tab">
                    <div class="preview-container">
                        <h3>Aperçu en direct</h3>
                        <div class="live-preview" id="livePreview">
                            <!-- Aperçu généré dynamiquement -->
                        </div>
                        <div class="preview-actions">
                            <button class="btn btn-primary" onclick="downloadPreview()">
                                <i class="fas fa-download"></i> Télécharger l'aperçu
                            </button>
                            <button class="btn btn-secondary" onclick="sharePreview()">
                                <i class="fas fa-share"></i> Partager
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-id-card"></i>
                <h3>Sélectionnez une carte pour commencer</h3>
                <p>Choisissez une carte dans la liste déroulante ci-dessus pour la modifier</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.js"></script>
    <script src="../assets/js/client.js"></script>
    <script>
        $(document).ready(function() {
            // Initialiser les sélecteurs de couleur
            $(".color-picker").spectrum({
                preferredFormat: "hex",
                showInput: true,
                showPalette: true,
                palette: [
                    ["#2c3e50", "#3498db", "#9b59b6", "#1abc9c"],
                    ["#e74c3c", "#f39c12", "#f1c40f", "#2ecc71"],
                    ["#ffffff", "#ecf0f1", "#bdc3c7", "#95a5a6"]
                ]
            });
            
            // Gestion des onglets
            $(".tab-btn").click(function() {
                const tab = $(this).data("tab");
                
                // Mettre à jour les boutons d'onglets
                $(".tab-btn").removeClass("active");
                $(this).addClass("active");
                
                // Afficher le contenu correspondant
                $(".tab-content").removeClass("active");
                $(`#${tab}-tab`).addClass("active");
                
                // Mettre à jour l'aperçu si nécessaire
                if (tab === 'preview') {
                    updateLivePreview();
                }
            });
            
            // Mettre à jour l'aperçu en temps réel
            $("#design_layout, #design_font, #design_primary_color, #design_secondary_color").on("change input", function() {
                updateCardPreview();
            });
        });
        
        function updateCardPreview() {
            const layout = $("#design_layout").val();
            const font = $("#design_font").val();
            const primaryColor = $("#design_primary_color").val() || "#2c3e50";
            const secondaryColor = $("#design_secondary_color").val() || "#3498db";
            const logo = $("#design_logo").val();
            
            const preview = $("#cardPreview");
            preview.css({
                'font-family': font,
                'background': `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`,
                'color': '#ffffff'
            });
            
            preview.html(`
                <div class="preview-logo">
                    ${logo ? `<img src="${logo}" alt="Logo">` : '<div class="logo-placeholder"><i class="fas fa-building"></i></div>'}
                </div>
                <div class="preview-content">
                    <h4>Jean Dupont</h4>
                    <p>Directeur Commercial</p>
                    <p>jean.dupont@entreprise.com</p>
                    <p>+33 1 23 45 67 89</p>
                </div>
            `);
        }
        
        function updateLivePreview() {
            // Récupérer toutes les données du formulaire
            const name = $("#contact_name").val() || "Jean Dupont";
            const title = $("#contact_title").val() || "Directeur Commercial";
            const email = $("#contact_email").val() || "contact@entreprise.com";
            const phone = $("#contact_phone").val() || "+33 1 23 45 67 89";
            const website = $("#contact_website").val() || "www.entreprise.com";
            const layout = $("#design_layout").val();
            const font = $("#design_font").val();
            const primaryColor = $("#design_primary_color").val() || "#2c3e50";
            const secondaryColor = $("#design_secondary_color").val() || "#3498db";
            const logo = $("#design_logo").val();
            
            const preview = $("#livePreview");
            preview.css({
                'font-family': font
            });
            
            preview.html(`
                <div class="card-preview-live" style="background: linear-gradient(135deg, ${primaryColor}, ${secondaryColor}); color: #ffffff;">
                    <div class="card-header">
                        ${logo ? `<img src="${logo}" alt="Logo" class="card-logo">` : '<div class="card-logo-placeholder"><i class="fas fa-building"></i></div>'}
                    </div>
                    <div class="card-body">
                        <h3>${name}</h3>
                        <p class="card-title">${title}</p>
                        <div class="card-contact">
                            <p><i class="fas fa-envelope"></i> ${email}</p>
                            <p><i class="fas fa-phone"></i> ${phone}</p>
                            ${website ? `<p><i class="fas fa-globe"></i> ${website}</p>` : ''}
                        </div>
                    </div>
                    <div class="card-footer">
                        <p>Scannez cette carte NFC pour plus d'informations</p>
                    </div>
                </div>
            `);
        }
        
        function downloadPreview() {
            alert("Fonctionnalité de téléchargement à implémenter");
        }
        
        function sharePreview() {
            alert("Fonctionnalité de partage à implémenter");
        }
        
        function removeLogo() {
            $("#design_logo").val("");
            $("#logoPreview").hide();
        }
    </script>
</body>
</html>