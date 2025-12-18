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

// Récupérer les cartes NFC du client pour démo
$stmt = $db->prepare("
    SELECT cc.*, p.name as product_name 
    FROM client_cards cc 
    JOIN products p ON cc.product_id = p.id 
    WHERE cc.client_id = ? 
    AND p.category = 'nfc'
    AND cc.status = 'active'
    ORDER BY cc.created_at DESC
");
$stmt->execute([$client_id]);
$nfc_cards = $stmt->fetchAll();

// Récupérer les démos déjà demandées
$stmt = $db->prepare("
    SELECT d.* 
    FROM demos d 
    WHERE d.client_id = ? 
    ORDER BY d.requested_date DESC
");
$stmt->execute([$client_id]);
$requested_demos = $stmt->fetchAll();

$message = '';
$message_type = '';

// Traitement du formulaire de demande de démo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_demo'])) {
    $card_type = $_POST['card_type'];
    $requested_date = $_POST['requested_date'];
    $demo_type = $_POST['demo_type'];
    $notes = trim($_POST['notes']);
    
    // Validation
    $min_date = date('Y-m-d', strtotime('+2 days'));
    if ($requested_date < $min_date) {
        $message = 'La date de démonstration doit être au moins 2 jours à l\'avance';
        $message_type = 'error';
    } else {
        try {
            // Créer la demande de démo
            $stmt = $db->prepare("
                INSERT INTO demos 
                (client_id, card_type, requested_date, demo_type, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$client_id, $card_type, $requested_date, $demo_type, $notes]);
            
            $message = 'Demande de démonstration envoyée avec succès ! Notre équipe vous contactera pour confirmer.';
            $message_type = 'success';
            
            // Recharger les démos demandées
            $stmt = $db->prepare("
                SELECT d.* 
                FROM demos d 
                WHERE d.client_id = ? 
                ORDER BY d.requested_date DESC
            ");
            $stmt->execute([$client_id]);
            $requested_demos = $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $message = 'Erreur lors de la demande: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Traitement de la simulation NFC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_nfc'])) {
    $card_id = intval($_POST['card_id']);
    
    // Récupérer les données NFC de la carte
    $stmt = $db->prepare("SELECT nfc_data FROM client_cards WHERE id = ? AND client_id = ?");
    $stmt->execute([$card_id, $client_id]);
    $nfc_data = $stmt->fetchColumn();
    
    $simulation_result = $nfc_data ? json_decode($nfc_data, true) : null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Démonstration - CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/client.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <li><a href="upgrade-card.php"><i class="fas fa-level-up-alt"></i> Mettre à niveau</a></li>
                <li class="active"><a href="demo.php"><i class="fas fa-play-circle"></i> Démonstration</a></li>
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
                <h1>Démonstrations</h1>
                <p>Testez et découvrez les fonctionnalités de vos cartes NFC</p>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="demo-sections">
            <!-- Section 1 : Simulation NFC -->
            <div class="demo-section">
                <div class="section-header">
                    <h2><i class="fas fa-mobile-alt"></i> Simulation NFC</h2>
                    <p>Testez le fonctionnement de vos cartes NFC en ligne</p>
                </div>
                
                <?php if (empty($nfc_cards)): ?>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <h3>Vous n'avez pas encore de cartes NFC</h3>
                        <p>Pour utiliser la simulation NFC, vous devez avoir au moins une carte NFC active.</p>
                        <div class="info-actions">
                            <a href="upgrade-card.php" class="btn btn-primary">
                                <i class="fas fa-level-up-alt"></i> Mettre à niveau vers NFC
                            </a>
                            <a href="my-cards.php?new=true" class="btn btn-secondary">
                                <i class="fas fa-plus"></i> Commander une nouvelle carte
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="nfc-simulator">
                        <div class="simulator-controls">
                            <form method="POST" action="" id="nfcForm">
                                <div class="form-group">
                                    <label for="card_id">Sélectionnez une carte NFC</label>
                                    <select id="card_id" name="card_id" required>
                                        <option value="">Choisissez une carte...</option>
                                        <?php foreach ($nfc_cards as $card): ?>
                                            <option value="<?php echo $card['id']; ?>">
                                                <?php echo htmlspecialchars($card['product_name']); ?> - ID: #<?php echo str_pad($card['id'], 6, '0', STR_PAD_LEFT); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Mode de simulation</label>
                                    <div class="simulation-modes">
                                        <label class="radio">
                                            <input type="radio" name="simulation_mode" value="tap" checked>
                                            <span><i class="fas fa-hand-point-up"></i> Tap simulation</span>
                                        </label>
                                        <label class="radio">
                                            <input type="radio" name="simulation_mode" value="hover">
                                            <span><i class="fas fa-hand-pointer"></i> Hover simulation</span>
                                        </label>
                                        <label class="radio">
                                            <input type="radio" name="simulation_mode" value="auto">
                                            <span><i class="fas fa-robot"></i> Auto-détection</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" name="simulate_nfc" class="btn btn-primary">
                                    <i class="fas fa-play"></i> Lancer la simulation
                                </button>
                            </form>
                        </div>
                        
                        <div class="simulator-display">
                            <div class="phone-mockup">
                                <div class="phone-header">
                                    <div class="phone-notch"></div>
                                </div>
                                <div class="phone-screen" id="phoneScreen">
                                    <div class="nfc-scan-animation">
                                        <div class="nfc-icon">
                                            <i class="fas fa-wifi"></i>
                                        </div>
                                        <p>Prêt à scanner</p>
                                        <p class="scan-instruction">Appuyez sur le bouton pour simuler le scan</p>
                                    </div>
                                </div>
                                <div class="phone-footer">
                                    <button class="nfc-scan-btn" onclick="scanNFCCard()">
                                        <i class="fas fa-hand-point-up"></i> Scanner
                                    </button>
                                </div>
                            </div>
                            
                            <div class="simulation-result" id="simulationResult">
                                <?php if (isset($simulation_result)): ?>
                                    <h3>Résultat du scan</h3>
                                    <div class="result-content">
                                        <?php if ($simulation_result && isset($simulation_result['actions'])): ?>
                                            <?php if (!empty($simulation_result['actions']['website'])): ?>
                                                <div class="result-item">
                                                    <i class="fas fa-globe"></i>
                                                    <div>
                                                        <strong>Site web</strong>
                                                        <p><a href="<?php echo htmlspecialchars($simulation_result['actions']['website']); ?>" target="_blank">
                                                            <?php echo htmlspecialchars($simulation_result['actions']['website']); ?>
                                                        </a></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($simulation_result['actions']['email'])): ?>
                                                <div class="result-item">
                                                    <i class="fas fa-envelope"></i>
                                                    <div>
                                                        <strong>Email</strong>
                                                        <p><a href="mailto:<?php echo htmlspecialchars($simulation_result['actions']['email']); ?>">
                                                            <?php echo htmlspecialchars($simulation_result['actions']['email']); ?>
                                                        </a></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($simulation_result['actions']['phone'])): ?>
                                                <div class="result-item">
                                                    <i class="fas fa-phone"></i>
                                                    <div>
                                                        <strong>Téléphone</strong>
                                                        <p><a href="tel:<?php echo htmlspecialchars($simulation_result['actions']['phone']); ?>">
                                                            <?php echo htmlspecialchars($simulation_result['actions']['phone']); ?>
                                                        </a></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($simulation_result['actions']['vcard']) && $simulation_result['actions']['vcard']): ?>
                                                <div class="result-item">
                                                    <i class="fas fa-address-card"></i>
                                                    <div>
                                                        <strong>vCard</strong>
                                                        <p>Contact ajouté au carnet d'adresses</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($simulation_result['actions']['wifi'])): ?>
                                                <div class="result-item">
                                                    <i class="fas fa-wifi"></i>
                                                    <div>
                                                        <strong>Wi-Fi</strong>
                                                        <p>Réseau: <?php echo htmlspecialchars($simulation_result['actions']['wifi']['ssid']); ?></p>
                                                        <p>Connecté automatiquement</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="result-empty">
                                                <i class="fas fa-exclamation-circle"></i>
                                                <p>Aucune action NFC configurée pour cette carte</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <h3>Résultat</h3>
                                    <div class="result-placeholder">
                                        <i class="fas fa-wifi"></i>
                                        <p>Les résultats de simulation apparaîtront ici</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="nfc-stats">
                        <h3>Statistiques de simulation</h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo count($nfc_cards); ?></div>
                                <div class="stat-label">Cartes NFC</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">24</div>
                                <div class="stat-label">Scans simulés</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">98%</div>
                                <div class="stat-label">Taux de succès</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">0.5s</div>
                                <div class="stat-label">Temps de réponse moyen</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 2 : Demande de démonstration -->
            <div class="demo-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-check"></i> Demander une démonstration</h2>
                    <p>Organisez une démonstration en personne ou en ligne avec notre équipe</p>
                </div>
                
                <div class="demo-request">
                    <div class="request-info">
                        <h3>Pourquoi demander une démo ?</h3>
                        <ul class="benefits-list">
                            <li><i class="fas fa-check"></i> Découverte des fonctionnalités avancées</li>
                            <li><i class="fas fa-check"></i> Assistance à la configuration</li>
                            <li><i class="fas fa-check"></i> Réponses à vos questions techniques</li>
                            <li><i class="fas fa-check"></i> Démonstration avec vos propres cartes</li>
                            <li><i class="fas fa-check"></i> Conseils personnalisés d'utilisation</li>
                        </ul>
                    </div>
                    
                    <div class="request-form">
                        <form method="POST" action="" id="demoRequestForm">
                            <div class="form-group">
                                <label for="card_type">Type de carte à démontrer *</label>
                                <select id="card_type" name="card_type" required>
                                    <option value="">Sélectionnez un type</option>
                                    <option value="standard">Carte Standard</option>
                                    <option value="premium">Carte Premium</option>
                                    <option value="nfc">Carte NFC</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="demo_type">Format de démonstration *</label>
                                <select id="demo_type" name="demo_type" required>
                                    <option value="">Choisissez un format</option>
                                    <option value="online">Visio-conférence</option>
                                    <option value="in_person">En personne (sur site)</option>
                                    <option value="phone">Appel téléphonique</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="requested_date">Date souhaitée *</label>
                                <input type="date" id="requested_date" name="requested_date" 
                                       min="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" 
                                       required>
                                <small>Au moins 2 jours à l'avance</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Informations complémentaires</label>
                                <textarea id="notes" name="notes" rows="3" placeholder="Objectifs de la démo, questions spécifiques..."></textarea>
                            </div>
                            
                            <button type="submit" name="request_demo" class="btn btn-primary btn-block">
                                <i class="fas fa-paper-plane"></i> Envoyer la demande
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Section 3 : Démonstrations demandées -->
            <div class="demo-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Vos demandes de démonstration</h2>
                </div>
                
                <?php if (empty($requested_demos)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Aucune démonstration demandée</h3>
                        <p>Vous n'avez pas encore demandé de démonstration</p>
                    </div>
                <?php else: ?>
                    <div class="demo-requests-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date demande</th>
                                    <th>Date souhaitée</th>
                                    <th>Type</th>
                                    <th>Format</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requested_demos as $demo): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($demo['created_at'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($demo['requested_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $demo['card_type']; ?>">
                                                <?php echo strtoupper($demo['card_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $format_labels = [
                                                'online' => 'Visio',
                                                'in_person' => 'Sur site',
                                                'phone' => 'Téléphone'
                                            ];
                                            echo $format_labels[$demo['demo_type']] ?? $demo['demo_type'];
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status status-<?php echo $demo['status']; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'pending' => 'En attente',
                                                    'scheduled' => 'Planifiée',
                                                    'completed' => 'Terminée',
                                                    'cancelled' => 'Annulée'
                                                ];
                                                echo $status_labels[$demo['status']] ?? $demo['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($demo['status'] === 'scheduled'): ?>
                                                    <button class="btn-action" title="Rejoindre" onclick="joinDemo(<?php echo $demo['id']; ?>)">
                                                        <i class="fas fa-video"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (in_array($demo['status'], ['pending', 'scheduled'])): ?>
                                                    <button class="btn-action" title="Annuler" onclick="cancelDemo(<?php echo $demo['id']; ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn-action" title="Détails" onclick="showDemoDetails(<?php echo $demo['id']; ?>)">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 4 : Tutoriels vidéo -->
            <div class="demo-section">
                <div class="section-header">
                    <h2><i class="fas fa-play-circle"></i> Tutoriels vidéo</h2>
                    <p>Apprenez à utiliser vos cartes NFC avec nos guides vidéo</p>
                </div>
                
                <div class="video-tutorials">
                    <div class="video-card">
                        <div class="video-thumbnail">
                            <i class="fas fa-play"></i>
                            <img src="../assets/images/tutorials/nfc-basics.jpg" alt="Fonctionnement NFC">
                        </div>
                        <div class="video-info">
                            <h3>Fonctionnement des cartes NFC</h3>
                            <p>Découvrez comment fonctionne la technologie NFC et comment l'utiliser au quotidien.</p>
                            <div class="video-meta">
                                <span><i class="fas fa-clock"></i> 5:23</span>
                                <span><i class="fas fa-eye"></i> 1,245 vues</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="video-card">
                        <div class="video-thumbnail">
                            <i class="fas fa-play"></i>
                            <img src="../assets/images/tutorials/configuration.jpg" alt="Configuration">
                        </div>
                        <div class="video-info">
                            <h3>Configuration de votre carte NFC</h3>
                            <p>Guide complet pour configurer les actions de votre carte via l'interface client.</p>
                            <div class="video-meta">
                                <span><i class="fas fa-clock"></i> 8:45</span>
                                <span><i class="fas fa-eye"></i> 892 vues</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="video-card">
                        <div class="video-thumbnail">
                            <i class="fas fa-play"></i>
                            <img src="../assets/images/tutorials/tips.jpg" alt="Astuces">
                        </div>
                        <div class="video-info">
                            <h3>Astuces et bonnes pratiques</h3>
                            <p>Maximisez l'impact de vos cartes NFC avec nos conseils d'experts.</p>
                            <div class="video-meta">
                                <span><i class="fas fa-clock"></i> 6:12</span>
                                <span><i class="fas fa-eye"></i> 1,543 vues</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function scanNFCCard() {
            const cardId = document.getElementById('card_id').value;
            if (!cardId) {
                alert('Veuillez sélectionner une carte NFC');
                return;
            }
            
            // Simulation d'animation de scan
            const screen = document.getElementById('phoneScreen');
            screen.innerHTML = `
                <div class="nfc-scanning">
                    <div class="scanning-animation">
                        <div class="scanning-line"></div>
                        <i class="fas fa-wifi"></i>
                    </div>
                    <p>Scan en cours...</p>
                </div>
            `;
            
            // Simuler un délai de scan
            setTimeout(() => {
                // Envoyer le formulaire pour récupérer les données
                document.getElementById('nfcForm').submit();
            }, 2000);
        }
        
        function joinDemo(demoId) {
            alert(`Rejoindre la démonstration #${demoId}`);
            // Implémenter la logique de connexion à la visio
        }
        
        function cancelDemo(demoId) {
            if (confirm('Êtes-vous sûr de vouloir annuler cette démonstration ?')) {
                // Envoyer une requête AJAX pour annuler
                fetch(`../api/client/cancel-demo.php?id=${demoId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue');
                });
            }
        }
        
        function showDemoDetails(demoId) {
            // Implémenter l'affichage des détails
            alert(`Détails de la démonstration #${demoId}`);
        }
        
        // Initialiser la date minimale pour le formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const minDate = new Date(today);
            minDate.setDate(today.getDate() + 2);
            
            const formattedDate = minDate.toISOString().split('T')[0];
            const dateInput = document.getElementById('requested_date');
            if (dateInput) {
                dateInput.min = formattedDate;
            }
        });
    </script>
</body>
</html>