<?php
// manager/index.php
session_start();
require_once '../config/db.php';

// Auth Protection
if (!isset($_SESSION['manager_logged_in']) || $_SESSION['manager_logged_in'] !== true) {
    header("Location: ../public/login.php");
    exit;
}

// Self-healing uploads path checker
$uploadPath = is_dir('../public/uploads') ? '../public/uploads/' : '../uploads/';

$teamId = $_SESSION['team_id'];
$managerUser = $_SESSION['manager_username'];

try {
    // Fetch latest manager team data
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = :id");
    $stmt->execute(['id' => $teamId]);
    $team = $stmt->fetch();
    
    if (!$team) {
        session_destroy();
        header("Location: ../public/login.php");
        exit;
    }

    // Fetch verified available players
    $stmt = $pdo->prepare("SELECT id, name, place, role, profile_image, base_price, auction_status FROM players WHERE payment_status = 'Verified' AND auction_status = 'Available' ORDER BY id ASC");
    $stmt->execute();
    $availablePool = $stmt->fetchAll();

    // Fetch verified sold or unsold players
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.mobile, p.place, p.role, p.profile_image, p.base_price, p.sold_price, p.auction_status, t.team_name, t.logo as team_logo 
                           FROM players p 
                           LEFT JOIN teams t ON p.team_id = t.id 
                           WHERE p.payment_status = 'Verified' AND p.auction_status IN ('Sold', 'Unsold') 
                           ORDER BY p.id DESC");
    $stmt->execute();
    $completedPool = $stmt->fetchAll();
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMCL 2026 — Franchise Dashboard</title>
    <?php require_once '../public/components/ui_head.php'; ?>
    <style>
        /* Custom UI transitions */
        .bid-btn {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bid-btn:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 163, 12, 0.25);
        }
        .bid-btn:not(:disabled):active {
            transform: translateY(1px);
        }
    </style>
