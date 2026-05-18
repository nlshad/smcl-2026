<?php
// api/get_live_bid.php
require_once '../config/db.php';
header('Content-Type: application/json');

// Check if database was successfully connected
if (!$pdo) {
    echo json_encode(['error' => 'Database connection failed. Please run setup.php first.']);
    exit;
}

$playerId = isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0;

if ($playerId === 0) {
    echo json_encode(['error' => 'Invalid Player ID']);
    exit;
}

try {
    // Fetch the highest bid and the team name for the current player
    $sql = "SELECT b.bid_amount, t.team_name 
            FROM bids b
            JOIN teams t ON b.team_id = t.id
            WHERE b.player_id = :player_id 
            ORDER BY b.bid_amount DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['player_id' => $playerId]);
    $bidData = $stmt->fetch();

    if ($bidData) {
        echo json_encode([
            'highest_bid' => (int)$bidData['bid_amount'],
            'leading_team_name' => $bidData['team_name']
        ]);
    } else {
        // If no bids yet, return the player's base price
        $stmt = $pdo->prepare("SELECT base_price FROM players WHERE id = :player_id");
        $stmt->execute(['player_id' => $playerId]);
        $player = $stmt->fetch();
        
        echo json_encode([
            'highest_bid' => $player ? (int)$player['base_price'] : 0,
            'leading_team_name' => null
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
