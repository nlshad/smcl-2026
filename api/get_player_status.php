<?php
// api/get_player_status.php
require_once '../config/db.php';
header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

$playerId = isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0;

if ($playerId <= 0) {
    echo json_encode(['error' => 'Invalid Player ID requested.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name, auction_status, sold_price FROM players WHERE id = :player_id");
    $stmt->execute(['player_id' => $playerId]);
    $player = $stmt->fetch();

    if ($player) {
        echo json_encode([
            'success' => true,
            'player_id' => $playerId,
            'name' => $player['name'],
            'auction_status' => $player['auction_status'],
            'sold_price' => $player['sold_price']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Player not found.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