</head>
<body class="text-gray-200 min-h-screen flex flex-col justify-between">

    <!-- Toast Notification Overlay -->
    <div id="toast-container" class="fixed top-6 right-6 z-50 space-y-3 pointer-events-none max-w-sm w-full"></div>

    <!-- Header Navigation -->
    <header class="w-full glass-panel border-b border-gold-500/10 px-4 py-3 sm:px-6 sm:py-4 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center gap-2 sm:gap-3">
            <img src="<?php echo $uploadPath; ?><?php echo htmlspecialchars($team['logo'] ? $team['logo'] : 'team_placeholder.jpg'); ?>" alt="Team Logo" class="w-7 h-7 sm:w-8 sm:h-8 object-contain bg-black/40 p-0.5 rounded border border-white/10">
            <div>
                <h1 class="text-base sm:text-lg font-black uppercase tracking-tight text-white leading-none">
                    <?php echo htmlspecialchars($team['team_name']); ?>
                </h1>
                <p class="text-[8px] sm:text-[9px] text-gold-500 uppercase tracking-widest font-bold mt-0.5">SMCL Franchise Manager Room</p>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
            <!-- League Logo Badge -->
            <div class="hidden sm:flex items-center gap-2 border-r border-white/10 pr-3 mr-1">
                <img src="<?php echo $uploadPath; ?>league_logo.png" alt="SMCL Logo" class="w-7 h-7 object-contain">
                <span class="text-gold-400 text-[10px] font-black uppercase tracking-wider">SMCL 2026</span>
            </div>

            <!-- Sound Toggle -->
            <button id="sound-toggle-btn" onclick="toggleMute()" class="flex items-center justify-center bg-black/40 border border-gold-500/10 hover:border-gold-500/35 w-8 h-8 rounded-full text-xs transition duration-200" title="Toggle Sound Effects">
                <i id="sound-icon" class="fa-solid fa-volume-high text-xs sm:text-sm text-gold-400"></i>
            </button>

            <!-- Active Bidding Indicator -->
            <div class="flex items-center gap-1.5 sm:gap-2 bg-black/40 border border-white/5 rounded-full px-2 py-0.5 sm:px-3 sm:py-1 text-[9px] sm:text-xs">
                <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-gray-500 animate-pulse" id="status-light"></span>
                <span class="text-gray-400 font-semibold tracking-wider uppercase text-[8px] sm:text-[10px]" id="status-text">Arena Idle</span>
            </div>

            <!-- Logout -->
            <a href="../public/logout.php" class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider bg-red-950/20 border border-red-500/20 text-red-400 hover:bg-red-500/10 px-2 py-1.5 sm:px-3 sm:py-1.5 rounded-lg transition">
                Logout
            </a>
        </div>
    </header>

    <!-- Main Content Arena -->
    <main class="flex-grow p-4 md:p-6 max-w-7xl w-full mx-auto space-y-6 relative">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(212,163,12,0.02)_0%,transparent_75%)] pointer-events-none"></div>

        <!-- Top Section: Stats & Active Bidding Panel -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT SIDE: Franchise Dashboard Stats (4 Cols) -->
        <div class="lg:col-span-4 space-y-6">
            <!-- Budget Purse Card -->
            <div class="glass-panel rounded-2xl p-6 border border-gold-500/15 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-gold-500/5 to-transparent pointer-events-none"></div>
                <span class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Remaining Purse Limit</span>
                <h2 class="text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-gold-300 via-gold-400 to-amber-500 mt-1 tracking-tight" id="manager-purse">
                    ₹<?php echo number_format($team['remaining_purse']); ?>
                </h2>
                <div class="mt-4 flex items-center justify-between text-xs text-gray-400 border-t border-white/5 pt-3">
                    <span>Total Starting Purse:</span>
                    <span class="font-bold text-gray-300">₹<?php echo number_format($team['total_purse']); ?></span>
                </div>
            </div>

            <!-- Squad List Bento Card -->
            <div class="glass-panel rounded-2xl p-6 border border-gold-500/15 flex flex-col h-[380px]">
                <div class="flex items-center justify-between border-b border-white/5 pb-4 mb-4">
                    <div>
                        <span class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Squad List</span>
                        <h3 class="text-2xl font-extrabold text-white mt-1" id="manager-squad-size">
                            <?php echo $team['current_squad_size']; ?> / <?php echo $team['max_squad_size']; ?>
                        </h3>
                    </div>
                    <i class="fa-solid fa-users text-3xl text-gold-400"></i>
                </div>
                <!-- Dynamic Player List Container -->
                <div class="flex-grow overflow-y-auto space-y-2 pr-1" id="manager-squad-list">
                    <!-- Fetched dynamically via JS -->
                    <div class="text-center text-[10px] text-gray-500 py-6 uppercase font-semibold">No players purchased yet.</div>
                </div>
                <p class="text-[10px] text-gray-400 mt-4 border-t border-white/5 pt-3">Maximum roster is limited to <?php echo $team['max_squad_size']; ?> slots.</p>
            </div>
        </div>

        <!-- CENTER SIDE: Active Auction Dashboard (8 Cols) -->
        <div class="lg:col-span-8 grid grid-cols-1 md:grid-cols-12 gap-6" id="auction-console">
            <!-- Standby Box -->
            <div id="standby-box" class="col-span-12 glass-panel rounded-2xl p-10 text-center flex flex-col items-center justify-center border border-gold-500/10 min-h-[380px]">
                <i class="fa-solid fa-tower-broadcast text-5xl text-gold-400 animate-pulse mb-3 block"></i>
                <h2 class="text-xl font-extrabold text-gold-400">Waiting for Live Auctioneer</h2>
                <p class="text-gray-400 max-w-sm mt-2 text-xs">
                    Please stand by. When the Super Admin opens bidding for a player, this panel will unlock instantly, presenting your quick-bidding controls.
                </p>
            </div>

            <!-- Player Profile (4 Cols) -->
            <div id="player-card" class="hidden col-span-12 md:col-span-4 glass-panel rounded-2xl p-5 border border-gold-500/15 flex flex-col justify-between">
                <div class="flex-grow flex flex-col items-center justify-center">
                    <div class="w-32 h-36 rounded-xl overflow-hidden border border-gold-500/20 bg-black/60 relative">
                        <img src="" id="player-image" alt="Player" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='<?php echo $uploadPath; ?>player_placeholder.jpg';">
                    </div>
                    <div class="text-center mt-4">
                        <span class="text-[8px] uppercase tracking-widest text-gold-400 font-bold bg-gold-950/60 border border-gold-500/20 px-2 py-0.5 rounded" id="player-role">
                            ROLE
                        </span>
                        <h2 class="text-xl font-bold text-white mt-1.5 tracking-tight" id="player-name">Player Name</h2>
                        <p class="text-xs text-gray-400 mt-1 flex items-center justify-center gap-1">
                            <i class="fa-solid fa-location-dot text-gray-500"></i>
                            <span id="player-place">Wayanad</span>
                        </p>
                    </div>
                </div>
                <div class="border-t border-white/5 pt-3 mt-4 flex justify-between items-center text-xs">
                    <span class="text-gray-500 font-semibold uppercase tracking-wider">Base Price</span>
                    <span class="text-gold-400 font-extrabold" id="player-base-price">₹100</span>
                </div>
            </div>

            <!-- Bidding Action Controls (5 Cols) -->
            <div id="bid-action-card" class="hidden col-span-12 md:col-span-5 space-y-6 flex flex-col justify-between">
                
                <!-- Bidding Panel -->
                <div class="glass-panel rounded-2xl p-5 border border-gold-500/15 flex-grow flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Current Active Bid</span>
                            <span class="text-[8px] uppercase tracking-widest font-bold text-red-400 bg-red-950/50 border border-red-500/20 px-1.5 py-0.5 rounded animate-pulse" id="high-bidder-indicator" style="display:none;">Leading</span>
                        </div>
                        <h3 class="text-4xl font-black text-white mt-1 tracking-tight" id="active-bid">₹0</h3>
                        <div class="flex items-center gap-2 mt-1.5" id="leading-team-wrapper">
                            <div id="leading-team-logo-container" class="w-7 h-7 rounded bg-black/40 border border-gold-500/20 flex items-center justify-center overflow-hidden p-0.5" style="display: none;">
                                <img src="" id="leading-team-logo" class="w-full h-full object-contain">
                            </div>
                            <p class="text-xs text-gray-400" id="leading-team">No bids placed yet</p>
                        </div>
                    </div>

                    <!-- Bid Increments Buttons -->
                    <div class="mt-6">
                        <span class="block text-[9px] text-gray-500 uppercase tracking-widest font-bold mb-3">Pre-calculated Bids</span>
                        <div class="grid grid-cols-2 gap-3" id="quick-bids-grid">
                            <!-- Populated dynamically via JavaScript -->
                        </div>
                    </div>

                    <!-- Custom Bid Field -->
                    <div class="mt-6 border-t border-white/5 pt-4">
                        <span class="block text-[9px] text-gray-500 uppercase tracking-widest font-bold mb-2">Place Custom Bid (₹)</span>
                        <div class="flex gap-2">
                            <input type="number" id="custom-bid-input" placeholder="Enter bid amount" min="100" step="100"
                                   class="flex-grow bg-black/60 border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-gold-500 transition font-mono">
                            <button id="custom-bid-submit" onclick="submitCustomBid()"
                                   class="bg-gold-500 hover:bg-gold-400 text-black font-extrabold uppercase text-[10px] tracking-wider px-5 rounded-xl transition">
                                Bid
                            </button>
                        </div>
                    </div>
                </div>
            </div> <!-- Close Bidding Action Controls (#bid-action-card) -->

            <!-- Bids Flow list in manager (3 Cols) -->
            <div id="bid-history-card" class="hidden col-span-12 md:col-span-3 glass-panel rounded-2xl p-4 border border-gold-500/15 flex flex-col h-full overflow-y-auto">
                <span class="block text-[9px] text-gray-500 uppercase tracking-widest font-bold mb-2.5 border-b border-white/5 pb-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-clock-rotate-left text-gray-400"></i> Bid History
                </span>
                <div class="space-y-2 pr-1" id="manager-bids-stream">
                    <!-- Polled list will render here -->
                </div>
            </div> <!-- Close Bids Flow list in manager (#bid-history-card) -->
        </div> <!-- Close Active Auction Dashboard (#auction-console) -->
    </div> <!-- Close Top Section (12 Cols Grid) -->

    <!-- Bottom Section: Pools (Side-by-side, 6 Cols each) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Block 1: Available Pool -->
            <div class="glass-panel rounded-2xl p-6 border border-gold-500/15 flex flex-col min-h-[350px]">
                <div class="flex items-center justify-between border-b border-white/5 pb-4 mb-4 gap-3">
                    <div>
                        <span class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">SMCL Pool</span>
                        <h3 class="text-base font-extrabold text-gold-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-baseball-bat-ball text-gray-400"></i> Available
                        </h3>
                    </div>
                    <div class="relative w-48 sm:w-56">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                        <input type="text" id="available-search-input" onkeyup="filterAvailablePlayers()" placeholder="Search available..." 
                               class="w-full bg-black/40 border border-white/10 rounded-xl pl-8 pr-3 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-gold-500 transition">
                    </div>
                </div>
                <div class="flex-grow overflow-y-auto max-h-[300px] space-y-2.5 pr-1" id="available-pool-container">
                    <?php if (empty($availablePool)): ?>
                        <div class="text-center text-gray-500 text-xs py-8 uppercase tracking-widest font-semibold no-players-msg">
                            No available players.
                        </div>
                    <?php else: ?>
                        <?php foreach ($availablePool as $p): ?>
                            <div class="available-player-card p-3 bg-white/5 border border-white/5 hover:border-gold-500/30 rounded-xl flex items-center gap-3 transition cursor-pointer relative" 
                                 data-id="<?php echo $p['id']; ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>"
                                 data-role="<?php echo htmlspecialchars(strtolower($p['role'])); ?>"
                                 data-place="<?php echo htmlspecialchars(strtolower($p['place'])); ?>"
                                 onclick="openPlayerDetailsModal(<?php echo $p['id']; ?>)">
                                <div class="w-8 h-8 rounded-lg overflow-hidden border border-white/10 bg-black/40 flex-shrink-0">
                                    <img src="<?php echo $uploadPath; ?><?php echo htmlspecialchars($p['profile_image'] ?: 'player_placeholder.jpg'); ?>" 
                                         alt="Player" class="w-full h-full object-cover" 
                                         onerror="this.onerror=null; this.src='<?php echo $uploadPath; ?>player_placeholder.jpg';">
                                </div>
                                <div class="flex-grow min-w-0">
                                    <div class="font-bold text-white text-xs truncate flex items-center gap-1.5">
                                        <span><?php echo htmlspecialchars($p['name']); ?></span>
                                    </div>
                                    <div class="text-[9px] text-gray-500 uppercase tracking-widest mt-0.5 truncate">
                                        <?php echo htmlspecialchars($p['role']); ?> | <span class="text-gray-400"><?php echo htmlspecialchars($p['place']); ?></span>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="text-[8px] text-gray-500 uppercase block font-bold">Base</span>
                                    <span class="text-gold-400 font-mono font-bold text-xs">₹<?php echo number_format($p['base_price']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Block 2: Sold & Unsold Pool -->
            <div class="glass-panel rounded-2xl p-6 border border-gold-500/15 flex flex-col min-h-[350px]">
                <div class="flex items-center justify-between border-b border-white/5 pb-4 mb-4 gap-3">
                    <div>
                        <span class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">SMCL Completed</span>
                        <h3 class="text-base font-extrabold text-gold-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-check text-gray-400"></i> Sold & Unsold
                        </h3>
                    </div>
                    <div class="relative w-48 sm:w-56">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                        <input type="text" id="completed-search-input" onkeyup="filterCompletedPlayers()" placeholder="Search sold/unsold..." 
                               class="w-full bg-black/40 border border-white/10 rounded-xl pl-8 pr-3 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-gold-500 transition">
                    </div>
                </div>
                <div class="flex-grow overflow-y-auto max-h-[300px] space-y-2.5 pr-1" id="completed-pool-container">
                    <?php if (empty($completedPool)): ?>
                        <div class="text-center text-gray-500 text-xs py-8 uppercase tracking-widest font-semibold no-players-msg">
                            No completed players yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($completedPool as $p): ?>
                            <div class="completed-player-card p-3 bg-white/5 border border-white/5 hover:border-gold-500/30 rounded-xl flex items-center gap-3 transition cursor-pointer relative" 
                                 data-id="<?php echo $p['id']; ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>"
                                 data-role="<?php echo htmlspecialchars(strtolower($p['role'])); ?>"
                                 data-place="<?php echo htmlspecialchars(strtolower($p['place'])); ?>"
                                 onclick="openPlayerDetailsModal(<?php echo $p['id']; ?>)">
                                <div class="w-8 h-8 rounded-lg overflow-hidden border border-white/10 bg-black/40 flex-shrink-0">
                                    <img src="<?php echo $uploadPath; ?><?php echo htmlspecialchars($p['profile_image'] ?: 'player_placeholder.jpg'); ?>" 
                                         alt="Player" class="w-full h-full object-cover" 
                                         onerror="this.onerror=null; this.src='<?php echo $uploadPath; ?>player_placeholder.jpg';">
                                </div>
                                <div class="flex-grow min-w-0">
                                    <div class="font-bold text-white text-xs truncate flex items-center gap-1.5">
                                        <span><?php echo htmlspecialchars($p['name']); ?></span>
                                        <?php if ($p['auction_status'] === 'Unsold'): ?>
                                            <span class="text-[7px] font-bold text-yellow-500 uppercase px-1 py-0.5 rounded bg-yellow-500/10 border border-yellow-500/25">Unsold</span>
                                        <?php else: ?>
                                            <span class="text-[7px] font-bold text-green-500 uppercase px-1 py-0.5 rounded bg-green-500/10 border border-green-500/25">Sold to <?php echo htmlspecialchars($p['team_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[9px] text-gray-500 uppercase tracking-widest mt-0.5 truncate">
                                        <?php echo htmlspecialchars($p['role']); ?> | <span class="text-gray-400"><?php echo htmlspecialchars($p['place']); ?></span>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <?php if ($p['auction_status'] === 'Unsold'): ?>
                                        <span class="text-[8px] text-gray-500 uppercase block font-bold">Base</span>
                                        <span class="text-gray-400 font-mono font-bold text-xs">₹<?php echo number_format($p['base_price']); ?></span>
                                    <?php else: ?>
                                        <span class="text-[8px] text-gray-500 uppercase block font-bold">Sold Price</span>
                                        <span class="text-gold-400 font-mono font-bold text-xs">₹<?php echo number_format($p['sold_price']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full glass-panel border-t border-gold-500/10 px-6 py-4 text-center text-xs text-gray-500 mt-6">
        <p>© 2026 Shamsu Memorial Cricket League. Live Manager Console.</p>
    </footer>

    <!-- JavaScript Handling Live Polling & Action Requests -->
    <script>
        const uploadPath = "<?php echo $uploadPath; ?>";
        const myTeamId = <?php echo $teamId; ?>;
        
        let activePlayerId = null;
        let myRemainingPurse = <?php echo $team['remaining_purse']; ?>;
        let mySquadSize = <?php echo $team['current_squad_size']; ?>;
        let myMaxSquad = <?php echo $team['max_squad_size']; ?>;
        let lastBidValue = 0;
        let activeStatus = 'Idle';
        let isMuted = false;

        // Premium Sound Engine (Zero-latency Web Audio API Synth)
        const SMCLSoundEngine = {
            ctx: null,
            init() {
                if (!this.ctx) {
                    this.ctx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (this.ctx.state === 'suspended') {
                    this.ctx.resume();
                }
            },
            playBid() {
                if (isMuted) return;
                try {
                    this.init();
                    const now = this.ctx.currentTime;
                    const osc = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();
                    osc.connect(gain);
                    gain.connect(this.ctx.destination);
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(480, now);
                    osc.frequency.exponentialRampToValueAtTime(120, now + 0.12);
                    gain.gain.setValueAtTime(0.45, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
                    osc.start(now);
                    osc.stop(now + 0.12);
                } catch(e) {}
            },
            playSold() {
                if (isMuted) return;
                try {
                    this.init();
                    const now = this.ctx.currentTime;
                    for (let i = 0; i < 2; i++) {
                        const time = now + i * 0.14;
                        const osc = this.ctx.createOscillator();
                        const gain = this.ctx.createGain();
                        osc.connect(gain);
                        gain.connect(this.ctx.destination);
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(580, time);
                        osc.frequency.exponentialRampToValueAtTime(90, time + 0.14);
                        gain.gain.setValueAtTime(0.55, time);
                        gain.gain.exponentialRampToValueAtTime(0.001, time + 0.14);
                        osc.start(time);
                        osc.stop(time + 0.14);
                    }
                    const notes = [523.25, 659.25, 783.99, 1046.50];
                    notes.forEach((freq, idx) => {
                        const time = now + 0.28 + idx * 0.08;
                        const osc = this.ctx.createOscillator();
                        const gain = this.ctx.createGain();
                        osc.connect(gain);
                        gain.connect(this.ctx.destination);
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, time);
                        gain.gain.setValueAtTime(0.12, time);
                        gain.gain.exponentialRampToValueAtTime(0.001, time + 0.85);
                        osc.start(time);
                        osc.stop(time + 0.85);
                    });
                } catch(e) {}
            },
            playUnsold() {
                if (isMuted) return;
                try {
                    this.init();
                    const now = this.ctx.currentTime;
                    const osc = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();
                    osc.connect(gain);
                    gain.connect(this.ctx.destination);
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(260, now);
                    osc.frequency.linearRampToValueAtTime(60, now + 0.75);
                    gain.gain.setValueAtTime(0.25, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.75);
                    osc.start(now);
                    osc.stop(now + 0.75);
                } catch(e) {}
            }
        };

        function toggleMute() {
            isMuted = !isMuted;
            const icon = document.getElementById('sound-icon');
            if (isMuted) {
                icon.className = "fa-solid fa-volume-xmark text-sm text-gray-500";
            } else {
                icon.className = "fa-solid fa-volume-high text-sm text-gold-400";
            }
            if (!isMuted) {
                SMCLSoundEngine.init();
            }
        }

        // Search Filter for Available Pool
        function filterAvailablePlayers() {
            const query = document.getElementById('available-search-input').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.available-player-card');
            
            cards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const role = card.getAttribute('data-role') || '';
                const place = card.getAttribute('data-place') || '';
                
                if (name.includes(query) || role.includes(query) || place.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Search Filter for Sold & Unsold Completed Pool
        function filterCompletedPlayers() {
            const query = document.getElementById('completed-search-input').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.completed-player-card');
            
            cards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const role = card.getAttribute('data-role') || '';
                const place = card.getAttribute('data-place') || '';
                
                if (name.includes(query) || role.includes(query) || place.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Keep both Available and Sold/Unsold Containers in Sync
        function syncPoolContainers(completedPlayers, currentBlockPlayerId) {
            const availableContainer = document.getElementById('available-pool-container');
            const completedContainer = document.getElementById('completed-pool-container');
            
            const completedMap = new Map();
            (completedPlayers || []).forEach(p => {
                completedMap.set(parseInt(p.id), p);
            });
            
            // 1. Process cards in the Available container
            const availableCards = availableContainer.querySelectorAll('.available-player-card');
            availableCards.forEach(card => {
                const cardId = parseInt(card.getAttribute('data-id'));
                
                if (completedMap.has(cardId)) {
                    card.remove();
                } else {
                    // Highlight if active player on block
                    if (cardId === currentBlockPlayerId) {
                        card.classList.add('border-gold-500/50', 'bg-gold-500/10', 'ring-1', 'ring-gold-500/30');
                        card.classList.remove('border-white/5', 'bg-white/5');
                        
                        let liveBadge = card.querySelector('.live-badge');
                        if (!liveBadge) {
                            liveBadge = document.createElement('span');
                            liveBadge.className = 'live-badge absolute top-1 right-1 bg-red-600 text-white text-[6px] font-extrabold px-1 rounded uppercase tracking-wider animate-pulse';
                            liveBadge.innerText = 'Live';
                            card.style.position = 'relative';
                            card.appendChild(liveBadge);
                        }
                    } else {
                        card.classList.remove('border-gold-500/50', 'bg-gold-500/10', 'ring-1', 'ring-gold-500/30');
                        card.classList.add('border-white/5', 'bg-white/5');
                        
                        const liveBadge = card.querySelector('.live-badge');
                        if (liveBadge) {
                            liveBadge.remove();
                        }
                    }
                }
            });
            
            // 2. Add or update cards in Completed container
            const completedCards = completedContainer.querySelectorAll('.completed-player-card');
            const existingCompletedIds = new Set();
            completedCards.forEach(card => {
                existingCompletedIds.add(parseInt(card.getAttribute('data-id')));
            });
            
            // Remove "No completed players yet" empty placeholder if we have players
            const noCompletedMsg = completedContainer.querySelector('.no-players-msg');
            if (completedMap.size > 0 && noCompletedMsg) {
                noCompletedMsg.remove();
            }
            
            // Render any missing completed cards (sorted most recent first)
            (completedPlayers || []).forEach(p => {
                const pid = parseInt(p.id);
                if (!existingCompletedIds.has(pid)) {
                    const card = document.createElement('div');
                    card.className = "completed-player-card p-3 bg-white/5 border border-white/5 hover:border-gold-500/30 rounded-xl flex items-center gap-3 transition cursor-pointer relative";
                    card.setAttribute('data-id', pid);
                    card.setAttribute('data-name', p.name.toLowerCase());
                    card.setAttribute('data-role', p.role.toLowerCase());
                    card.setAttribute('data-place', p.place.toLowerCase());
                    card.onclick = () => openPlayerDetailsModal(pid);
                    
                    const isUnsold = p.auction_status === 'Unsold';
                    const statusBadge = isUnsold 
                        ? `<span class="text-[7px] font-bold text-yellow-500 uppercase px-1 py-0.5 rounded bg-yellow-500/10 border border-yellow-500/25">Unsold</span>`
                        : `<span class="text-[7px] font-bold text-green-500 uppercase px-1 py-0.5 rounded bg-green-500/10 border border-green-500/25">Sold to ${p.team_name}</span>`;
                        
                    const rightSide = isUnsold
                        ? `<span class="text-[8px] text-gray-500 uppercase block font-bold">Base</span>
                           <span class="text-gray-400 font-mono font-bold text-xs">₹${Number(p.base_price).toLocaleString()}</span>`
                        : `<span class="text-[8px] text-gray-500 uppercase block font-bold">Sold Price</span>
                           <span class="text-gold-400 font-mono font-bold text-xs">₹${Number(p.sold_price).toLocaleString()}</span>`;
                    
                    card.innerHTML = `
                        <div class="w-8 h-8 rounded-lg overflow-hidden border border-white/10 bg-black/40 flex-shrink-0">
                            <img src="${uploadPath}${p.profile_image ? p.profile_image : 'player_placeholder.jpg'}" 
                                 alt="Player" class="w-full h-full object-cover" 
                                 onerror="this.onerror=null; this.src='${uploadPath}player_placeholder.jpg';">
                        </div>
                        <div class="flex-grow min-w-0">
                            <div class="font-bold text-white text-xs truncate flex items-center gap-1.5">
                                <span>${p.name}</span>
                                ${statusBadge}
                            </div>
                            <div class="text-[9px] text-gray-500 uppercase tracking-widest mt-0.5 truncate">
                                ${p.role} | <span class="text-gray-400">${p.place}</span>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            ${rightSide}
                        </div>
                    `;
                    
                    completedContainer.insertBefore(card, completedContainer.firstChild);
                } else {
                    // Sync badge state if needed
                    const card = completedContainer.querySelector(`.completed-player-card[data-id="${pid}"]`);
                    if (card) {
                        const isUnsold = p.auction_status === 'Unsold';
                        const statusSpan = card.querySelector('.font-bold.text-white > span:last-child');
                        if (statusSpan && statusSpan !== card.querySelector('.font-bold.text-white > span:first-child')) {
                            statusSpan.outerHTML = isUnsold 
                                ? `<span class="text-[7px] font-bold text-yellow-500 uppercase px-1 py-0.5 rounded bg-yellow-500/10 border border-yellow-500/25">Unsold</span>`
                                : `<span class="text-[7px] font-bold text-green-500 uppercase px-1 py-0.5 rounded bg-green-500/10 border border-green-500/25">Sold to ${p.team_name}</span>`;
                        }
                    }
                }
            });
            
            // Update Empty Status messages for Available pool
            const noAvailableMsg = availableContainer.querySelector('.no-players-msg');
            const currentAvailableCards = availableContainer.querySelectorAll('.available-player-card');
            if (currentAvailableCards.length === 0 && !noAvailableMsg) {
                const div = document.createElement('div');
                div.className = 'text-center text-gray-500 text-xs py-8 uppercase tracking-widest font-semibold no-players-msg';
                div.innerText = 'No available players.';
                availableContainer.appendChild(div);
            } else if (currentAvailableCards.length > 0 && noAvailableMsg) {
                noAvailableMsg.remove();
            }
        }

        document.addEventListener('click', () => {
            SMCLSoundEngine.init();
        }, { once: true });

        // Set up the polling loop
        fetchLiveState();
        setInterval(fetchLiveState, 1500);

        async function fetchLiveState() {
            try {
                const response = await fetch('../api/get_live_state.php');
                const data = await response.json();

                if (data.error) {
                    console.error(data.error);
                    return;
                }

                // 1. Update Status Indicator
                const statusLight = document.getElementById('status-light');
                const statusText = document.getElementById('status-text');
                activeStatus = data.status;

                if (data.status === 'Bidding') {
                    statusLight.className = "w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse";
                    statusText.innerText = "Bidding Active";
                    statusText.className = "text-red-400 font-extrabold tracking-wider uppercase text-[10px]";
                } else if (data.status === 'Paused') {
                    statusLight.className = "w-2.5 h-2.5 rounded-full bg-yellow-500";
                    statusText.innerText = "Bidding Paused";
                    statusText.className = "text-yellow-400 font-extrabold tracking-wider uppercase text-[10px]";
                } else {
                    statusLight.className = "w-2.5 h-2.5 rounded-full bg-gray-500";
                    statusText.innerText = "Arena Idle";
                    statusText.className = "text-gray-500 font-extrabold tracking-wider uppercase text-[10px]";
                }

                // 2. Fetch my team parameters specifically from the teams payload
                if (data.teams && data.teams.length > 0) {
                    const myTeam = data.teams.find(t => parseInt(t.id) === myTeamId);
                    if (myTeam) {
                        myRemainingPurse = parseInt(myTeam.remaining_purse);
                        mySquadSize = parseInt(myTeam.current_squad_size);
                        myMaxSquad = parseInt(myTeam.max_squad_size);

                        // Update local left-hand panels
                        document.getElementById('manager-purse').innerText = "₹" + myRemainingPurse.toLocaleString();
                        document.getElementById('manager-squad-size').innerText = `${mySquadSize} / ${myMaxSquad}`;
                    }
                }

                // 2.5 Update My Squad Roster List
                if (data.completed_players) {
                    const myPlayers = data.completed_players.filter(p => p.auction_status === 'Sold' && parseInt(p.team_id) === myTeamId);
                    const squadListEl = document.getElementById('manager-squad-list');
                    squadListEl.innerHTML = '';
                    
                    if (myPlayers.length > 0) {
                        myPlayers.forEach(p => {
                            const row = document.createElement('div');
                            row.className = "flex items-center justify-between p-2.5 rounded-lg bg-white/5 border border-white/5 text-xs cursor-pointer hover:bg-white/10 transition group";
                            row.onclick = () => openPlayerDetailsModal(p.id);
                            
                            const cleanMobile = p.mobile ? p.mobile.replace(/\D/g, '') : '';
                            let formattedMobile = cleanMobile;
                            if (formattedMobile.length === 10) {
                                formattedMobile = '91' + formattedMobile;
                            }
                            const waUrl = formattedMobile ? `https://wa.me/${formattedMobile}` : '#';

                            row.innerHTML = `
                                <div class="flex items-center gap-3">
                                    <img src="${uploadPath}${p.profile_image ? p.profile_image : 'player_placeholder.jpg'}" class="w-8 h-8 rounded-md object-cover border border-gold-500/20 shadow-md" onerror="this.onerror=null; this.src='${uploadPath}player_placeholder.jpg';">
                                    <div>
                                        <span class="text-white font-extrabold block">${p.name}</span>
                                        <span class="text-[9px] text-gray-400 uppercase tracking-wider">${p.role}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2.5">
                                    <span class="text-gold-400 font-mono font-bold">₹${p.sold_price}</span>
                                    ${p.mobile ? `
                                    <a href="${waUrl}" target="_blank" onclick="event.stopPropagation();" 
                                       class="w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20 hover:text-emerald-300 transition duration-150 flex items-center justify-center" 
                                       title="Message ${p.name} on WhatsApp">
                                        <i class="fa-brands fa-whatsapp text-sm"></i>
                                    </a>
                                    ` : ''}
                                </div>
                            `;
                            squadListEl.appendChild(row);
                        });
                    } else {
                        squadListEl.innerHTML = `<div class="text-center text-[10px] text-gray-500 py-6 uppercase font-semibold">No players purchased yet.</div>`;
                    }
                }

                // 2.7 Update Available and Completed Pools dynamically
                syncPoolContainers(data.completed_players, data.current_player_id);

                // 3. Layout Switches based on Bidding Player
                const standbyBox = document.getElementById('standby-box');
                const playerCard = document.getElementById('player-card');
                const bidActionCard = document.getElementById('bid-action-card');
                const bidHistoryCard = document.getElementById('bid-history-card');

                if (data.current_player && data.status !== 'Idle') {
                    standbyBox.classList.add('hidden');
                    playerCard.classList.remove('hidden');
                    bidActionCard.classList.remove('hidden');
                    if (bidHistoryCard) {
                        bidHistoryCard.classList.remove('hidden');
                    }

                    const newPlayerId = parseInt(data.current_player.id);

                    // Update player info
                    document.getElementById('player-name').innerText = data.current_player.name;
                    document.getElementById('player-role').innerText = data.current_player.role.toUpperCase();
                    document.getElementById('player-place').innerText = data.current_player.place;
                    document.getElementById('player-base-price').innerText = "₹" + data.current_player.base_price;
                    document.getElementById('player-image').src = data.current_player.profile_image ? (uploadPath + data.current_player.profile_image) : (uploadPath + 'player_placeholder.jpg');

                    // Update Bid details
                    document.getElementById('active-bid').innerText = "₹" + data.highest_bid;
                    
                    const leadingLogoContainer = document.getElementById('leading-team-logo-container');
                    const leadingLogo = document.getElementById('leading-team-logo');
                    const leadingTeamText = document.getElementById('leading-team');

                    if (data.leading_team_name) {
                        leadingTeamText.innerText = `Held by: ${data.leading_team_name}`;
                        if (leadingLogoContainer && leadingLogo) {
                            leadingLogo.src = data.leading_team_logo ? (uploadPath + data.leading_team_logo) : (uploadPath + 'team_placeholder.jpg');
                            leadingLogoContainer.style.display = 'flex';
                        }
                    } else {
                        leadingTeamText.innerText = "Waiting for opening bid (Base Price: ₹" + data.current_player.base_price + ")";
                        if (leadingLogoContainer) {
                            leadingLogoContainer.style.display = 'none';
                        }
                    }

                    // Toggle leading indicator
                    const indicator = document.getElementById('high-bidder-indicator');
                    const isHighBidder = (data.leading_team_id && data.leading_team_id === myTeamId);
                    
                    if (isHighBidder) {
                        indicator.style.display = 'inline-block';
                    } else {
                        indicator.style.display = 'none';
                    }

                    // Sync Bid changes & play sound
                    if (activePlayerId !== newPlayerId) {
                        activePlayerId = newPlayerId;
                        lastBidValue = parseInt(data.highest_bid);
                    } else {
                        if (lastBidValue !== 0 && parseInt(data.highest_bid) > lastBidValue) {
                            SMCLSoundEngine.playBid();
                        }
                        lastBidValue = parseInt(data.highest_bid);
                    }

                    // Render Bids Increment Button Lists
                    renderBiddingButtons(data.highest_bid, isHighBidder);

                    // Render Bids Stream logs (Flow History) in Franchise Manager Room
                    const bidsStream = document.getElementById('manager-bids-stream');
                    if (bidsStream) {
                        bidsStream.innerHTML = '';
                        if (data.bid_history && data.bid_history.length > 0) {
                            data.bid_history.forEach(log => {
                                const div = document.createElement('div');
                                div.className = "flex items-center justify-between p-2 rounded-lg bg-white/5 border border-white/5 text-[10px] hover:bg-white/10 transition duration-150";
                                const logoSrc = log.team_logo ? (uploadPath + log.team_logo) : (uploadPath + 'team_placeholder.jpg');
                                div.innerHTML = `
                                    <div class="flex items-center gap-2">
                                        <img src="${logoSrc}" class="w-5 h-5 rounded object-contain bg-black/40 p-0.5 border border-white/10">
                                        <span class="text-gray-300 font-medium">${log.team_name}</span>
                                    </div>
                                    <span class="text-gold-400 font-extrabold">₹${Number(log.bid_amount).toLocaleString()}</span>
                                `;
                                bidsStream.appendChild(div);
                            });
                        } else {
                            bidsStream.innerHTML = `<div class="text-center text-[9px] text-gray-600 py-3 font-semibold uppercase tracking-wider">No bids placed.</div>`;
                        }
                    }

                } else {
                    // Player has transitioned off the block!
                    if (activePlayerId !== null) {
                        const oldPlayerId = activePlayerId;
                        activePlayerId = null;
                        lastBidValue = 0;
                        checkPastPlayerStatus(oldPlayerId);
                    }

                    standbyBox.classList.remove('hidden');
                    playerCard.classList.add('hidden');
                    bidActionCard.classList.add('hidden');
                    if (bidHistoryCard) {
                        bidHistoryCard.classList.add('hidden');
                    }
                }

            } catch (error) {
                console.error("Manager Sync failure:", error);
            }
        }

        // Fetch whether player was sold or unsold to trigger exact sound
        async function checkPastPlayerStatus(playerId) {
            try {
                const response = await fetch(`../api/get_player_status.php?player_id=${playerId}`);
                const res = await response.json();
                if (res.success) {
                    if (res.auction_status === 'Sold') {
                        SMCLSoundEngine.playSold();
                    } else if (res.auction_status === 'Unsold') {
                        SMCLSoundEngine.playUnsold();
                    }
                }
            } catch (e) {
                console.error("Failed to lookup past player status:", e);
            }
        }

        // Render adaptive button grids
        function renderBiddingButtons(highestBid, isHighBidder) {
            const grid = document.getElementById('quick-bids-grid');
            grid.innerHTML = '';

            const increments = [100, 200, 500, 1000];
            
            increments.forEach(inc => {
                const targetBid = parseInt(highestBid) + inc;
                const button = document.createElement('button');
                button.className = "bid-btn w-full bg-zinc-900 border border-gold-500/20 text-gold-400 rounded-xl py-3 text-xs font-bold transition disabled:opacity-30 disabled:pointer-events-none flex flex-col items-center justify-center";
                button.innerHTML = `
                    <span class="text-[9px] uppercase tracking-wider text-gray-500">+₹${inc}</span>
                    <span class="text-sm font-black mt-0.5">₹${targetBid}</span>
                `;

                // Disable rules
                const isDisabled = (
                    activeStatus !== 'Bidding' || 
                    isHighBidder || 
                    mySquadSize >= myMaxSquad || 
                    targetBid > myRemainingPurse
                );
                
                if (isDisabled) {
                    button.disabled = true;
                }

                button.onclick = () => placeBid(targetBid);
                grid.appendChild(button);
            });
        }

        // Place Bid securely
        async function placeBid(bidAmount) {
            if (activeStatus !== 'Bidding') {
                showToast("Bidding is not open currently.", "error");
                return;
            }
            if (bidAmount > myRemainingPurse) {
                showToast("Insufficient budget remaining.", "error");
                return;
            }
            if (mySquadSize >= myMaxSquad) {
                showToast("Squad roster is full.", "error");
                return;
            }
            if (!activePlayerId) {
                showToast("No player currently up for auction.", "error");
                return;
            }

            const formData = new FormData();
            formData.append('player_id', activePlayerId);
            formData.append('team_id', myTeamId);
            formData.append('bid_amount', bidAmount);

            try {
                const response = await fetch('../api/place_bid.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast(`Bid of ₹${bidAmount} placed successfully!`, "success");
                    fetchLiveState(); // Refresh immediately
                } else {
                    showToast(result.error || "Bidding request rejected.", "error");
                }
            } catch (error) {
                showToast("Server connection error occurred.", "error");
                console.error(error);
            }
        }

        // Submit Custom Bid
        function submitCustomBid() {
            const input = document.getElementById('custom-bid-input');
            const customBid = parseInt(input.value);

            if (!customBid || isNaN(customBid)) {
                showToast("Please enter a valid numeric bid.", "error");
                return;
            }

            if (customBid <= lastBidValue) {
                showToast(`Bid must be strictly higher than current ₹${lastBidValue}.`, "error");
                return;
            }

            if (customBid % 100 !== 0) {
                showToast("Bid amount must be a multiple of 100.", "error");
                return;
            }

            placeBid(customBid);
            input.value = ''; // Clean input
        }

        // Beautiful glassmorphic toast notification alerts
        function showToast(message, type = "success") {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            toast.className = `p-4 rounded-xl border flex items-center gap-3 transition-all duration-300 transform translate-x-20 opacity-0 ${
                type === 'success' 
                    ? 'bg-gold-950/80 border-gold-500 text-gold-300 shadow-md shadow-gold-500/10' 
                    : 'bg-red-950/80 border-red-500 text-red-300 shadow-md shadow-red-500/10'
            }`;
            
            toast.style.backdropFilter = "blur(12px)";
            toast.innerHTML = `
                <span class="text-base">${type === 'success' ? '✔️' : '🚨'}</span>
                <span class="text-xs font-semibold leading-relaxed">${message}</span>
            `;

            container.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-20', 'opacity-0');
            }, 50);

            // Animate out and delete
            setTimeout(() => {
                toast.classList.add('translate-x-20', 'opacity-0');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3500);
        }
    </script>
    <?php 
        require_once '../public/components/modals.php'; 
    ?>
</body>
</html>
