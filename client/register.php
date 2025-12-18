<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Redirection si déjà connecté
if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $address = trim($_POST['address']);
    $accept_terms = isset($_POST['accept_terms']);
    
    // Validation
    if (empty($company_name) || empty($contact_person) || empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs obligatoires';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide';
    } elseif (!$accept_terms) {
        $error = 'Vous devez accepter les conditions d\'utilisation';
    } else {
        try {
            $db = Database::getInstance();
            
            // Vérifier si l'email existe déjà
            $stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Cet email est déjà utilisé';
            } else {
                // Hasher le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insérer le client
                $stmt = $db->prepare("INSERT INTO clients (company_name, contact_person, email, phone, password, address) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$company_name, $contact_person, $email, $phone, $hashed_password, $address]);
                
                $client_id = $db->lastInsertId();
                
                // Connecter automatiquement le client
                $_SESSION['client_id'] = $client_id;
                $_SESSION['client_name'] = $company_name;
                $_SESSION['client_email'] = $email;
                
                $success = 'Compte créé avec succès ! Redirection...';
                
                // Redirection après 2 secondes
                header('Refresh: 2; URL=dashboard.php');
            }
        } catch (PDOException $e) {
            $error = 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Client - CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/client.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <a href="../index.html" class="logo">
                <img src="../assets/images/logo.png" alt="CartesVisitePro">
                <span>CartesVisitePro</span>
            </a>
            <h1>Créer un compte</h1>
            <p>Rejoignez-nous pour gérer vos cartes de visite</p>
        </div>

        <div class="auth-form">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="company_name">Nom de l'entreprise *</label>
                        <div class="input-group">
                            <i class="fas fa-building"></i>
                            <input type="text" id="company_name" name="company_name" required value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="contact_person">Personne de contact *</label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" id="contact_person" name="contact_person" required value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Téléphone</label>
                        <div class="input-group">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">Adresse</label>
                        <div class="input-group">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Mot de passe *</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" required>
                            <button type="button" class="show-password" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-text">Minimum 8 caractères</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe *</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="show-password" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group terms">
                    <label class="checkbox">
                        <input type="checkbox" name="accept_terms" required>
                        <span>J'accepte les <a href="#" target="_blank">conditions d'utilisation</a> et la <a href="#" target="_blank">politique de confidentialité</a> *</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Créer mon compte</button>

                <div class="auth-links">
                    <p>Vous avez déjà un compte ? <a href="login.php">Connectez-vous</a></p>
                    <p><a href="../index.html">← Retour à l'accueil</a></p>
                </div>
            </form>
        </div>

        <div class="auth-footer">
            <p>&copy; 2025 CartesVisiteProSamCorporate. Tous droits réservés.</p>
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
        
        // Validation client
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères');
                return false;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>