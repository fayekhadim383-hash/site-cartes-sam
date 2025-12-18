<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

// Vérifier l'authentification
checkClientAuth();

$db = Database::getInstance();
$client_id = $_SESSION['client_id'];

// Récupérer les cartes récentes (5 dernières)
$stmt = $db->prepare("
    SELECT cc.*, p.name as product_name, p.category 
    FROM client_cards cc 
    JOIN products p ON cc.product_id = p.id 
    WHERE cc.client_id = ? 
    ORDER BY cc.created_at DESC 
    LIMIT 5
");
$stmt->execute([$client_id]);
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formater les données
foreach ($cards as &$card) {
    $card['created_at'] = date('d/m/Y', strtotime($card['created_at']));
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'cards' => $cards
]);
?>