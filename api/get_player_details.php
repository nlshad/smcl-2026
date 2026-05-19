<?php
// api/get_player_details.php
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
    // 1. Fetch complete player info and team details
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.mobile, p.place, p.role, p.profile_image, p.payment_status, p.base_price, p.sold_price, p.auction_status, t.team_name, t.logo as team_logo 
                           FROM players p 
                           LEFT JOIN teams t ON p.team_id = t.id 
                           WHERE p.id = :player_id");
    $stmt->execute(['player_id' => $playerId]);
    $player = $stmt->fetch();

    if (!$player) {
        echo json_encode(['success' => false, 'error' => 'Player not found.']);
        exit;
    }

    // 2. Fetch complete bid history for this specific player
    $stmt = $pdo->prepare("SELECT b.bid_amount, t.team_name, DATE_FORMAT(b.created_at, '%h:%i:%s %p') as bid_time 
                           FROM bids b 
                           JOIN teams t ON b.team_id = t.id 
                           WHERE b.player_id = :player_id 
                           ORDER BY b.bid_amount DESC");
    $stmt->execute(['player_id' => $playerId]);
    $bids = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'player' => $player,
        'bids' => $bids
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
