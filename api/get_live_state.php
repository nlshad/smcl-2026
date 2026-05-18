<?php
// api/get_live_state.php
require_once '../config/db.php';
header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['error' => 'Database connection failed. Please run setup.php first.']);
    exit;
}

try {
    // 1. Get current auction state
    $stmt = $pdo->prepare("SELECT * FROM auction_state WHERE id = 1");
    $stmt->execute();
    $state = $stmt->fetch();

    if (!$state) {
        echo json_encode([
            'status' => 'Idle',
            'current_player' => null,
            'highest_bid' => 0,
            'leading_team_name' => null,
            'leading_team_id' => null,
            'teams' => []
        ]);
        exit;
    }

    $currentPlayer = null;
    $bidHistory = [];
    $highestBid = 0;
    $leadingTeamName = null;
    $leadingTeamId = null;

    // 2. Fetch active player info if a player is on the block
    if ($state['current_player_id']) {
        $stmt = $pdo->prepare("SELECT id, name, place, role, profile_image, base_price, auction_status FROM players WHERE id = :player_id");
        $stmt->execute(['player_id' => $state['current_player_id']]);
        $currentPlayer = $stmt->fetch();

        if ($currentPlayer) {
            // Fetch bids history for this player
            $stmt = $pdo->prepare("SELECT b.bid_amount, t.team_name, t.id as team_id, DATE_FORMAT(b.created_at, '%h:%i:%s %p') as bid_time 
                                   FROM bids b 
                                   JOIN teams t ON b.team_id = t.id 
                                   WHERE b.player_id = :player_id 
                                   ORDER BY b.bid_amount DESC");
            $stmt->execute(['player_id' => $state['current_player_id']]);
            $bidHistory = $stmt->fetchAll();

            if (!empty($bidHistory)) {
                $highestBid = (int)$bidHistory[0]['bid_amount'];
                $leadingTeamName = $bidHistory[0]['team_name'];
                $leadingTeamId = (int)$bidHistory[0]['team_id'];
            } else {
                $highestBid = (int)$currentPlayer['base_price'];
            }
        }
    }

    // 3. Fetch all teams (for leaderboards / sidebar purses)
    $stmt = $pdo->prepare("SELECT id, team_name, logo, total_purse, remaining_purse, current_squad_size, max_squad_size FROM teams ORDER BY remaining_purse DESC");
    $stmt->execute();
    $teams = $stmt->fetchAll();

    echo json_encode([
        'status' => $state['status'],
        'current_player_id' => $state['current_player_id'] ? (int)$state['current_player_id'] : null,
        'current_player' => $currentPlayer,
        'highest_bid' => $highestBid,
        'leading_team_name' => $leadingTeamName,
        'leading_team_id' => $leadingTeamId,
        'bid_history' => $bidHistory,
        'teams' => $teams
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
