<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier l'authentification
checkClientAuth();

$db = Database::getInstance();
$client_id = $_SESSION['client_id'];

// Récupérer les données
$data = json_decode(file_get_contents('php://input'), true);

/*if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}*/

try {
    // Récupérer l'ID du produit
    $stmt = $db->prepare("SELECT id FROM products WHERE category = ? LIMIT 1");
    $stmt->execute([$data['card_type']]);
    $product_id = $stmt->fetchColumn();
    
    if (!$product_id) {
        throw new Exception('Type de carte non trouvé');
    }
    
    // Préparer les données de design
    $design_data = json_encode([
        'quantity' => $data['quantity'],
        'paper_type' => $data['paper_type'] ?? 'mat',
        'finish' => $data['finish'] ?? 'coins_droits',
        'special_finish' => $data['special_finish'] ?? 'none',
        'nfc_type' => $data['nfc_type'] ?? null
    ]);
    
    // Créer la carte
    $stmt = $db->prepare("
        INSERT INTO client_cards 
        (client_id, product_id, card_type, design_data, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $client_id,
        $product_id,
        $data['card_type'],
        $design_data
    ]);
    
    $card_id = $db->lastInsertId();
    
    // Réponse
    echo json_encode([
        'success' => true,
        'message' => 'Carte créée avec succès',
        'card_id' => $card_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>