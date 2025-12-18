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

$message = '';
$message_type = '';

// Traitement de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validation
    if (empty($company_name) || empty($contact_person) || empty($email)) {
        $message = 'Les champs obligatoires doivent être remplis';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Adresse email invalide';
        $message_type = 'error';
    } else {
        try {
            // Vérifier si l'email est déjà utilisé par un autre client
            $stmt = $db->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
            $stmt->execute([$email, $client_id]);
            
            if ($stmt->fetch()) {
                $message = 'Cet email est déjà utilisé par un autre compte';
                $message_type = 'error';
            } else {
                // Mettre à jour le profil
                $stmt = $db->prepare("
                    UPDATE clients 
                    SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$company_name, $contact_person, $email, $phone, $address, $client_id]);
                
                // Mettre à jour la session
                $_SESSION['client_name'] = $company_name;
                $_SESSION['client_email'] = $email;
                
                $message = 'Profil mis à jour avec succès';
                $message_type = 'success';
                
                // Recharger les données du client
                $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
                $stmt->execute([$client_id]);
                $client = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $message = 'Erreur lors de la mise à jour: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'Tous les champs doivent être remplis';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Les nouveaux mots de passe ne correspondent pas';
        $message_type = 'error';
    } elseif (strlen($new_password) < 8) {
        $message = 'Le nouveau mot de passe doit contenir au moins 8 caractères';
        $message_type = 'error';
    } else {
        try {
            // Vérifier le mot de passe actuel
            $stmt = $db->prepare("SELECT password FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $current_hash = $stmt->fetchColumn();
            
            if (password_verify($current_password, $current_hash)) {
                // Hasher le nouveau mot de passe
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Mettre à jour le mot de passe
                $stmt = $db->prepare("UPDATE clients SET password = ? WHERE id = ?");
                $stmt->execute([$new_hash, $client_id]);
                
                $message = 'Mot de passe changé avec succès';
                $message_type = 'success';
            } else {
                $message = 'Mot de passe actuel incorrect';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Erreur lors du changement de mot de passe: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Récupérer les statistiques du client
$stmt = $db->prepare("SELECT COUNT(*) as total_cards FROM client_cards WHERE client_id = ?");
$stmt->execute([$client_id]);
$total_cards = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) as active_cards FROM client_cards WHERE client_id = ? AND status = 'active'");
$stmt->execute([$client_id]);
$active_cards = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) as total_demos FROM demos WHERE client_id = ?");
$stmt->execute([$client_id]);
$total_demos = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) as upgrade_requests FROM upgrade_requests WHERE client_id = ?");
$stmt->execute([$client_id]);
$upgrade_requests = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - CartesVisitePro</title>
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
                <li><a href="upgrade-card.php"><i class="fas fa-level-up-alt"></i> Mettre à niveau</a></li>
                <li><a href="demo.php"><i class="fas fa-play-circle"></i> Démonstration</a></li>
                <li class="active"><a href="profile.php"><i class="fas fa-user-cog"></i> Mon profil</a></li>
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
                <h1>Mon Profil</h1>
                <p>Gérez vos informations personnelles et vos paramètres de compte</p>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-sections">
            <!-- Section 1 : Statistiques -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-line"></i> Statistiques de votre compte</h2>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_cards; ?></h3>
                            <p>Cartes totales</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $active_cards; ?></h3>
                            <p>Cartes actives</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_demos; ?></h3>
                            <p>Démonstrations</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-level-up-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $upgrade_requests; ?></h3>
                            <p>Mises à niveau</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2 : Informations du compte -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-user"></i> Informations du compte</h2>
                </div>
                
                <div class="account-info">
                    <form method="POST" action="" id="profileForm">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="company_name">Nom de l'entreprise *</label>
                                <input type="text" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($client['company_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_person">Personne de contact *</label>
                                <input type="text" id="contact_person" name="contact_person" 
                                       value="<?php echo htmlspecialchars($client['contact_person']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($client['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Téléphone</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label for="address">Adresse</label>
                                <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="account-meta">
                            <div class="meta-item">
                                <strong>Date d'inscription:</strong>
                                <span><?php echo date('d/m/Y', strtotime($client['created_at'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <strong>Dernière connexion:</strong>
                                <span>Aujourd'hui à <?php echo date('H:i'); ?></span>
                            </div>
                            <div class="meta-item">
                                <strong>ID Client:</strong>
                                <span>#<?php echo str_pad($client['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Section 3 : Sécurité -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-shield-alt"></i> Sécurité</h2>
                </div>
                
                <div class="security-settings">
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password">Mot de passe actuel *</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="current_password" name="current_password" required>
                                <button type="button" class="show-password" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe *</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="new_password" name="new_password" required>
                                <button type="button" class="show-password" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="form-text">Minimum 8 caractères</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le nouveau mot de passe *</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="show-password" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-bar"></div>
                            </div>
                            <span class="strength-text">Force du mot de passe</span>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Changer le mot de passe
                            </button>
                        </div>
                    </form>
                    
                    <div class="security-options">
                        <h3>Options de sécurité</h3>
                        <div class="security-option">
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                            <div class="option-info">
                                <strong>Connexion à deux facteurs</strong>
                                <p>Recevez un code par SMS pour chaque connexion</p>
                            </div>
                        </div>
                        <div class="security-option">
                            <label class="switch">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                            <div class="option-info">
                                <strong>Notifications de connexion</strong>
                                <p>Recevez un email pour chaque nouvelle connexion</p>
                            </div>
                        </div>
                        <div class="security-option">
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                            <div class="option-info">
                                <strong>Sessions actives</strong>
                                <p>Afficher et gérer vos sessions actives</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4 : Préférences -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-cog"></i> Préférences</h2>
                </div>
                
                <div class="preferences">
                    <form id="preferencesForm">
                        <div class="preference-group">
                            <h3>Notifications</h3>
                            <div class="preference-option">
                                <label class="checkbox">
                                    <input type="checkbox" name="notif_orders" checked>
                                    <span>Commandes et expéditions</span>
                                </label>
                            </div>
                            <div class="preference-option">
                                <label class="checkbox">
                                    <input type="checkbox" name="notif_updates" checked>
                                    <span>Mises à jour de cartes</span>
                                </label>
                            </div>
                            <div class="preference-option">
                                <label class="checkbox">
                                    <input type="checkbox" name="notif_promotions">
                                    <span>Promotions et offres spéciales</span>
                                </label>
                            </div>
                            <div class="preference-option">
                                <label class="checkbox">
                                    <input type="checkbox" name="notif_newsletter" checked>
                                    <span>Newsletter mensuelle</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="preference-group">
                            <h3>Affichage</h3>
                            <div class="preference-option">
                                <label for="theme">Thème</label>
                                <select id="theme" name="theme">
                                    <option value="light">Clair</option>
                                    <option value="dark">Sombre</option>
                                    <option value="auto">Auto (selon l'appareil)</option>
                                </select>
                            </div>
                            <div class="preference-option">
                                <label for="language">Langue</label>
                                <select id="language" name="language">
                                    <option value="fr">Français</option>
                                    <option value="en">English</option>
                                    <option value="es">Español</option>
                                </select>
                            </div>
                            <div class="preference-option">
                                <label for="timezone">Fuseau horaire</label>
                                <select id="timezone" name="timezone">
                                    <option value="Europe/Paris">Europe/Paris (GMT+1)</option>
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">America/New_York (GMT-5)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-primary" onclick="savePreferences()">
                                <i class="fas fa-save"></i> Enregistrer les préférences
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Section 5 : Danger Zone -->
            <div class="profile-section danger-zone">
                <div class="section-header">
                    <h2><i class="fas fa-exclamation-triangle"></i> Zone de danger</h2>
                    <p>Actions irréversibles - Soyez certain de ce que vous faites</p>
                </div>
                
                <div class="danger-actions">
                    <div class="danger-action">
                        <div class="action-info">
                            <h3>Exporter mes données</h3>
                            <p>Téléchargez toutes vos données personnelles au format JSON</p>
                        </div>
                        <button class="btn btn-outline" onclick="exportData()">
                            <i class="fas fa-download"></i> Exporter
                        </button>
                    </div>
                    
                    <div class="danger-action">
                        <div class="action-info">
                            <h3>Désactiver mon compte</h3>
                            <p>Votre compte sera désactivé mais pourra être réactivé ultérieurement</p>
                        </div>
                        <button class="btn btn-outline" onclick="disableAccount()">
                            <i class="fas fa-user-slash"></i> Désactiver
                        </button>
                    </div>
                    
                    <div class="danger-action">
                        <div class="action-info">
                            <h3>Supprimer mon compte</h3>
                            <p>Cette action est définitive et supprimera toutes vos données</p>
                        </div>
                        <button class="btn btn-danger" onclick="deleteAccount()">
                            <i class="fas fa-trash"></i> Supprimer le compte
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Vérifier la force du mot de passe
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.querySelector('.strength-bar');
            const strengthText = document.querySelector('.strength-text');
            
            let strength = 0;
            let color = '#e74c3c';
            let text = 'Faible';
            
            if (password.length >= 8) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            switch(strength) {
                case 1:
                    color = '#e74c3c';
                    text = 'Faible';
                    break;
                case 2:
                    color = '#f39c12';
                    text = 'Moyen';
                    break;
                case 3:
                    color = '#3498db';
                    text = 'Bon';
                    break;
                case 4:
                    color = '#2ecc71';
                    text = 'Fort';
                    break;
            }
            
            strengthBar.style.width = (strength * 25) + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
        });
        
        function savePreferences() {
            // Récupérer les préférences
            const preferences = {
                notifications: {
                    orders: document.querySelector('[name="notif_orders"]').checked,
                    updates: document.querySelector('[name="notif_updates"]').checked,
                    promotions: document.querySelector('[name="notif_promotions"]').checked,
                    newsletter: document.querySelector('[name="notif_newsletter"]').checked
                },
                display: {
                    theme: document.getElementById('theme').value,
                    language: document.getElementById('language').value,
                    timezone: document.getElementById('timezone').value
                }
            };
            
            // Envoyer les préférences au serveur
            fetch('../api/client/save-preferences.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(preferences)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Préférences enregistrées avec succès');
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            });
        }
        
        function exportData() {
            if (confirm('Voulez-vous exporter toutes vos données personnelles ?')) {
                window.location.href = '../api/client/export-data.php';
            }
        }
        
        function disableAccount() {
            if (confirm('Êtes-vous sûr de vouloir désactiver votre compte ? Vous pourrez le réactiver ultérieurement.')) {
                fetch('../api/client/disable-account.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Compte désactivé. Vous allez être déconnecté.');
                        window.location.href = 'logout.php';
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
        
        function deleteAccount() {
            if (confirm('ATTENTION : Cette action est définitive. Toutes vos données seront supprimées. Êtes-vous ABSOLUMENT certain ?')) {
                const confirmation = prompt('Pour confirmer la suppression, tapez "SUPPRIMER"');
                if (confirmation === 'SUPPRIMER') {
                    fetch('../api/client/delete-account.php', {
                        method: 'DELETE'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Compte supprimé. Vous allez être redirigé.');
                            window.location.href = '../index.html';
                        } else {
                            alert('Erreur: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Une erreur est survenue');
                    });
                } else {
                    alert('Suppression annulée');
                }
            }
        }
    </script>
</body>
</html>