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

// Récupérer les cartes du client
$stmt = $db->prepare("
    SELECT cc.*, p.name as product_name, p.category, p.base_price
    FROM client_cards cc 
    JOIN products p ON cc.product_id = p.id 
    WHERE cc.client_id = ? 
    ORDER BY cc.created_at DESC
");
$stmt->execute([$client_id]);
$cards = $stmt->fetchAll();

// Compter les cartes par type
$card_types = ['standard' => 0, 'nfc' => 0, 'premium' => 0];
foreach ($cards as $card) {
    $card_types[$card['category']]++;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Cartes - SamCard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/client.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
</head>
<body class="client-dashboard">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../index.html" class="logo">
                <img src="../assets/images/logo.png" alt="CartesVisitePro">
                <span>SamCard</span>
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
                <li class="active"><a href="my-cards.php"><i class="fas fa-id-card"></i> Mes cartes</a></li>
                <li><a href="update-card.php"><i class="fas fa-edit"></i> Mettre à jour</a></li>
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
                <h1>Mes Cartes de Visite</h1>
                <p>Gérez toutes vos cartes à un seul endroit</p>
            </div>
        </header>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-overview-card">
                <div class="stat-content">
                    <h3><?php echo count($cards); ?></h3>
                    <p>Total cartes</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-id-card"></i>
                </div>
            </div>
            <div class="stat-overview-card">
                <div class="stat-content">
                    <h3><?php echo $card_types['standard']; ?></h3>
                    <p>Cartes Qr Code</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-id-card"></i>
                </div>
            </div>
            <div class="stat-overview-card">
                <div class="stat-content">
                    <h3><?php echo $card_types['nfc']; ?></h3>
                    <p>Cartes NFC</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-wifi"></i>
                </div>
            </div>
        </div>

        <!-- Cards Table -->
        <div class="cards-table-section">
            <div class="section-header">
                <h2>Toutes vos cartes</h2>
                <div class="table-actions">
                    <button class="btn-filter active">Toutes</button>
                    <button class="btn-filter">Actives</button>
                    <button class="btn-filter">Expirées</button>
                </div>
            </div>

            <?php if (empty($cards)): ?>
                <div class="empty-state">
                    <i class="fas fa-id-card"></i>
                    <h3>Vous n'avez pas encore de cartes</h3>
                    <p>Créez votre première carte de visite pour commencer</p>
                    <a href="?new=true" class="btn btn-primary">Créer ma première carte</a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table id="cardsTable" class="display">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Nom</th>
                                <th>Statut</th>
                                <th>Date création</th>
                                <th>Dernière mise à jour</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cards as $card): ?>
                                <tr>
                                    <td>#<?php echo str_pad($card['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $card['category']; ?>">
                                            <?php echo strtoupper($card['category']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($card['product_name']); ?></td>
                                    <td>
                                        <span class="status status-<?php echo $card['status']; ?>">
                                            <?php echo $card['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($card['created_at'])); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($card['updated_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="update-card.php?id=<?php echo $card['id']; ?>" class="btn-action" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn-action" title="Aperçu" onclick="previewCard(<?php echo $card['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($card['category'] === 'nfc'): ?>
                                                <a href="demo.php?card=<?php echo $card['id']; ?>" class="btn-action" title="Démo">
                                                    <i class="fas fa-play-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($card['category'] !== 'nfc'): ?>
                                                <a href="upgrade-card.php?card=<?php echo $card['id']; ?>" class="btn-action" title="Mettre à niveau">
                                                    <i class="fas fa-level-up-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn-action" title="Supprimer" onclick="confirmDelete(<?php echo $card['id']; ?>)">
                                                <i class="fas fa-trash"></i>
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

        <!-- New Card Form (si paramètre new) -->
        <?php if (isset($_GET['new'])): ?>
            <div class="new-card-form">
                <h2>Créer une nouvelle carte</h2>
                <form id="newCardForm" action="../api/client/create-card.php" method="POST">
                    <div class="form-group">
                        <label for="card_type">Type de carte *</label>
                        <select id="card_type" name="card_type" required onchange="updateCardType(this.value)">
                            <option value="">Sélectionnez un type</option>
                            <option value="standard">Standard (à partir de 49,99€)</option>
                            <option value="premium">Premium (à partir de 99,99€)</option>
                            <option value="nfc">NFC (à partir de 149,99€)</option>
                        </select>
                    </div>

                    <div id="cardOptions" style="display: none;">
                        <div class="form-group">
                            <label for="quantity">Quantité *</label>
                            <select id="quantity" name="quantity" required>
                                <option value="100">100 unités</option>
                                <option value="250">250 unités</option>
                                <option value="500">500 unités</option>
                                <option value="1000">1000 unités</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="paper_type">Type de papier</label>
                            <select id="paper_type" name="paper_type">
                                <option value="mat">Mat 300g</option>
                                <option value="brillant">Brillant 300g</option>
                                <option value="recycle">Recyclé 300g</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="finish">Finition</label>
                            <select id="finish" name="finish">
                                <option value="coins_droits">Coins droits</option>
                                <option value="coins_ronds">Coins arrondis</option>
                            </select>
                        </div>

                        <div id="nfcOptions" style="display: none;">
                            <div class="form-group">
                                <label for="nfc_type">Type de NFC</label>
                                <select id="nfc_type" name="nfc_type">
                                    <option value="standard">Standard (NTAG213)</option>
                                    <option value="premium">Premium (NTAG215)</option>
                                    <option value="advanced">Avancé (NTAG216)</option>
                                </select>
                            </div>
                        </div>

                        <div id="premiumOptions" style="display: none;">
                            <div class="form-group">
                                <label for="special_finish">Finition spéciale</label>
                                <select id="special_finish" name="special_finish">
                                    <option value="none">Aucune</option>
                                    <option value="dorure">Dorure à chaud (+20€)</option>
                                    <option value="gaufrage">Gaufrage (+15€)</option>
                                    <option value="vernis">Vernis sélectif (+10€)</option>
                                </select>
                            </div>
                        </div>

                        <div class="price-summary">
                            <h3>Récapitulatif</h3>
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
                                    <span id="priceOptions">-</span>
                                </div>
                                <div class="price-item total">
                                    <span>Total estimé:</span>
                                    <span id="priceTotal">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="hideNewCardForm()">Annuler</button>
                            <button type="submit" class="btn btn-primary">Créer la carte</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="../assets/js/client.js"></script>
    <script>
        $(document).ready(function() {
            $('#cardsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
                },
                "pageLength": 10
            });
        });

        function updateCardType(type) {
            const cardOptions = document.getElementById('cardOptions');
            const nfcOptions = document.getElementById('nfcOptions');
            const premiumOptions = document.getElementById('premiumOptions');
            
            cardOptions.style.display = 'block';
            
            if (type === 'nfc') {
                nfcOptions.style.display = 'block';
                premiumOptions.style.display = 'none';
            } else if (type === 'premium') {
                nfcOptions.style.display = 'none';
                premiumOptions.style.display = 'block';
            } else {
                nfcOptions.style.display = 'none';
                premiumOptions.style.display = 'none';
            }
            
            updatePrice();
        }

        function updatePrice() {
            const type = document.getElementById('card_type').value;
            const quantity = document.getElementById('quantity').value;
            const specialFinish = document.getElementById('special_finish')?.value;
            
            let basePrice = 0;
            switch(type) {
                case 'standard': basePrice = 49.99; break;
                case 'premium': basePrice = 99.99; break;
                case 'nfc': basePrice = 149.99; break;
            }
            
            let optionsPrice = 0;
            if (specialFinish === 'dorure') optionsPrice = 20;
            else if (specialFinish === 'gaufrage') optionsPrice = 15;
            else if (specialFinish === 'vernis') optionsPrice = 10;
            
            const quantityMultiplier = quantity / 100;
            const total = (basePrice * quantityMultiplier) + optionsPrice;
            
            document.getElementById('priceType').textContent = type.toUpperCase();
            document.getElementById('priceQuantity').textContent = quantity + ' unités';
            document.getElementById('priceOptions').textContent = optionsPrice > 0 ? '+' + optionsPrice + '€' : 'Aucune';
            document.getElementById('priceTotal').textContent = total.toFixed(2) + '€';
        }

        function hideNewCardForm() {
            window.location.href = 'my-cards.php';
        }

        function confirmDelete(cardId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette carte ?')) {
                // Appel API pour suppression
                fetch(`../api/client/delete-card.php?id=${cardId}`, {
                    method: 'DELETE',
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

        function previewCard(cardId) {
            // Implémenter la prévisualisation
            window.open(`preview.php?id=${cardId}`, '_blank');
        }
    </script>
</body>
</html>