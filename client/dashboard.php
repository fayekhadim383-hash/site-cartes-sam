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
$stmt = $db->prepare("SELECT company_name, email, phone, address FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

// Récupérer les cartes du client
$stmt = $db->prepare("
    SELECT cc.*, p.name as product_name, p.category 
    FROM client_cards cc 
    JOIN products p ON cc.product_id = p.id 
    WHERE cc.client_id = ? 
    ORDER BY cc.created_at DESC
");
$stmt->execute([$client_id]);
$cards = $stmt->fetchAll();

// Compter les cartes par statut
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM client_cards WHERE client_id = ? GROUP BY status");
$stmt->execute([$client_id]);
$status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Compter les démos demandées
$stmt = $db->prepare("SELECT COUNT(*) FROM demos WHERE client_id = ?");
$stmt->execute([$client_id]);
$demo_count = $stmt->fetchColumn();

// Récupérer les demandes de mise à niveau en attente
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM upgrade_requests ur
    JOIN client_cards cc ON ur.card_id = cc.id
    WHERE cc.client_id = ? AND ur.status = 'pending'
");
$stmt->execute([$client_id]);
$pending_upgrades = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - SamCard</title>
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
                <span>SamCard</span>
            </a>
        </div>
        
        <div class="client-info">
            <div class="client-avatar">
                <i class="fas fa-building"></i>
            </div>
            <h3><?php echo htmlspecialchars($client['company_name']); ?></h3>
            <p><?php echo htmlspecialchars($client['email']); ?></p>
        </div>
        
        <nav class="sidebar-nav">
            <ul>
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li><a href="my-cards.php"><i class="fas fa-id-card"></i> Mes cartes</a></li>
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
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <h1>Tableau de bord</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($client['company_name']); ?>!</p>
            </div>
            <div class="header-right">
                <div class="notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notification-count">3</span>
                </div>
                <div class="user-dropdown">
                    <img src="../assets/images/default-avatar.png" alt="Avatar">
                    <span><?php echo htmlspecialchars($client['company_name']); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </header>

        <!-- Stats -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($cards); ?></h3>
                    <p>Cartes au total</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['active'] ?? 0; ?></h3>
                    <p>Cartes actives</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_upgrades; ?></h3>
                        <p>Mises à niveau en attente</p>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $demo_count; ?></h3>
                    <p>Démonstrations</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>Actions rapides</h2>
            <div class="action-buttons">
                <a href="update-card.php" class="action-btn">
                    <i class="fas fa-edit"></i>
                    <span>Mettre à jour une carte</span>
                </a>
                <a href="demo.php" class="action-btn">
                    <i class="fas fa-play-circle"></i>
                    <span>Voir démo</span>
                </a>
            </div>
        </div>

        <!-- Recent Cards -->
        <div class="recent-cards">
            <div class="section-header">
                <h2>Cartes récentes</h2>
                <a href="my-cards.php" class="view-all">Voir toutes →</a>
            </div>
            
            <?php if (empty($cards)): ?>
                <div class="empty-state">
                    <i class="fas fa-id-card"></i>
                    <h3>Vous n'avez pas encore de cartes</h3>
                    <p>Créez votre première carte de visite pour commencer</p>
                    <a href="my-cards.php?new=true" class="btn btn-primary">Créer une carte</a>
                </div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach (array_slice($cards, 0, 4) as $card): ?>
                        <div class="card-item">
                            <div class="card-header">
                                <span class="card-type <?php echo $card['category']; ?>">
                                    <?php echo strtoupper($card['category']); ?>
                                </span>
                                <span class="card-status <?php echo $card['status']; ?>">
                                    <?php echo $card['status']; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h3><?php echo htmlspecialchars($card['product_name']); ?></h3>
                                <p>Créée le: <?php echo date('d/m/Y', strtotime($card['created_at'])); ?></p>
                                <?php if ($card['category'] === 'nfc'): ?>
                                    <p><i class="fas fa-wifi"></i> Carte NFC active</p>
                                <?php endif; ?>
                            </div>
                            <div class="card-actions">
                                <a href="update-card.php?id=<?php echo $card['id']; ?>" class="btn-action">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <a href="#" class="btn-action" onclick="previewCard(<?php echo $card['id']; ?>)">
                                    <i class="fas fa-eye"></i> Aperçu
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <div class="section-header">
                <h2>Activité récente</h2>
            </div>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="activity-content">
                        <p>Vous vous êtes connecté à votre compte</p>
                        <span class="activity-time">Il y a 5 minutes</span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="activity-content">
                        <p>Mise à jour des informations de contact</p>
                        <span class="activity-time">15 Mars 2024</span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="activity-content">
                        <p>Téléchargement du rapport d'activité</p>
                        <span class="activity-time">10 Mars 2024</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function previewCard(cardId) {
            // Implémenter la prévisualisation de la carte
            alert('Prévisualisation de la carte #' + cardId);
        }
    </script>
    <script src="../assets/js/client.js"></script>
</body>
</html>