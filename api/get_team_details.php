<?php
// api/get_team_details.php
require_once '../config/db.php';
header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

if ($teamId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid Team ID requested.']);
    exit;
}

try {
    // 1. Fetch team statistics and info
    $stmt = $pdo->prepare("SELECT id, team_name, manager_username as owner_name, logo, total_purse, remaining_purse, current_squad_size, max_squad_size 
                           FROM teams WHERE id = :team_id");
    $stmt->execute(['team_id' => $teamId]);
    $team = $stmt->fetch();

    if (!$team) {
        echo json_encode(['success' => false, 'error' => 'Franchise not found.']);
        exit;
    }

    // 2. Fetch the roster of purchased players for this team
    $stmt = $pdo->prepare("SELECT id, name, role, profile_image, base_price, sold_price 
                           FROM players 
                           WHERE team_id = :team_id AND auction_status = 'Sold' 
                           ORDER BY sold_price DESC");
    $stmt->execute(['team_id' => $teamId]);
    $players = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'team' => $team,
        'players' => $players
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
