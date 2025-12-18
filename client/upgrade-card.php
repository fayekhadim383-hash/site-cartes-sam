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

// Récupérer les cartes éligibles pour la mise à niveau
$stmt = $db->prepare("
    SELECT cc.id, p.name, p.category, cc.status 
    FROM client_cards cc 
    JOIN products p ON cc.product_id = p.id 
    WHERE cc.client_id = ? 
    AND cc.status = 'active' 
    AND p.category != 'nfc'
    ORDER BY cc.created_at DESC
");
$stmt->execute([$client_id]);
$upgradable_cards = $stmt->fetchAll();

$card_id = isset($_GET['card']) ? intval($_GET['card']) : 0;
$selected_card = null;
$upgrade_options = [];
$message = '';
$message_type = '';

if ($card_id > 0) {
    // Récupérer les détails de la carte sélectionnée
    $stmt = $db->prepare("
        SELECT cc.*, p.name as product_name, p.category, p.base_price 
        FROM client_cards cc 
        JOIN products p ON cc.product_id = p.id 
        WHERE cc.id = ? AND cc.client_id = ?
    ");
    $stmt->execute([$card_id, $client_id]);
    $selected_card = $stmt->fetch();
    
    if ($selected_card) {
        // Définir les options de mise à niveau disponibles
        $upgrade_options = [
            [
                'from' => $selected_card['category'],
                'to' => 'premium',
                'name' => 'Premium Upgrade',
                'description' => 'Améliorez votre carte standard en version premium',
                'price' => 50.00,
                'features' => ['Papier 400g', 'Impression recto-verso', 'Finition brillante']
            ],
            [
                'from' => $selected_card['category'],
                'to' => 'nfc',
                'name' => 'NFC Upgrade',
                'description' => 'Transformez votre carte en carte NFC intelligente',
                'price' => 100.00,
                'features' => ['Puce NFC intégrée', 'Application mobile', 'Mises à jour en ligne']
            ]
        ];
    }
}

// Traitement du formulaire de demande de mise à niveau
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_upgrade'])) {
    $card_id = intval($_POST['card_id']);
    $from_type = $_POST['from_type'];
    $to_type = $_POST['to_type'];
    $notes = trim($_POST['notes']);
    
    // Vérifier que la carte appartient au client
    $stmt = $db->prepare("SELECT id FROM client_cards WHERE id = ? AND client_id = ?");
    $stmt->execute([$card_id, $client_id]);
    
    if ($stmt->fetch()) {
        try {
            // Créer la demande de mise à niveau
            $stmt = $db->prepare("
                INSERT INTO upgrade_requests 
                (client_id, card_id, from_type, to_type, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$client_id, $card_id, $from_type, $to_type, $notes]);
            
            // Mettre à jour le statut de la carte
            $stmt = $db->prepare("UPDATE client_cards SET status = 'upgrading' WHERE id = ?");
            $stmt->execute([$card_id]);
            
            $message = 'Demande de mise à niveau envoyée avec succès ! Notre équipe vous contactera rapidement.';
            $message_type = 'success';
            
        } catch (PDOException $e) {
            $message = 'Erreur lors de la demande: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Carte non trouvée';
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mettre à niveau - CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/client.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <li><a href="update-card.php"><i class="fas fa-edit"></i> Mettre à jour</a></li>
                <li class="active"><a href="upgrade-card.php"><i class="fas fa-level-up-alt"></i> Mettre à niveau</a></li>
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
                <h1>Mettre à niveau une carte</h1>
                <p>Améliorez vos cartes de visite avec des fonctionnalités avancées</p>
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

        <div class="upgrade-process">
            <!-- Étape 1 : Sélection de la carte -->
            <div class="upgrade-step active" id="step1">
                <div class="step-header">
                    <span class="step-number">1</span>
                    <h2>Sélectionnez une carte à mettre à niveau</h2>
                </div>
                
                <?php if (empty($upgradable_cards)): ?>
                    <div class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <h3>Aucune carte éligible pour la mise à niveau</h3>
                        <p>Toutes vos cartes sont déjà à la version la plus récente ou ne sont pas actives.</p>
                        <a href="my-cards.php?new=true" class="btn btn-primary">Créer une nouvelle carte</a>
                    </div>
                <?php else: ?>
                    <div class="cards-grid-select">
                        <?php foreach ($upgradable_cards as $card): ?>
                            <div class="card-select-item <?php echo ($card['id'] == $card_id) ? 'selected' : ''; ?>" 
                                 onclick="selectCard(<?php echo $card['id']; ?>)">
                                <div class="card-select-header">
                                    <span class="card-type <?php echo $card['category']; ?>">
                                        <?php echo strtoupper($card['category']); ?>
                                    </span>
                                </div>
                                <div class="card-select-body">
                                    <h4><?php echo htmlspecialchars($card['name']); ?></h4>
                                    <p>ID: #<?php echo str_pad($card['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                    <p>Statut: <span class="status status-<?php echo $card['status']; ?>"><?php echo $card['status']; ?></span></p>
                                </div>
                                <div class="card-select-footer">
                                    <button class="btn btn-outline" onclick="event.stopPropagation(); viewCard(<?php echo $card['id']; ?>)">
                                        <i class="fas fa-eye"></i> Voir
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($card_id > 0 && $selected_card): ?>
                        <div class="step-actions">
                            <button class="btn btn-primary" onclick="goToStep(2)">
                                Continuer <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Étape 2 : Choix de l'upgrade -->
            <?php if ($card_id > 0 && $selected_card): ?>
            <div class="upgrade-step" id="step2">
                <div class="step-header">
                    <span class="step-number">2</span>
                    <h2>Choisissez votre mise à niveau</h2>
                    <p class="step-subtitle">
                        Carte sélectionnée: <strong><?php echo htmlspecialchars($selected_card['product_name']); ?></strong>
                        (<?php echo strtoupper($selected_card['category']); ?>)
                    </p>
                </div>
                
                <div class="upgrade-options">
                    <?php foreach ($upgrade_options as $option): ?>
                        <div class="upgrade-option" onclick="selectUpgrade('<?php echo $option['to']; ?>')" 
                             id="option-<?php echo $option['to']; ?>">
                            <div class="option-header">
                                <h3><?php echo $option['name']; ?></h3>
                                <div class="option-price">
                                    <span class="price">+<?php echo number_format($option['price'], 2); ?>€</span>
                                    <span class="price-note">en plus de la carte existante</span>
                                </div>
                            </div>
                            <div class="option-body">
                                <p><?php echo $option['description']; ?></p>
                                <ul class="option-features">
                                    <?php foreach ($option['features'] as $feature): ?>
                                        <li><i class="fas fa-check"></i> <?php echo $feature; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="option-footer">
                                <span class="upgrade-path">
                                    <i class="fas fa-arrow-right"></i>
                                    <?php echo strtoupper($option['from']); ?> → <?php echo strtoupper($option['to']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Option de création de nouvelle carte -->
                <div class="upgrade-option new-card-option" onclick="selectUpgrade('new')" id="option-new">
                    <div class="option-header">
                        <h3>Nouvelle carte NFC</h3>
                        <div class="option-price">
                            <span class="price">149,99€</span>
                            <span class="price-note">commande séparée</span>
                        </div>
                    </div>
                    <div class="option-body">
                        <p>Créez une nouvelle carte NFC sans modifier votre carte existante</p>
                        <ul class="option-features">
                            <li><i class="fas fa-check"></i> Carte NFC complète</li>
                            <li><i class="fas fa-check"></i> Application mobile incluse</li>
                            <li><i class="fas fa-check"></i> Support technique prioritaire</li>
                            <li><i class="fas fa-check"></i> Livraison en 10 jours</li>
                        </ul>
                    </div>
                    <div class="option-footer">
                        <span class="upgrade-path">
                            <i class="fas fa-plus-circle"></i> Nouvelle commande
                        </span>
                    </div>
                </div>
                
                <div class="step-actions">
                    <button class="btn btn-secondary" onclick="goToStep(1)">
                        <i class="fas fa-arrow-left"></i> Retour
                    </button>
                    <button class="btn btn-primary" onclick="goToStep(3)" id="continueToStep3" disabled>
                        Continuer <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Étape 3 : Formulaire de demande -->
            <div class="upgrade-step" id="step3">
                <div class="step-header">
                    <span class="step-number">3</span>
                    <h2>Finalisez votre demande</h2>
                </div>
                
                <form method="POST" action="" id="upgradeForm" class="upgrade-form">
                    <input type="hidden" name="card_id" value="<?php echo $card_id; ?>">
                    <input type="hidden" name="from_type" value="<?php echo $selected_card['category']; ?>">
                    <input type="hidden" name="to_type" id="to_type" value="">
                    <input type="hidden" name="request_upgrade" value="1">
                    
                    <div class="form-group">
                        <label for="upgrade_summary">Récapitulatif</label>
                        <div class="summary-box" id="upgradeSummary">
                            <!-- Rempli par JavaScript -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes additionnelles (optionnel)</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="Informations complémentaires, exigences particulières..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox">
                            <input type="checkbox" name="terms" required>
                            <span>J'accepte les conditions de mise à niveau et comprends qu'il s'agit d'une demande qui sera traitée par notre équipe.</span>
                        </label>
                    </div>
                    
                    <div class="step-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                            <i class="fas fa-arrow-left"></i> Retour
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Envoyer la demande
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let selectedCardId = <?php echo $card_id; ?>;
        let selectedUpgrade = '';
        let upgradeOptions = <?php echo json_encode($upgrade_options); ?>;
        
        function selectCard(cardId) {
            selectedCardId = cardId;
            window.location.href = `upgrade-card.php?card=${cardId}`;
        }
        
        function viewCard(cardId) {
            window.open(`update-card.php?id=${cardId}`, '_blank');
        }
        
        function selectUpgrade(upgradeType) {
            selectedUpgrade = upgradeType;
            
            // Mettre à jour l'apparence des options
            document.querySelectorAll('.upgrade-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelectorAll('.new-card-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            const selectedOption = document.getElementById(`option-${upgradeType}`);
            if (selectedOption) {
                selectedOption.classList.add('selected');
            }
            
            // Activer le bouton continuer
            document.getElementById('continueToStep3').disabled = false;
            
            // Mettre à jour le récapitulatif pour l'étape 3
            updateUpgradeSummary(upgradeType);
        }
        
        function updateUpgradeSummary(upgradeType) {
            const summary = document.getElementById('upgradeSummary');
            const toTypeInput = document.getElementById('to_type');
            
            if (upgradeType === 'new') {
                summary.innerHTML = `
                    <div class="summary-item">
                        <strong>Type:</strong> Nouvelle commande
                    </div>
                    <div class="summary-item">
                        <strong>Produit:</strong> Carte NFC complète
                    </div>
                    <div class="summary-item">
                        <strong>Prix:</strong> 149,99€
                    </div>
                    <div class="summary-item">
                        <strong>Livraison:</strong> 10 jours ouvrés
                    </div>
                `;
                toTypeInput.value = 'nfc';
            } else {
                const option = upgradeOptions.find(opt => opt.to === upgradeType);
                if (option) {
                    summary.innerHTML = `
                        <div class="summary-item">
                            <strong>Mise à niveau:</strong> ${option.from.toUpperCase()} → ${option.to.toUpperCase()}
                        </div>
                        <div class="summary-item">
                            <strong>Description:</strong> ${option.description}
                        </div>
                        <div class="summary-item">
                            <strong>Coût additionnel:</strong> +${option.price.toFixed(2)}€
                        </div>
                        <div class="summary-item">
                            <strong>Fonctionnalités:</strong> ${option.features.join(', ')}
                        </div>
                    `;
                    toTypeInput.value = option.to;
                }
            }
        }
        
        function goToStep(stepNumber) {
            // Cacher toutes les étapes
            document.querySelectorAll('.upgrade-step').forEach(step => {
                step.classList.remove('active');
            });
            
            // Afficher l'étape sélectionnée
            document.getElementById(`step${stepNumber}`).classList.add('active');
            
            // Faire défiler vers le haut
            window.scrollTo(0, 0);
        }
        
        // Initialiser l'étape
        document.addEventListener('DOMContentLoaded', function() {
            if (selectedCardId > 0) {
                // Si une carte est sélectionnée, commencer à l'étape 2
                if (selectedUpgrade) {
                    goToStep(3);
                } else {
                    goToStep(2);
                }
            } else {
                goToStep(1);
            }
        });
    </script>
</body>
</html>