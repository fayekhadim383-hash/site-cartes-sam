<?php
// Configuration du site
define('SITE_NAME', 'CartesVisitePro');
define('SITE_URL', 'http://localhost/site-cartes-visite');
define('ADMIN_EMAIL', 'contact@cartes-visite.com');

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'cartes_visite_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuration des chemins
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/site-cartes-visite/assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>