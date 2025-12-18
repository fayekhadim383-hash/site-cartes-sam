<?php
/**
 * Fonctions d'authentification
 */

/**
 * Vérifie si un utilisateur est connecté en tant qu'admin
 */
function checkAdminAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
        exit();
    }
}

/**
 * Vérifie si un utilisateur est connecté en tant que client
 */
function checkClientAuth() {
    if (!isset($_SESSION['client_id'])) {
        header('Location: /client/login.php');
        exit();
    }
}

/**
 * Vérifie si un utilisateur est connecté
 */
function checkAuth() {
    if (!isset($_SESSION['admin_id']) && !isset($_SESSION['client_id'])) {
        header('Location: /index.html');
        exit();
    }
}

/**
 * Vérifie les permissions
 */
function checkPermission($permission) {
    $db = Database::getInstance();
    
    if (isset($_SESSION['admin_id'])) {
        $stmt = $db->prepare("SELECT permissions FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $permissions = json_decode($stmt->fetchColumn(), true);
        
        if (in_array($permission, $permissions) || in_array('all', $permissions)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Génère un token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF
 */
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Hash un mot de passe
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Vérifie un mot de passe
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Enregistre une tentative de connexion
 */
function logLoginAttempt($email, $success, $ip = null, $userAgent = null) {
    $db = Database::getInstance();
    
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'];
    $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $db->prepare("
        INSERT INTO login_attempts (email, success, ip_address, user_agent) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$email, $success, $ip, $userAgent]);
}

/**
 * Vérifie si un compte est bloqué
 */
function isAccountLocked($email) {
    $db = Database::getInstance();
    
    // Vérifier les tentatives récentes (dernières 15 minutes)
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM login_attempts 
        WHERE email = ? 
        AND success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$email]);
    $failedAttempts = $stmt->fetchColumn();
    
    // Bloquer après 5 tentatives échouées
    return $failedAttempts >= 5;
}

/**
 * Envoie un email de réinitialisation de mot de passe
 */
function sendPasswordResetEmail($email, $token) {
    $resetLink = SITE_URL . "/client/reset-password.php?token=" . urlencode($token);
    
    $subject = "Réinitialisation de votre mot de passe - CartesVisitePro";
    $message = "
        <html>
        <head>
            <title>Réinitialisation de mot de passe</title>
        </head>
        <body>
            <h2>Réinitialisation de mot de passe</h2>
            <p>Bonjour,</p>
            <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
            <p>Cliquez sur le lien suivant pour créer un nouveau mot de passe :</p>
            <p><a href=\"$resetLink\">$resetLink</a></p>
            <p>Ce lien expirera dans 1 heure.</p>
            <p>Si vous n'avez pas fait cette demande, veuillez ignorer cet email.</p>
            <p>Cordialement,<br>L'équipe CartesVisitePro</p>
        </body>
        </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Crée une session sécurisée
 */
function createSecureSession($userData, $userType = 'client') {
    // Régénérer l'ID de session
    session_regenerate_id(true);
    
    // Stocker les informations utilisateur
    if ($userType === 'admin') {
        $_SESSION['admin_id'] = $userData['id'];
        $_SESSION['admin_username'] = $userData['username'];
        $_SESSION['admin_email'] = $userData['email'];
    } else {
        $_SESSION['client_id'] = $userData['id'];
        $_SESSION['client_name'] = $userData['company_name'];
        $_SESSION['client_email'] = $userData['email'];
    }
    
    // Stocker l'empreinte numérique de la session
    $_SESSION['fingerprint'] = createSessionFingerprint();
}

/**
 * Crée une empreinte numérique de session
 */
function createSessionFingerprint() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $userAgent . $ip);
}

/**
 * Vérifie l'empreinte numérique de la session
 */
function verifySessionFingerprint() {
    if (!isset($_SESSION['fingerprint'])) {
        return false;
    }
    
    $currentFingerprint = createSessionFingerprint();
    return hash_equals($_SESSION['fingerprint'], $currentFingerprint);
}

/**
 * Déconnexion sécurisée
 */
function secureLogout() {
    // Supprimer toutes les variables de session
    $_SESSION = array();
    
    // Supprimer le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Détruire la session
    session_destroy();
}

/**
 * Vérifie l'expiration de la session
 */
function checkSessionExpiry() {
    $sessionLifetime = 3600; // 1 heure
    
    if (isset($_SESSION['LAST_ACTIVITY']) && 
        (time() - $_SESSION['LAST_ACTIVITY'] > $sessionLifetime)) {
        secureLogout();
        header('Location: /client/login.php?expired=1');
        exit();
    }
    
    $_SESSION['LAST_ACTIVITY'] = time();
}
?>