<?php
/**
 * Fonctions utilitaires pour le site CartesVisitePro
 */

/**
 * Vérifie si un email est valide
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Vérifie la force d'un mot de passe
 */
function checkPasswordStrength($password) {
    $strength = 0;
    
    // Longueur minimale
    if (strlen($password) >= 8) $strength++;
    
    // Contient des majuscules
    if (preg_match('/[A-Z]/', $password)) $strength++;
    
    // Contient des chiffres
    if (preg_match('/[0-9]/', $password)) $strength++;
    
    // Contient des caractères spéciaux
    if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
    
    return $strength;
}

/**
 * Génère un token sécurisé
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Formate un montant
 */
function formatPrice($amount) {
    return number_format($amount, 2, ',', ' ') . ' €';
}

/**
 * Formate une date
 */
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * Raccourcit un texte
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
 * Nettoie les données d'entrée
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Upload un fichier
 */
function uploadFile($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors du téléchargement'];
    }
    
    // Vérifier le type
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé'];
    }
    
    // Vérifier la taille (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Fichier trop volumineux (max 5MB)'];
    }
    
    // Générer un nom unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $uploadPath = UPLOAD_PATH . $filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $uploadPath,
            'url' => SITE_URL . '/assets/uploads/' . $filename
        ];
    }
    
    return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier'];
}

/**
 * Envoie un email
 */
function sendEmail($to, $subject, $message, $headers = []) {
    $defaultHeaders = [
        'From: ' . ADMIN_EMAIL,
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    return mail($to, $subject, $message, implode("\r\n", $allHeaders));
}

/**
 * Redirige avec un message flash
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit();
}

/**
 * Affiche un message flash
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return '<div class="alert alert-' . htmlspecialchars($type) . '">' . htmlspecialchars($message) . '</div>';
    }
    
    return '';
}

/**
 * Génère un ID unique pour une commande
 */
function generateOrderId() {
    return 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Calcule la TVA
 */
function calculateVAT($amount, $rate = 0.20) {
    return $amount * $rate;
}

/**
 * Vérifie si un utilisateur est admin
 */
function isAdmin() {
    return isset($_SESSION['admin_id']);
}

/**
 * Vérifie si un utilisateur est client
 */
function isClient() {
    return isset($_SESSION['client_id']);
}

/**
 * Débogue une variable
 */
function debug($var) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
}
?>