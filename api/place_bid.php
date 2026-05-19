<?php
// api/place_bid.php
require_once '../config/db.php';
require_once '../config/auction_history.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check database connection
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection offline.']);
        exit;
    }

    $playerId = isset($_POST['player_id']) ? (int)$_POST['player_id'] : 0;
    $teamId = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
    $bidAmount = isset($_POST['bid_amount']) ? (int)$_POST['bid_amount'] : 0;

    if ($playerId === 0 || $teamId === 0 || $bidAmount === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid bid parameters.']);
        exit;
    }

    try {
        // Start a database transaction for concurrency control
        $pdo->beginTransaction();

        // 1. Verify that the global auction state is active and matches this player
        $stmt = $pdo->prepare("SELECT current_player_id, current_bid_amount, current_highest_bidder_id, status FROM auction_state WHERE id = 1 FOR UPDATE");
        $stmt->execute();
        $state = $stmt->fetch();

        if (!$state || $state['status'] !== 'Bidding') {
            echo json_encode(['success' => false, 'error' => 'Bidding is not open for this player.']);
            $pdo->rollBack();
            exit;
        }

        if ((int)$state['current_player_id'] !== $playerId) {
            echo json_encode(['success' => false, 'error' => 'This player is not currently on the block.']);
            $pdo->rollBack();
            exit;
        }

        // 2. Prevent team from outbidding itself (optional but highly recommended to prevent budget errors)
        if ((int)$state['current_highest_bidder_id'] === $teamId) {
            echo json_encode(['success' => false, 'error' => 'Your team already holds the highest bid!']);
            $pdo->rollBack();
            exit;
        }

        // 3. Retrieve Team Data (Check remaining purse and squad limits)
        $stmt = $pdo->prepare("SELECT team_name, remaining_purse, current_squad_size, max_squad_size FROM teams WHERE id = :team_id FOR UPDATE");
        $stmt->execute(['team_id' => $teamId]);
        $team = $stmt->fetch();

        if (!$team) {
            echo json_encode(['success' => false, 'error' => 'Franchise team not found.']);
            $pdo->rollBack();
            exit;
        }

        if ($team['current_squad_size'] >= $team['max_squad_size']) {
            echo json_encode(['success' => false, 'error' => "Squad full! Max squad limit is {$team['max_squad_size']} players."]);
            $pdo->rollBack();
            exit;
        }

        if ($bidAmount > $team['remaining_purse']) {
            echo json_encode(['success' => false, 'error' => "Insufficient funds. Remaining purse: ₹{$team['remaining_purse']}."]);
            $pdo->rollBack();
            exit;
        }

        // 4. Verify that this bid is higher than the current bid in the auction state
        $currentHighest = (int)$state['current_bid_amount'];
        if ($bidAmount <= $currentHighest) {
            echo json_encode(['success' => false, 'error' => "Bid must be higher than the current bid of ₹{$currentHighest}."]);
            $pdo->rollBack();
            exit;
        }

        // Double check against bids table max (failsafe)
        $stmt = $pdo->prepare("SELECT MAX(bid_amount) as max_bid FROM bids WHERE player_id = :player_id");
        $stmt->execute(['player_id' => $playerId]);
        $dbMax = (int)$stmt->fetch()['max_bid'];

        if ($bidAmount <= $dbMax) {
            echo json_encode(['success' => false, 'error' => "A higher bid of ₹{$dbMax} was already submitted."]);
            $pdo->rollBack();
            exit;
        }

        // 5. Insert bid log row
        $stmt = $pdo->prepare("INSERT INTO bids (player_id, team_id, bid_amount) VALUES (:player_id, :team_id, :bid_amount)");
        $stmt->execute([
            'player_id' => $playerId,
            'team_id' => $teamId,
            'bid_amount' => $bidAmount
        ]);

        // 6. Update the central auction state row
        $stmt = $pdo->prepare("UPDATE auction_state SET current_bid_amount = :bid_amount, current_highest_bidder_id = :team_id, last_update = CURRENT_TIMESTAMP WHERE id = 1");
        $stmt->execute([
            'bid_amount' => $bidAmount,
            'team_id' => $teamId
        ]);

        // Commit transaction
        $pdo->commit();
        
        // Record step in history
        record_auction_history_step($pdo, 'bid');
        
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
