<?php
// config/auction_history.php
require_once __DIR__ . '/db.php';

/**
 * Capture a complete snapshot of the auction database state.
 */
function get_current_auction_state_snapshot($pdo) {
    // 1. Get auction_state row
    $stmt = $pdo->query("SELECT current_player_id, current_bid_amount, current_highest_bidder_id, status FROM auction_state WHERE id = 1");
    $auctionState = $stmt->fetch();

    // 2. Get all players state (only critical auction fields)
    $stmt = $pdo->query("SELECT id, team_id, auction_status, sold_price FROM players");
    $playersState = $stmt->fetchAll();

    // 3. Get all bids state
    $stmt = $pdo->query("SELECT id, player_id, team_id, bid_amount, bid_time FROM bids");
    $bidsState = $stmt->fetchAll();

    // 4. Get all teams state
    $stmt = $pdo->query("SELECT id, remaining_purse, current_squad_size FROM teams");
    $teamsState = $stmt->fetchAll();

    return [
        'auction_state' => $auctionState,
        'players_state' => $playersState,
        'bids_state' => $bidsState,
        'teams_state' => $teamsState
    ];
}

/**
 * Restore database state from a snapshot.
 */
function restore_auction_state_snapshot($pdo, $snapshot) {
    // 1. Restore auction_state
    $as = $snapshot['auction_state'];
    $stmt = $pdo->prepare("UPDATE auction_state SET current_player_id = ?, current_bid_amount = ?, current_highest_bidder_id = ?, status = ?, last_update = CURRENT_TIMESTAMP WHERE id = 1");
    $stmt->execute([
        $as['current_player_id'],
        $as['current_bid_amount'],
        $as['current_highest_bidder_id'],
        $as['status']
    ]);

    // 2. Restore all players state
    foreach ($snapshot['players_state'] as $ps) {
        $stmt = $pdo->prepare("UPDATE players SET team_id = ?, auction_status = ?, sold_price = ? WHERE id = ?");
        $stmt->execute([
            $ps['team_id'],
            $ps['auction_status'],
            $ps['sold_price'],
            $ps['id']
        ]);
    }

    // 3. Restore all teams state
    foreach ($snapshot['teams_state'] as $ts) {
        $stmt = $pdo->prepare("UPDATE teams SET remaining_purse = ?, current_squad_size = ? WHERE id = ?");
        $stmt->execute([
            $ts['remaining_purse'],
            $ts['current_squad_size'],
            $ts['id']
        ]);
    }

    // 4. Restore bids list
    $pdo->exec("DELETE FROM bids");
    foreach ($snapshot['bids_state'] as $bid) {
        $stmt = $pdo->prepare("INSERT INTO bids (id, player_id, team_id, bid_amount, bid_time) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $bid['id'],
            $bid['player_id'],
            $bid['team_id'],
            $bid['bid_amount'],
            $bid['bid_time']
        ]);
    }
}

/**
 * Initialize history baseline if empty.
 */
function ensure_history_baseline($pdo) {
    // Check history pointer
    $stmt = $pdo->query("SELECT history_pointer FROM auction_state WHERE id = 1");
    $pointer = (int)$stmt->fetchColumn();

    if ($pointer === 0) {
        // Capture baseline state (Idle)
        $snapshot = get_current_auction_state_snapshot($pdo);
        $snapshotJson = json_encode($snapshot);

        // Delete any existing history
        $pdo->exec("DELETE FROM auction_history");

        // Insert baseline
        $stmt = $pdo->prepare("INSERT INTO auction_history (state_snapshot, action_type) VALUES (?, 'baseline')");
        $stmt->execute([$snapshotJson]);
        $baselineId = $pdo->lastInsertId();

        // Update pointer
        $stmt = $pdo->prepare("UPDATE auction_state SET history_pointer = ? WHERE id = 1");
        $stmt->execute([$baselineId]);

        return $baselineId;
    }
    return $pointer;
}

/**
 * Record a state change in history (truncating redo branch).
 */
function record_auction_history_step($pdo, $actionType) {
    // 1. Ensure baseline exists
    $pointer = ensure_history_baseline($pdo);

    // 2. Delete any future redo steps
    $stmt = $pdo->prepare("DELETE FROM auction_history WHERE id > ?");
    $stmt->execute([$pointer]);

    // 3. Capture and save new state snapshot
    $snapshot = get_current_auction_state_snapshot($pdo);
    $snapshotJson = json_encode($snapshot);

    $stmt = $pdo->prepare("INSERT INTO auction_history (state_snapshot, action_type) VALUES (?, ?)");
    $stmt->execute([$snapshotJson, $actionType]);
    $newPointer = $pdo->lastInsertId();

    // 4. Update pointer
    $stmt = $pdo->prepare("UPDATE auction_state SET history_pointer = ? WHERE id = 1");
    $stmt->execute([$newPointer]);

    return $newPointer;
}
