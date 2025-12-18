<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

// Vérifier l'authentification
checkClientAuth();

// Vérifier l'upload
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
    exit();
}

$file = $_FILES['file'];

// Vérifications
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Erreur de téléchargement']);
    exit();
}

// Type de fichier
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé']);
    exit();
}

// Taille maximale (5MB)
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 5MB)']);
    exit();
}

// Générer un nom unique
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . $client_id . '.' . $extension;
$upload_path = UPLOAD_PATH . 'clients/' . $filename;

// Créer le dossier si nécessaire
$upload_dir = dirname($upload_path);
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Déplacer le fichier
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Réponse avec l'URL
    $file_url = SITE_URL . '/assets/uploads/clients/' . $filename;
    
    echo json_encode([
        'success' => true,
        'url' => $file_url,
        'filename' => $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
}
?>