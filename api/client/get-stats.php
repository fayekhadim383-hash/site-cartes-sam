<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

// Vérifier l'authentification
checkClientAuth();

$db = Database::getInstance();
$client_id = $_SESSION['client_id'];

// Récupérer les statistiques
$stats = [];

// Nombre total de cartes
$stmt = $db->prepare("SELECT COUNT(*) FROM client_cards WHERE client_id = ?");
$stmt->execute([$client_id]);
$stats['total_cards'] = $stmt->fetchColumn();

// Cartes actives
$stmt = $db->prepare("SELECT COUNT(*) FROM client_cards WHERE client_id = ? AND status = 'active'");
$stmt->execute([$client_id]);
$stats['active_cards'] = $stmt->fetchColumn();

// Cartes NFC
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM client_cards cc 
    JOIN products p ON cc.product_id = p.id 
    WHERE cc.client_id = ? AND p.category = 'nfc'
");
$stmt->execute([$client_id]);
$stats['nfc_cards'] = $stmt->fetchColumn();

// Démos demandées
$stmt = $db->prepare("SELECT COUNT(*) FROM demos WHERE client_id = ?");
$stmt->execute([$client_id]);
$stats['demo_requests'] = $stmt->fetchColumn();

// Commandes ce mois-ci
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM client_cards 
    WHERE client_id = ? 
    AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stmt->execute([$client_id]);
$stats['cards_this_month'] = $stmt->fetchColumn();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'stats' => $stats
]);
?>