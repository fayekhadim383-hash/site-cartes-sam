<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Redirection si déjà connecté
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Vérifier si le compte est verrouillé
function isAccountLocked($email) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM login_attempts 
        WHERE email = ? 
        AND success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$email]);
    return $stmt->fetchColumn() >= 5;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } elseif (isAccountLocked($email)) {
        $error = 'Compte temporairement verrouillé. Veuillez réessayer dans 15 minutes.';
    } else {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT id, username, password, full_name, is_active FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if ($admin && $admin['is_active'] && password_verify($password, $admin['password'])) {
                // Créer une session sécurisée
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_email'] = $email;
                $_SESSION['fingerprint'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
                
                // Mettre à jour la dernière connexion
                $stmt = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$admin['id']]);
                
                // Enregistrer la tentative réussie
                $stmt = $db->prepare("INSERT INTO login_attempts (email, success, ip_address, user_agent) VALUES (?, 1, ?, ?)");
                $stmt->execute([$email, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                
                // Redirection
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Email ou mot de passe incorrect';
                
                // Enregistrer la tentative échouée
                $stmt = $db->prepare("INSERT INTO login_attempts (email, success, ip_address, user_agent) VALUES (?, 0, ?, ?)");
                $stmt->execute([$email, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            }
        } catch (PDOException $e) {
            $error = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - CartesVisitePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-login {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .admin-login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        
        .admin-login-header {
            padding: 40px 30px;
            text-align: center;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
        }
        
        .admin-login-header h1 {
            color: white;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .admin-login-form {
            padding: 30px;
        }
        
        .admin-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .admin-logo img {
            height: 40px;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }
        
        .input-with-icon input {
            padding-left: 45px !important;
        }
        
        .show-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            padding: 5px;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-forgot label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .forgot-password {
            color: #3498db;
            font-size: 0.9rem;
        }
        
        .login-footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #eee;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .security-notice {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .security-notice i {
            color: #3498db;
            margin-right: 10px;
        }
    </style>
</head>
<body class="admin-login">
    <div class="admin-login-container">
        <div class="admin-login-header">
            <div class="admin-logo">
                <img src="../assets/images/logo.png" alt="CartesVisitePro">
                <span>CartesVisitePro</span>
            </div>
            <h1>Administration</h1>
            <p>Accès réservé au personnel autorisé</p>
        </div>
        
        <div class="admin-login-form">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="security-notice">
                <i class="fas fa-shield-alt"></i>
                <strong>Sécurité :</strong> Cette zone est protégée. Toute tentative non autorisée sera enregistrée.
            </div>
            
            <form method="POST" action="" id="adminLoginForm">
                <div class="form-group">
                    <label for="email">Email administratif</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user-shield"></i>
                        <input type="email" id="email" name="email" required 
                               placeholder="admin@cartesvisitepro.com" autocomplete="username"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required 
                               placeholder="••••••••" autocomplete="current-password">
                        <button type="button" class="show-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="remember-forgot">
                    <label>
                        <input type="checkbox" name="remember">
                        <span>Se souvenir de moi</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Mot de passe oublié ?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <p>© 2025 CartesVisiteProSamCorporate. Tous droits réservés.</p>
            <p>Version 1.0.0</p>
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
        
        // Auto-focus sur le champ email
        document.getElementById('email').focus();
        
        // Empêcher le copier-coller dans le champ mot de passe
        document.getElementById('password').addEventListener('paste', function(e) {
            e.preventDefault();
        });
        
        // Empêcher le clic droit
        document.addEventListener('contextmenu', function(e) {
            if (e.target.type === 'password') {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>