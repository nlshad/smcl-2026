<?php
// admin/index.php
session_start();
require_once '../config/db.php';

// Session protection
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../public/login.php");
    exit;
}

$successMsg = '';
$errorMsg = '';

// Handle Admin Actions (Verify, Reject, Create Team)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'verify_player') {
            $playerId = (int)$_POST['player_id'];
            $basePrice = (int)($_POST['base_price'] ?? 100);

            // Update player status
            $stmt = $pdo->prepare("UPDATE players SET payment_status = 'Verified', auction_status = 'Available', base_price = :base_price WHERE id = :id");
            $stmt->execute(['base_price' => $basePrice, 'id' => $playerId]);
            $successMsg = "🟢 Player payment verified. Player added to auction pool at base price of ₹$basePrice!";
        } elseif ($action === 'reject_player') {
            $playerId = (int)$_POST['player_id'];
            
            $stmt = $pdo->prepare("UPDATE players SET payment_status = 'Rejected', auction_status = 'Available' WHERE id = :id");
            $stmt->execute(['id' => $playerId]);
            $successMsg = "🔴 Player registration rejected.";
        } elseif ($action === 'toggle_registration') {
            $enabled = (int)$_POST['registration_enabled'];
            $stmt = $pdo->prepare("UPDATE auction_state SET registration_enabled = ? WHERE id = 1");
            $stmt->execute([$enabled]);
            $successMsg = $enabled ? "🟢 Public player registration is now ENABLED." : "🔴 Public player registration is now DISABLED.";
        } elseif ($action === 'create_team') {
            $teamName = trim($_POST['team_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $purse = (int)($_POST['purse'] ?? 10000);

            if (empty($teamName) || empty($username) || empty($password)) {
                $errorMsg = "❌ All team setup fields are required.";
            } else {
                // Check if username is taken
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE manager_username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    $errorMsg = "❌ Manager username already taken.";
                } else {
                    // Handle file upload
                    $logoName = 'team_placeholder.jpg';
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $fileTmpPath = $_FILES['logo']['tmp_name'];
                        $fileName = $_FILES['logo']['name'];
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        $newFileName = 'team_' . time() . '_' . uniqid() . '.' . $fileExtension;
                        $uploadFileDir = '../public/uploads/';
                        $dest_path = $uploadFileDir . $newFileName;
                        
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $logoName = $newFileName;
                        }
                    }

                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO teams (team_name, logo, manager_username, manager_password, total_purse, remaining_purse, current_squad_size, max_squad_size) VALUES (?, ?, ?, ?, ?, ?, 0, 15)");
                    $stmt->execute([$teamName, $logoName, $username, $hashedPassword, $purse, $purse]);
                    $successMsg = "🎉 Franchise Team '$teamName' created successfully! Manager can log in with username '$username'.";
                }
            }
        } elseif ($action === 'delete_player') {
            $playerId = (int)$_POST['player_id'];
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
            $stmt->execute([$playerId]);

            // Auto-recalculate: dynamically sync all franchise remaining purses and squad sizes
            $pdo->exec("
                UPDATE teams t 
                SET 
                    current_squad_size = (SELECT COUNT(id) FROM players p WHERE p.team_id = t.id AND p.auction_status = 'Sold'),
                    remaining_purse = total_purse - COALESCE((SELECT SUM(sold_price) FROM players p WHERE p.team_id = t.id AND p.auction_status = 'Sold'), 0)
            ");
            $pdo->commit();
            
            $successMsg = "🗑️ Player details deleted successfully, and franchise rosters/budgets were adjusted!";
        } elseif ($action === 'edit_player') {
            $playerId = (int)$_POST['player_id'];
            $name = trim($_POST['name'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $place = trim($_POST['place'] ?? '');
            $role = $_POST['role'] ?? 'Batsman';
            $utr = trim($_POST['utr'] ?? '');
            $status = $_POST['payment_status'] ?? 'Pending';
            $basePrice = (int)($_POST['base_price'] ?? 100);
            
            // New Auction Management Fields
            $teamId = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;
            $auctionStatus = $_POST['auction_status'] ?? 'Available';
            $soldPrice = !empty($_POST['sold_price']) ? (int)$_POST['sold_price'] : null;

            if (empty($name) || empty($mobile) || empty($place) || empty($utr)) {
                $errorMsg = "❌ All player edit fields are required.";
            } else {
                $stmt = $pdo->prepare("UPDATE players SET name = ?, mobile = ?, place = ?, role = ?, payment_utr = ?, payment_status = ?, base_price = ?, team_id = ?, auction_status = ?, sold_price = ? WHERE id = ?");
                $stmt->execute([$name, $mobile, $place, $role, $utr, $status, $basePrice, $teamId, $auctionStatus, $soldPrice, $playerId]);
                
                // CRITICAL AUTO-RECALCULATION: 
                // Instantly sync all franchise purses and squad sizes based on the new reality of the players table!
                $pdo->exec("
                    UPDATE teams t 
                    SET 
                        current_squad_size = (SELECT COUNT(id) FROM players p WHERE p.team_id = t.id AND p.auction_status = 'Sold'),
                        remaining_purse = total_purse - COALESCE((SELECT SUM(sold_price) FROM players p WHERE p.team_id = t.id AND p.auction_status = 'Sold'), 0)
                ");

                $successMsg = "✏️ Player details and Franchise allocations updated securely!";
            }
        } elseif ($action === 'delete_team') {
            $teamId = (int)$_POST['team_id'];
            
            // Security: Check if team has players before deleting
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE team_id = ? AND auction_status = 'Sold'");
            $stmt->execute([$teamId]);
            if ($stmt->fetchColumn() > 0) {
                $errorMsg = "❌ Cannot delete Franchise. Please release all players first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
                $stmt->execute([$teamId]);
                $successMsg = "🗑️ Franchise Team deleted successfully!";
            }
        } elseif ($action === 'release_player') {
            $playerId = (int)$_POST['player_id'];
            
            // Release player
            $stmt = $pdo->prepare("UPDATE players SET team_id = NULL, auction_status = 'Available', sold_price = NULL WHERE id = ?");
            $stmt->execute([$playerId]);
            
            // Recalculate squad sizes and purses
            $pdo->exec("
                UPDATE teams t 
                SET 
                    current_squad_size = (SELECT COUNT(id) FROM players p WHERE p.team_id = t.id AND p.auction_status = 'Sold'),
                    remaining_purse = total_purse - COALESCE((SELECT SUM(sold_price) FROM players p WHERE p.team_id = t.id AND p.auction_status = 'Sold'), 0)
            ");
            
            $successMsg = "🟢 Player released from Franchise successfully!";
        } elseif ($action === 'edit_team') {
            $teamId = (int)$_POST['team_id'];
            $teamName = trim($_POST['team_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $purse = (int)($_POST['purse'] ?? 10000);
            $remPurse = (int)($_POST['remaining_purse'] ?? 10000);
            $maxSquad = (int)($_POST['max_squad_size'] ?? 15);

            if (empty($teamName) || empty($username)) {
                $errorMsg = "❌ All team edit fields are required.";
            } else {
                // Fetch current logo
                $stmt = $pdo->prepare("SELECT logo FROM teams WHERE id = ?");
                $stmt->execute([$teamId]);
                $currTeam = $stmt->fetch();
                $logoName = $currTeam['logo'] ?? 'team_placeholder.jpg';

                // Handle file upload
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['logo']['tmp_name'];
                    $fileName = $_FILES['logo']['name'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    
                    $newFileName = 'team_' . time() . '_' . uniqid() . '.' . $fileExtension;
                    $uploadFileDir = '../public/uploads/';
                    $dest_path = $uploadFileDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $logoName = $newFileName;
                    }
                }

                if (!empty($_POST['password'])) {
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE teams SET team_name = ?, manager_username = ?, manager_password = ?, total_purse = ?, remaining_purse = ?, max_squad_size = ?, logo = ? WHERE id = ?");
                    $stmt->execute([$teamName, $username, $hashedPassword, $purse, $remPurse, $maxSquad, $logoName, $teamId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE teams SET team_name = ?, manager_username = ?, total_purse = ?, remaining_purse = ?, max_squad_size = ?, logo = ? WHERE id = ?");
                    $stmt->execute([$teamName, $username, $purse, $remPurse, $maxSquad, $logoName, $teamId]);
                }
                $successMsg = "✏️ Franchise Team details updated successfully!";
            }
        }
    } catch (Exception $e) {
        $errorMsg = "❌ Error processing request: " . $e->getMessage();
    }
}

// Fetch Data for Render
try {
    // 1. Fetch Registered Players
    $stmt = $pdo->prepare("SELECT * FROM players ORDER BY payment_status DESC, id DESC");
    $stmt->execute();
    $players = $stmt->fetchAll();

    // 2. Fetch Franchise Teams
    $stmt = $pdo->prepare("SELECT * FROM teams ORDER BY id DESC");
    $stmt->execute();
    $teams = $stmt->fetchAll();

    // 3. Fetch Registration status
    $stmt = $pdo->prepare("SELECT registration_enabled FROM auction_state WHERE id = 1");
    $stmt->execute();
    $regState = $stmt->fetch();
    $registrationEnabled = $regState ? (bool)$regState['registration_enabled'] : true;
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMCL Admin Dashboard</title>
    <?php require_once '../public/components/ui_head.php'; ?>
</head>
<body class="text-gray-255 min-h-screen flex flex-col justify-between">

    <!-- Header Navigation -->
    <header class="w-full glass-panel border-b border-gold-500/10 px-4 py-3 sm:px-6 sm:py-4 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center gap-2 sm:gap-3">
            <img src="../public/uploads/league_logo.png" alt="SMCL Logo" class="w-7 h-7 sm:w-8 sm:h-8 object-contain">
            <div>
                <h1 class="text-base sm:text-lg font-black uppercase tracking-tight text-white leading-none">
                    Super Admin Console
                </h1>
                <p class="text-[8px] sm:text-[9px] text-gold-500 uppercase tracking-widest font-bold mt-0.5">SMCL Tournament Control Centre</p>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Link to Live Bidding Desk -->
            <a href="auction.php" class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider bg-gold-500 hover:bg-gold-400 text-black px-2.5 py-1.5 sm:px-4 sm:py-2 rounded-lg transition font-extrabold shadow-md shadow-gold-500/5 flex items-center gap-1">
                <i class="fa-solid fa-microphone text-xs text-black"></i> <span class="hidden xs:inline">Live </span>Auction Room
            </a>
            <!-- Logout -->
            <a href="../public/logout.php" class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider bg-zinc-900 border border-white/5 text-gray-400 hover:bg-white/5 px-2.5 py-1.5 sm:px-3.5 sm:py-2 rounded-lg transition">
                Logout
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow p-4 md:p-6 max-w-7xl w-full mx-auto space-y-6 relative">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(212,163,12,0.01)_0%,transparent_75%)] pointer-events-none"></div>

        <!-- Success/Error Feedback Alerts -->
        <?php if (!empty($successMsg)): ?>
            <div class="bg-gold-950/20 border border-gold-500/40 text-gold-300 px-5 py-3.5 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400 text-sm"></i>
                <div><?php echo $successMsg; ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="bg-red-950/20 border border-red-500/40 text-red-300 px-5 py-3.5 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-red-400 text-sm"></i>
                <div><?php echo $errorMsg; ?></div>
            </div>
        <?php endif; ?>

        <!-- Split Grid (Left Panel: Players, Right Panel: Teams Builders) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Left Side: Player Roster & Approvals (8 Cols) -->
            <div class="lg:col-span-8 glass-panel rounded-2xl p-5 border border-gold-500/15">
                <div class="border-b border-white/5 pb-3 mb-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-gold-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-baseball-bat-ball text-base text-gray-400"></i> Player Registrations
                        </h3>
                        <p class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold mt-1">Review Registrations & Set Base Prices</p>
                    </div>
                    <span class="text-xs font-bold text-gray-400 bg-white/5 border border-white/5 px-2.5 py-1 rounded-md">
                        Total Players: <?php echo count($players); ?>
                    </span>
                </div>

                <!-- Players Table Container -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-white/5 text-gray-500 font-semibold uppercase tracking-wider">
                                <th class="pb-3 pr-2">Player</th>
                                <th class="pb-3 px-2">Role/Hometown</th>
                                <th class="pb-3 px-2 font-mono">Registration ID</th>
                                <th class="pb-3 px-2">Status</th>
                                <th class="pb-3 pl-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php if (empty($players)): ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-gray-500 uppercase tracking-widest font-semibold">
                                        No player entries recorded.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($players as $p): ?>
                                    <tr class="hover:bg-white/[0.02] transition">
                                        <!-- Player photo + name -->
                                        <td class="py-3.5 pr-2 flex items-center gap-3 cursor-pointer hover:bg-white/5 transition rounded-lg p-2" onclick="openPlayerDetailsModal(<?php echo $p['id']; ?>)">
                                            <div class="w-10 h-10 rounded-lg overflow-hidden border border-white/10 bg-black/40">
                                                <img src="../public/uploads/<?php echo htmlspecialchars($p['profile_image'] ?: 'player_placeholder.jpg'); ?>" alt="Profile" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='../public/uploads/player_placeholder.jpg';">
                                            </div>
                                            <div>
                                                <div class="font-bold text-white group-hover:text-gold-400 transition"><?php echo htmlspecialchars($p['name']); ?></div>
                                                <div class="text-[10px] text-gray-500"><?php echo htmlspecialchars($p['mobile']); ?></div>
                                            </div>
                                        </td>
                                        
                                        <!-- Role / Hometown -->
                                        <td class="py-3.5 px-2">
                                            <div class="font-semibold text-gold-400"><?php echo htmlspecialchars($p['role']); ?></div>
                                            <div class="text-[10px] text-gray-500 flex items-center gap-0.5"><i class="fa-solid fa-location-dot text-gray-500 text-[10px]"></i> <?php echo htmlspecialchars($p['place']); ?></div>
                                        </td>

                                        <!-- UTR Code -->
                                        <td class="py-3.5 px-2 font-mono text-gray-300 font-semibold">
                                            <?php echo htmlspecialchars($p['payment_utr']); ?>
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="py-3.5 px-2">
                                            <?php if ($p['payment_status'] === 'Verified'): ?>
                                                <span class="bg-gold-950/60 border border-gold-500/20 text-gold-400 font-bold px-2 py-0.5 rounded text-[9px] uppercase tracking-wide">Verified</span>
                                            <?php elseif ($p['payment_status'] === 'Rejected'): ?>
                                                <span class="bg-red-950/60 border border-red-500/20 text-red-400 font-bold px-2 py-0.5 rounded text-[9px] uppercase tracking-wide">Rejected</span>
                                            <?php else: ?>
                                                <span class="bg-yellow-950/60 border border-yellow-500/20 text-yellow-400 font-bold px-2 py-0.5 rounded text-[9px] uppercase tracking-wide animate-pulse">Pending</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions verify/reject + edit/delete forms -->
                                        <td class="py-3.5 pl-2 text-right">
                                            <div class="flex flex-col md:flex-row gap-2 items-center justify-end">
                                                <?php if ($p['payment_status'] === 'Pending'): ?>
                                                    <form action="index.php" method="POST" class="inline-flex gap-1.5 items-center">
                                                        <input type="hidden" name="player_id" value="<?php echo $p['id']; ?>">
                                                        <!-- Base Price Set Input -->
                                                        <div class="flex items-center bg-black/40 border border-white/10 rounded-lg px-1.5 py-1 max-w-[75px]">
                                                            <span class="text-gray-500 mr-0.5 font-bold">₹</span>
                                                            <input type="number" name="base_price" value="100" min="50" step="50" required
                                                                   class="w-full bg-transparent text-white focus:outline-none font-bold text-center text-[10px]">
                                                        </div>
                                                        <button type="submit" name="action" value="verify_player"
                                                                class="bg-gold-500 hover:bg-gold-400 text-black font-extrabold px-2 py-1.5 rounded-lg transition text-[9px] uppercase tracking-wider">
                                                            Verify
                                                        </button>
                                                        <button type="submit" name="action" value="reject_player"
                                                                class="bg-red-950/30 border border-red-500/30 hover:bg-red-500/10 text-red-400 font-bold px-1.5 py-1.5 rounded-lg transition text-[9px] uppercase">
                                                            Reject
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <div class="inline-flex gap-1.5 items-center">
                                                    <!-- Edit Button -->
                                                    <button onclick='openPlayerEditModal(<?php echo json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                                            class="bg-blue-950/40 border border-blue-500/30 hover:bg-blue-500/20 text-blue-400 font-bold px-2 py-1.5 rounded-lg transition text-[9px] uppercase flex items-center gap-1">
                                                        <i class="fa-solid fa-pen text-[10px]"></i> Edit
                                                    </button>
                                                    <!-- Delete Button -->
                                                    <form action="index.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete player <?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>?');" class="inline">
                                                        <input type="hidden" name="player_id" value="<?php echo $p['id']; ?>">
                                                        <button type="submit" name="action" value="delete_player"
                                                                class="bg-red-950/40 border border-red-500/30 hover:bg-red-500/20 text-red-400 font-bold px-2 py-1.5 rounded-lg transition text-[9px] uppercase flex items-center gap-1">
                                                            <i class="fa-solid fa-trash-can text-[10px]"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Side: Teams List & Creation (4 Cols) -->
            <div class="lg:col-span-4 space-y-6">
                
                <!-- Add Team Toggle Button -->
                <button id="add-team-btn" onclick="toggleAddTeamForm()" class="w-full bg-gradient-to-r from-gold-500/10 to-amber-500/10 border border-gold-500/35 hover:border-gold-500/50 text-gold-400 hover:text-gold-300 font-extrabold uppercase text-[10px] tracking-wider py-3.5 px-4 rounded-xl flex items-center justify-center gap-2 hover:bg-gold-500/15 transition duration-200 shadow-md">
                    <i class="fa-solid fa-plus-circle text-xs text-gold-400"></i> Add Franchise Team
                </button>

                <!-- Create Franchise Form -->
                <div id="add-team-panel" class="glass-panel rounded-2xl p-5 border border-gold-500/15 hidden">
                    <h3 class="text-base font-bold text-gold-400 border-b border-white/5 pb-2 mb-4 flex items-center gap-1.5">
                        <i class="fa-solid fa-plus text-base text-gray-400"></i> Add Franchise Team
                    </h3>

                    <form action="index.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="create_team">
                        
                        <!-- Team Name -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Franchise Name</label>
                            <input type="text" name="team_name" required placeholder="e.g. Wayanad Warriors"
                                   class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-gold-500 transition">
                        </div>

                        <!-- Login Username -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Manager Username</label>
                            <input type="text" name="username" required placeholder="e.g. wayanad"
                                   class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono">
                        </div>

                        <!-- Login Password -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Manager Password</label>
                            <input type="password" name="password" required placeholder="••••••••"
                                   class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-gold-500 transition">
                        </div>

                        <!-- Total Purse Points -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">purse balance (₹)</label>
                            <input type="number" name="purse" value="10000" min="1000" step="500" required
                                   class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-gold-400 font-bold focus:outline-none focus:border-gold-500 transition font-mono">
                        </div>

                        <!-- Franchise Logo Upload -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Franchise Logo (PNG / JPG)</label>
                            <input type="file" name="logo" accept="image/*"
                                   class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:uppercase file:bg-gold-500/10 file:text-gold-400 hover:file:bg-gold-500/20 file:cursor-pointer">
                        </div>

                        <!-- Submit -->
                        <button type="submit"
                                class="w-full bg-gradient-to-r from-gold-500 to-amber-600 text-black font-extrabold uppercase text-[10px] tracking-wider py-3.5 px-4 rounded-xl hover:from-gold-400 hover:to-gold-500 transition duration-300">
                            Build Franchise
                        </button>
                    </form>
                </div>

                <!-- Franchise Standings Overview -->
                <div class="glass-panel rounded-2xl p-5 border border-gold-500/15">
                    <h3 class="text-base font-bold text-gold-400 border-b border-white/5 pb-2 mb-4 flex items-center gap-1.5">
                        <i class="fa-solid fa-trophy text-base text-gray-400"></i> Franchise Standings
                    </h3>
                    <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                        <?php if (empty($teams)): ?>
                            <div class="text-center py-6 text-xs text-gray-500 uppercase font-semibold">No teams added.</div>
                        <?php else: ?>
                            <?php foreach ($teams as $t): ?>
                                <div class="p-3 bg-white/5 border border-white/5 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs hover:border-gold-500/20 transition">
                                    <div class="flex items-center gap-2.5 cursor-pointer hover:opacity-85 transition duration-200" onclick="openFranchiseDetailsModal(<?php echo $t['id']; ?>)">
                                        <img src="../public/uploads/<?php echo $t['logo'] ? htmlspecialchars($t['logo']) : 'team_placeholder.jpg'; ?>" class="w-7 h-7 rounded object-contain bg-black/40 p-0.5 border border-white/10 shadow-sm" onerror="this.onerror=null; this.src='../public/uploads/team_placeholder.jpg';">
                                        <div>
                                            <div class="font-bold text-white hover:text-gold-400 transition flex items-center gap-1">
                                                <?php echo htmlspecialchars($t['team_name']); ?>
                                                <i class="fa-solid fa-up-right-from-square text-[8px] text-gray-500"></i>
                                            </div>
                                            <div class="text-[10px] text-gray-500 mt-1">
                                                Roster: <strong class="text-gray-300 font-bold"><?php echo $t['current_squad_size']; ?>/<?php echo $t['max_squad_size']; ?></strong> 
                                                | User: <strong class="text-gold-500 font-mono"><?php echo htmlspecialchars($t['manager_username']); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between sm:justify-end gap-3.5">
                                        <div class="text-right">
                                            <div class="font-bold text-gold-400 font-mono">₹<?php echo number_format($t['remaining_purse']); ?></div>
                                            <div class="text-[9px] text-gray-500 mt-0.5">Purse Left</div>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <!-- Edit Team -->
                                            <button onclick="event.stopPropagation(); openTeamEditModal(<?php echo htmlspecialchars(json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)"
                                                    class="bg-blue-950/40 border border-blue-500/30 hover:bg-blue-500/20 text-blue-400 font-bold px-2 py-1 rounded transition text-[9px] uppercase tracking-wider flex items-center gap-1">
                                                <i class="fa-solid fa-pen text-[10px]"></i> Edit
                                            </button>
                                            <!-- Delete Team -->
                                            <form action="index.php" method="POST" 
                                                  onsubmit="<?php echo $t['current_squad_size'] > 0 ? "alert('Cannot delete Franchise. You must release all players first.'); return false;" : "return confirm('Are you sure you want to delete team " . htmlspecialchars($t['team_name'], ENT_QUOTES) . "?');"; ?>" 
                                                  class="inline" onclick="event.stopPropagation();">
                                                <input type="hidden" name="team_id" value="<?php echo $t['id']; ?>">
                                                <button type="submit" name="action" value="delete_team"
                                                        class="<?php echo $t['current_squad_size'] > 0 ? 'bg-zinc-800 border border-white/5 text-gray-500 cursor-not-allowed opacity-50' : 'bg-red-950/40 border border-red-500/30 hover:bg-red-500/20 text-red-400'; ?> font-bold px-2 py-1 rounded transition text-[9px] uppercase tracking-wider flex items-center gap-1"
                                                        <?php if ($t['current_squad_size'] > 0) echo 'title="Release all players to delete"'; ?>>
                                                    <i class="fa-solid fa-trash-can text-[10px]"></i> Delete
                                                 </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Registration Status Control -->
                <div class="glass-panel rounded-2xl p-5 border border-gold-500/15">
                    <h3 class="text-base font-bold text-gold-400 border-b border-white/5 pb-2 mb-4 flex items-center gap-1.5">
                        <i class="fa-solid fa-users-gear text-base text-gray-400"></i> Registration Settings
                    </h3>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-bold text-white">Public Registration</div>
                            <p class="text-[10px] text-gray-500 mt-0.5">Toggle open/closed status for new player registrations</p>
                        </div>
                        <form action="index.php" method="POST" class="shrink-0">
                            <input type="hidden" name="action" value="toggle_registration">
                            <input type="hidden" name="registration_enabled" value="<?php echo $registrationEnabled ? '0' : '1'; ?>">
                            <button type="submit" 
                                    class="px-3 py-1.5 rounded-lg text-[10px] uppercase font-extrabold tracking-wider transition <?php echo $registrationEnabled ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/20' : 'bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20'; ?>">
                                <?php echo $registrationEnabled ? 'Active / Open' : 'Closed'; ?>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="w-full glass-panel border-t border-gold-500/10 px-6 py-4 text-center text-xs text-gray-500 mt-6">
        <p>© 2026 Shamsu Memorial Cricket League. Super Admin Administration.</p>
    </footer>

    <!-- Player Edit Modal -->
    <div id="playerEditModal" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="max-w-md w-full glass-panel rounded-2xl p-6 border border-gold-500/20 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-white/5 pb-3 mb-4">
                <h3 class="text-base font-bold text-gold-400 flex items-center gap-1.5"><i class="fa-solid fa-pen text-gold-400"></i> Edit Player Details</h3>
                <button onclick="closePlayerEditModal()" class="text-gray-400 hover:text-white flex items-center justify-center w-6 h-6 rounded-full hover:bg-white/5"><i class="fa-solid fa-xmark text-sm"></i></button>
            </div>
            <form action="index.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_player">
                <input type="hidden" name="player_id" id="edit_player_id">

                <!-- Name -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Full Name</label>
                    <input type="text" name="name" id="edit_player_name" required
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition">
                </div>

                <!-- Mobile -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Mobile Number</label>
                    <input type="text" name="mobile" id="edit_player_mobile" required
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono">
                </div>

                <!-- Place -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Hometown / Place</label>
                    <input type="text" name="place" id="edit_player_place" required
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Role -->
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Role</label>
                        <select name="role" id="edit_player_role"
                                class="w-full bg-zinc-900 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-bold">
                            <option value="Batsman">Batsman</option>
                            <option value="Bowler">Bowler</option>
                            <option value="All-Rounder">All-Rounder</option>
                            <option value="Wicket-Keeper">Wicket-Keeper</option>
                        </select>
                    </div>

                    <!-- Base Price -->
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Base Price (₹)</label>
                        <input type="number" name="base_price" id="edit_player_base_price" min="50" step="50" required
                               class="w-full bg-black/40 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono font-bold">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Auction Status -->
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Auction Status</label>
                        <select name="auction_status" id="edit_player_auction_status"
                                class="w-full bg-zinc-900 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-bold">
                            <option value="Available">Available</option>
                            <option value="Sold">Sold</option>
                            <option value="Unsold">Unsold</option>
                        </select>
                    </div>

                    <!-- Sold Price -->
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Sold Price (₹)</label>
                        <input type="number" name="sold_price" id="edit_player_sold_price" min="0" step="50"
                               class="w-full bg-black/40 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono font-bold">
                    </div>
                </div>

                <!-- Assigned Franchise -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Assigned Franchise</label>
                    <select name="team_id" id="edit_player_team_id"
                            class="w-full bg-zinc-900 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-bold">
                        <option value="">-- None (Available / Unsold) --</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['team_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Registration Reference ID -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Registration Reference ID</label>
                    <input type="text" name="utr" id="edit_player_utr" required
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono font-bold">
                </div>

                <!-- Payment Status -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Payment Status</label>
                    <select name="payment_status" id="edit_player_status"
                            class="w-full bg-zinc-900 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-bold">
                        <option value="Pending">Pending</option>
                        <option value="Verified">Verified</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closePlayerEditModal()"
                            class="flex-1 bg-zinc-900 border border-white/5 text-gray-400 font-bold uppercase text-[10px] tracking-wider py-3 rounded-xl hover:bg-white/5 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 bg-gold-500 text-black font-extrabold uppercase text-[10px] tracking-wider py-3 rounded-xl hover:bg-gold-400 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Team Edit Modal -->
    <div id="teamEditModal" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="max-w-md w-full glass-panel rounded-2xl p-6 border border-gold-500/20 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-white/5 pb-3 mb-4">
                <h3 class="text-base font-bold text-gold-400 flex items-center gap-1.5"><i class="fa-solid fa-pen text-gold-400"></i> Edit Franchise Team</h3>
                <button onclick="closeTeamEditModal()" class="text-gray-400 hover:text-white flex items-center justify-center w-6 h-6 rounded-full hover:bg-white/5"><i class="fa-solid fa-xmark text-sm"></i></button>
            </div>
            <form action="index.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="edit_team">
                <input type="hidden" name="team_id" id="edit_team_id">

                <!-- Team Name -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Franchise Name</label>
                    <input type="text" name="team_name" id="edit_team_name" required
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition">
                </div>

                <!-- Manager Username -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Manager Username</label>
                    <input type="text" name="username" id="edit_team_username" required
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono">
                </div>

                <!-- Manager Password -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">New Password (Leave blank to keep current)</label>
                    <input type="password" name="password" placeholder="••••••••"
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Total Purse -->
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Total Purse (₹)</label>
                        <input type="number" name="purse" id="edit_team_purse" min="1000" step="500" required
                               class="w-full bg-black/40 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono font-bold">
                    </div>

                    <!-- Remaining Purse -->
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Remaining Purse (₹)</label>
                        <input type="number" name="remaining_purse" id="edit_team_remaining_purse" min="0" step="100" required
                               class="w-full bg-black/40 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono font-bold">
                    </div>
                </div>

                <!-- Max Squad Size -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Max Squad Size</label>
                    <input type="number" name="max_squad_size" id="edit_team_max_squad" min="5" max="30" required
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono font-bold">
                </div>

                <!-- Franchise Logo Update -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Update Franchise Logo (Leave blank to keep current)</label>
                    <input type="file" name="logo" accept="image/*"
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-gold-500 transition file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:uppercase file:bg-gold-500/10 file:text-gold-400 hover:file:bg-gold-500/20 file:cursor-pointer">
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeTeamEditModal()"
                            class="flex-1 bg-zinc-900 border border-white/5 text-gray-400 font-bold uppercase text-[10px] tracking-wider py-3 rounded-xl hover:bg-white/5 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 bg-gold-500 text-black font-extrabold uppercase text-[10px] tracking-wider py-3 rounded-xl hover:bg-gold-400 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Franchise Details Modal -->
    <div id="franchiseDetailsModal" class="fixed inset-0 z-50 hidden bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="max-w-2xl w-full glass-panel rounded-2xl p-6 border border-gold-500/20 max-h-[90vh] overflow-y-auto">
            
            <!-- Modal Header -->
            <div class="flex justify-between items-center border-b border-white/5 pb-4 mb-4">
                <div class="flex items-center gap-3">
                    <img id="view_team_logo" src="../public/uploads/team_placeholder.jpg" class="w-10 h-10 rounded object-contain bg-black/40 p-0.5 border border-white/10 shadow-md">
                    <div>
                        <h3 id="view_team_name" class="text-base font-black uppercase text-gold-400 tracking-tight">Franchise Details</h3>
                        <p class="text-[9px] text-gray-500 uppercase tracking-widest font-bold">Roster & Financial Summary</p>
                    </div>
                </div>
                <button onclick="closeFranchiseDetailsModal()" class="text-gray-400 hover:text-white flex items-center justify-center w-7 h-7 rounded-full hover:bg-white/5 transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Team Stats Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="p-3 bg-white/5 border border-white/5 rounded-xl text-center">
                    <div class="text-[8px] font-bold text-gray-400 uppercase tracking-widest mb-1">Purse Budget</div>
                    <div id="view_team_total_purse" class="text-xs font-black text-white font-mono">₹0</div>
                </div>
                <div class="p-3 bg-white/5 border border-white/5 rounded-xl text-center">
                    <div class="text-[8px] font-bold text-gray-400 uppercase tracking-widest mb-1">Purse Remaining</div>
                    <div id="view_team_remaining_purse" class="text-xs font-black text-gold-400 font-mono">₹0</div>
                </div>
                <div class="p-3 bg-white/5 border border-white/5 rounded-xl text-center">
                    <div class="text-[8px] font-bold text-gray-400 uppercase tracking-widest mb-1">Squad Size</div>
                    <div id="view_team_squad_size" class="text-xs font-black text-white">0 / 15</div>
                </div>
                <div class="p-3 bg-white/5 border border-white/5 rounded-xl text-center">
                    <div class="text-[8px] font-bold text-gray-400 uppercase tracking-widest mb-1">Manager</div>
                    <div id="view_team_username" class="text-xs font-black text-gold-500 font-mono">N/A</div>
                </div>
            </div>

            <!-- Team Players Roster Section -->
            <div>
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                    <i class="fa-solid fa-users text-gray-500"></i> Purchased Roster
                </h4>

                <div class="max-h-80 overflow-y-auto pr-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/5 text-[9px] uppercase tracking-wider text-gray-500">
                                <th class="py-2.5 font-bold">Player</th>
                                <th class="py-2.5 font-bold">Role</th>
                                <th class="py-2.5 font-bold text-right">Sold Price</th>
                                <th class="py-2.5 font-bold text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="view_team_players_tbody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Close Button -->
            <div class="flex justify-end pt-4 border-t border-white/5 mt-6">
                <button onclick="closeFranchiseDetailsModal()" class="bg-zinc-900 border border-white/5 text-gray-400 font-bold uppercase text-[10px] tracking-wider py-2.5 px-6 rounded-xl hover:bg-white/5 transition">
                    Close Details
                </button>
            </div>
        </div>
    </div>

    <!-- Modals Script Control -->
    <script>
    async function openFranchiseDetailsModal(teamId) {
        try {
            const response = await fetch(`../api/get_team_details.php?team_id=${teamId}`);
            const data = await response.json();
            
            if (!data.success) {
                alert(data.error || 'Failed to fetch franchise details.');
                return;
            }

            const team = data.team;
            const players = data.players;

            // Populate statistics
            document.getElementById('view_team_logo').src = team.logo ? `../public/uploads/${team.logo}` : '../public/uploads/team_placeholder.jpg';
            document.getElementById('view_team_name').innerText = team.team_name;
            document.getElementById('view_team_total_purse').innerText = '₹' + Number(team.total_purse).toLocaleString('en-IN');
            document.getElementById('view_team_remaining_purse').innerText = '₹' + Number(team.remaining_purse).toLocaleString('en-IN');
            document.getElementById('view_team_squad_size').innerText = `${team.current_squad_size} / ${team.max_squad_size}`;
            document.getElementById('view_team_username').innerText = team.owner_name;

            // Populate roster table
            const tbody = document.getElementById('view_team_players_tbody');
            tbody.innerHTML = '';

            if (players.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="py-6 text-center text-xs text-gray-500 uppercase font-semibold">
                            No players purchased yet.
                        </td>
                    </tr>
                `;
            } else {
                players.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.className = "border-b border-white/5 text-xs hover:bg-white/5 transition duration-150";
                    
                    const imgUrl = p.profile_image ? `../public/uploads/${p.profile_image}` : '../public/uploads/player_placeholder.jpg';
                    
                    tr.innerHTML = `
                        <td class="py-3 flex items-center gap-2.5">
                            <img src="${imgUrl}" class="w-8.5 h-8.5 rounded object-cover bg-black/40 border border-white/10 shadow-sm">
                            <span class="font-bold text-white">${escapeHtml(p.name)}</span>
                        </td>
                        <td class="py-3 text-gray-300 uppercase tracking-wider font-semibold text-[10px]">
                            ${escapeHtml(p.role)}
                        </td>
                        <td class="py-3 font-bold text-gold-400 font-mono text-right">
                            ₹${Number(p.sold_price).toLocaleString('en-IN')}
                        </td>
                        <td class="py-3 text-center">
                            <form action="index.php" method="POST" onsubmit="return confirm('Are you sure you want to release ${escapeHtml(p.name)} from ${escapeHtml(team.team_name)}?');" class="inline">
                                <input type="hidden" name="action" value="release_player">
                                <input type="hidden" name="player_id" value="${p.id}">
                                <button type="submit" class="bg-red-950/40 border border-red-500/30 hover:bg-red-500/20 text-red-400 font-bold px-2.5 py-1 rounded transition text-[9px] uppercase tracking-wider flex items-center justify-center gap-1 mx-auto">
                                    <i class="fa-solid fa-circle-minus"></i> Release
                                </button>
                            </form>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            document.getElementById('franchiseDetailsModal').classList.remove('hidden');

        } catch (err) {
            console.error(err);
            alert('An error occurred while loading franchise details.');
        }
    }

    function closeFranchiseDetailsModal() {
        document.getElementById('franchiseDetailsModal').classList.add('hidden');
    }

    function escapeHtml(string) {
        return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function openPlayerEditModal(player) {
        document.getElementById('edit_player_id').value = player.id;
        document.getElementById('edit_player_name').value = player.name;
        document.getElementById('edit_player_mobile').value = player.mobile;
        document.getElementById('edit_player_place').value = player.place;
        document.getElementById('edit_player_role').value = player.role;
        document.getElementById('edit_player_base_price').value = player.base_price;
        document.getElementById('edit_player_utr').value = player.payment_utr;
        document.getElementById('edit_player_status').value = player.payment_status;
        
        // Populate new auction fields
        document.getElementById('edit_player_auction_status').value = player.auction_status || 'Available';
        document.getElementById('edit_player_team_id').value = player.team_id || '';
        document.getElementById('edit_player_sold_price').value = player.sold_price || '';
        
        document.getElementById('playerEditModal').classList.remove('hidden');
    }

    function closePlayerEditModal() {
        document.getElementById('playerEditModal').classList.add('hidden');
    }

    function openTeamEditModal(team) {
        document.getElementById('edit_team_id').value = team.id;
        document.getElementById('edit_team_name').value = team.team_name;
        document.getElementById('edit_team_username').value = team.manager_username;
        document.getElementById('edit_team_purse').value = team.total_purse;
        document.getElementById('edit_team_remaining_purse').value = team.remaining_purse;
        document.getElementById('edit_team_max_squad').value = team.max_squad_size;
        
        document.getElementById('teamEditModal').classList.remove('hidden');
    }

    function closeTeamEditModal() {
        document.getElementById('teamEditModal').classList.add('hidden');
    }

    function toggleAddTeamForm() {
        const panel = document.getElementById('add-team-panel');
        const btn = document.getElementById('add-team-btn');
        if (panel.classList.contains('hidden')) {
            panel.classList.remove('hidden');
            btn.innerHTML = '<i class="fa-solid fa-circle-minus text-xs text-gold-400"></i> Hide Add Form';
        } else {
            panel.classList.add('hidden');
            btn.innerHTML = '<i class="fa-solid fa-plus-circle text-xs text-gold-400"></i> Add Franchise Team';
        }
    }
    </script>
    <?php 
        $uploadPath = "../public/uploads/";
        require_once '../public/components/modals.php'; 
    ?>
</body>
</html>
