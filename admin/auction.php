<?php
// admin/auction.php
session_start();
require_once '../config/db.php';

// Session protection
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../public/login.php");
    exit;
}

// Fetch verified and available players for the side menu list
try {
    $stmt = $pdo->prepare("SELECT * FROM players WHERE payment_status = 'Verified' AND auction_status IN ('Available', 'Unsold') ORDER BY id ASC");
    $stmt->execute();
    $availablePlayers = $stmt->fetchAll();
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMCL — Live Auctioneer Room</title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: {
                            50: '#fdfbeb',
                            100: '#fbf5c4',
                            200: '#f7e985',
                            300: '#f3d744',
                            400: '#eebf17',
                            500: '#d4a30c',
                            600: '#a77c08',
                            700: '#7e5a07',
                            800: '#533c07',
                            900: '#2b1f03',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at center, #121212 0%, #020202 100%);
            overflow-x: hidden;
        }
        h1, h2, h3, h4 {
            font-family: 'Outfit', sans-serif;
        }
        .glass-panel {
            background: rgba(22, 22, 22, 0.7);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(212, 163, 12, 0.08);
            box-shadow: 0 10px 30px 0 rgba(0, 0, 0, 0.6);
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.01);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(212, 163, 12, 0.2);
            border-radius: 4px;
        }
    </style>
</head>
<body class="text-gray-250 min-h-screen flex flex-col justify-between">

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-6 right-6 z-50 space-y-3 pointer-events-none max-w-sm w-full"></div>

    <!-- Header Navigation -->
    <header class="w-full glass-panel border-b border-gold-500/10 px-6 py-4 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <span class="text-xl">🎙️</span>
            <div>
                <h1 class="text-lg font-black uppercase tracking-tight text-white flex items-center gap-2">
                    SMCL Auctioneer Desk <span class="bg-red-600 text-white text-[8px] font-extrabold px-1.5 py-0.5 rounded tracking-widest uppercase animate-pulse">Live</span>
                </h1>
                <p class="text-[9px] text-gold-500 uppercase tracking-widest font-bold">Manage Active Bidding Flow</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- Sound Toggle -->
            <button id="sound-toggle-btn" onclick="toggleMute()" class="flex items-center justify-center bg-zinc-900 border border-white/5 hover:border-gold-500/30 w-8 h-8 rounded-lg text-xs transition duration-200" title="Toggle Sound Effects">
                <span id="sound-icon">🔊</span>
            </button>
            <!-- Back to Manager Panel -->
            <a href="index.php" class="text-[10px] font-bold uppercase tracking-wider bg-zinc-900 border border-white/5 text-gray-400 hover:bg-white/5 px-3.5 py-2 rounded-lg transition flex items-center gap-1">
                ← Admin Home
            </a>
            <!-- Logout -->
            <a href="../public/logout.php" class="text-[10px] font-bold uppercase tracking-wider bg-red-950/20 border border-red-500/20 text-red-400 hover:bg-red-500/10 px-3 py-2 rounded-lg transition">
                Logout
            </a>
        </div>
    </header>

    <!-- Main Live Controls Grid -->
    <main class="flex-grow p-4 md:p-6 max-w-7xl w-full mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 relative">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(212,163,12,0.015)_0%,transparent_75%)] pointer-events-none"></div>

        <!-- LEFT SIDE: Available Players to Bring to Block (4 Cols) -->
        <aside class="lg:col-span-4 glass-panel rounded-2xl p-5 border border-gold-500/15 flex flex-col max-h-[580px]">
            <div class="border-b border-white/5 pb-3 mb-4">
                <h3 class="text-base font-bold text-gold-400">🏏 Available Pool</h3>
                <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-semibold">Select and bring player to block</p>
            </div>

            <!-- List container -->
            <div class="flex-grow overflow-y-auto pr-1 space-y-3" id="available-players-pool">
                <?php if (empty($availablePlayers)): ?>
                    <div class="text-center text-gray-500 text-xs py-8 uppercase tracking-widest font-semibold">
                        Pool is currently empty.<br><span class="text-[10px] text-gray-600 mt-1 block">Verify payments first.</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($availablePlayers as $p): ?>
                        <div class="p-3 bg-white/5 border border-white/5 hover:border-gold-500/30 rounded-xl flex items-center justify-between transition group">
                            <div class="flex items-center gap-3 cursor-pointer" onclick="openPlayerDetailsModal(<?php echo $p['id']; ?>)">
                                <div class="w-10 h-10 rounded-lg overflow-hidden border border-white/10 bg-black/40">
                                    <img src="../public/uploads/<?php echo htmlspecialchars($p['profile_image']); ?>" alt="Player" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <div class="font-bold text-white text-xs group-hover:text-gold-400 transition"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <div class="text-[9px] text-gray-500 uppercase tracking-widest mt-0.5"><?php echo $p['role']; ?> | 📍 <?php echo htmlspecialchars($p['place']); ?></div>
                                </div>
                            </div>
                            
                            <!-- Bidding Action Form -->
                            <div class="flex items-center gap-1.5">
                                <div class="bg-black/60 border border-white/10 rounded-lg px-1.5 py-1 text-[10px] max-w-[65px] flex items-center">
                                    <span class="text-gray-600 font-bold">₹</span>
                                    <input type="number" id="base_<?php echo $p['id']; ?>" value="<?php echo $p['base_price']; ?>" step="50" min="50"
                                           class="w-full bg-transparent text-center font-bold text-gold-400 focus:outline-none">
                                </div>
                                <button onclick="bringToBlock(<?php echo $p['id']; ?>)"
                                        class="block-action-btn bg-gold-500 hover:bg-gold-400 text-black font-extrabold text-[9px] uppercase tracking-wider py-2 px-2.5 rounded-lg transition active:scale-95">
                                    Block
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <!-- RIGHT SIDE: Auction Block Control Centre (8 Cols) -->
        <div class="lg:col-span-8 grid grid-cols-1 md:grid-cols-12 gap-6" id="auctioneer-controls">
            
            <!-- Standby Box -->
            <div id="standby-box" class="col-span-12 glass-panel rounded-2xl p-10 text-center flex flex-col items-center justify-center border border-gold-500/10 min-h-[450px]">
                <span class="text-6xl animate-pulse mb-4">🎤</span>
                <h2 class="text-xl font-extrabold text-gold-400">Live Auction Block Idle</h2>
                <p class="text-gray-400 max-w-sm mt-2 text-xs">
                    Select a cricket player from the left panel pool, adjust their opening base price if necessary, and click "Block" to commence live franchise bidding.
                </p>
            </div>

            <!-- Active Player Card (5 Cols) -->
            <div id="player-card" class="hidden md:col-span-5 glass-panel rounded-2xl p-5 border border-gold-500/15 flex flex-col justify-between">
                <div class="flex-grow flex flex-col items-center justify-center">
                    <div class="w-32 h-36 rounded-xl overflow-hidden border border-gold-500/20 bg-black/60 relative">
                        <img src="" id="player-image" alt="Player Image" class="w-full h-full object-cover">
                    </div>
                    <div class="text-center mt-4">
                        <span class="text-[8px] uppercase tracking-widest text-gold-400 font-bold bg-gold-950/60 border border-gold-500/20 px-2 py-0.5 rounded" id="player-role">
                            ROLE
                        </span>
                        <h2 class="text-xl font-bold text-white mt-1.5 tracking-tight" id="player-name">---</h2>
                        <p class="text-xs text-gray-400" id="player-place">📍 Hometown</p>
                    </div>
                </div>
                <!-- Base Price -->
                <div class="border-t border-white/5 pt-3 mt-4 flex justify-between items-center text-xs">
                    <span class="text-gray-500 font-semibold uppercase tracking-wider">Base Price</span>
                    <span class="text-gold-400 font-extrabold" id="player-base-price">₹100</span>
                </div>
            </div>

            <!-- Bids & Auctioneer Controllers (7 Cols) -->
            <div id="bid-control-card" class="hidden md:col-span-7 space-y-6 flex flex-col justify-between">
                
                <!-- Bidding Panel -->
                <div class="glass-panel rounded-2xl p-5 border border-gold-500/15 flex-grow flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Active High Bid</span>
                            <div class="flex items-center gap-1.5 bg-black/40 border border-white/5 rounded-full px-2.5 py-0.5 text-[8px]" id="auctioneer-status-tag">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500" id="auctioneer-status-light"></span>
                                <span class="text-gray-400 uppercase font-bold" id="auctioneer-status-text">Bidding</span>
                            </div>
                        </div>
                        <h3 class="text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-gold-300 via-gold-400 to-amber-500 mt-1 tracking-tight" id="active-bid">₹0</h3>
                        <p class="text-xs text-gray-400 mt-1" id="leading-team">No bids placed yet</p>
                    </div>

                    <!-- Command Buttons -->
                    <div class="mt-6 space-y-3">
                        <span class="block text-[9px] text-gray-500 uppercase tracking-widest font-bold">Auctioneer Handlers</span>
                        <div class="grid grid-cols-2 gap-3">
                            <button onclick="triggerAuctionAction('sold')"
                                    class="w-full bg-gradient-to-r from-gold-500 to-amber-600 text-black font-extrabold uppercase text-[10px] tracking-wider py-3.5 px-4 rounded-xl hover:from-gold-400 hover:to-gold-500 transition duration-300 active:scale-95 shadow-md shadow-gold-500/10">
                                sold
                            </button>
                            <button onclick="triggerAuctionAction('unsold')"
                                    class="w-full bg-zinc-900 border border-white/5 hover:bg-white/5 text-gray-300 font-extrabold uppercase text-[10px] tracking-wider py-3.5 px-4 rounded-xl transition active:scale-95">
                                unsold / pass
                            </button>
                        </div>
                        <button onclick="triggerAuctionAction('toggle_pause')" id="pause-btn"
                                class="w-full bg-zinc-800 hover:bg-zinc-700 text-gold-400 font-bold uppercase text-[10px] tracking-wider py-3 px-4 rounded-xl transition border border-gold-500/10">
                            pause bidding
                        </button>
                    </div>
                </div>

                <!-- Bids Flow list in admin -->
                <div class="glass-panel rounded-2xl p-4 border border-gold-500/15 max-h-40 overflow-y-auto">
                    <span class="block text-[9px] text-gray-500 uppercase tracking-widest font-bold mb-2.5 border-b border-white/5 pb-1">Incoming Bid stream</span>
                    <div class="space-y-2 pr-1" id="admin-bids-stream">
                        <!-- Polled list will render here -->
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full glass-panel border-t border-gold-500/10 px-6 py-4 text-center text-xs text-gray-500 mt-6">
        <p>© 2026 Shamsu Memorial Cricket League. Super Admin Administration.</p>
    </footer>

    <!-- JavaScript Controller -->
    <script>
        let activePlayerId = null;
        let lastBiddingStatus = 'Idle';
        let lastBidAmount = 0;
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
            document.getElementById('sound-icon').innerText = isMuted ? '🔇' : '🔊';
            if (!isMuted) {
                SMCLSoundEngine.init();
            }
        }

        document.addEventListener('click', () => {
            SMCLSoundEngine.init();
        }, { once: true });

        // Polling loop
        fetchAuctioneerState();
        setInterval(fetchAuctioneerState, 1500);

        async function fetchAuctioneerState() {
            try {
                const response = await fetch('../api/get_live_state.php');
                const data = await response.json();

                if (data.error) {
                    console.error(data.error);
                    return;
                }

                const standbyBox = document.getElementById('standby-box');
                const playerCard = document.getElementById('player-card');
                const bidControlCard = document.getElementById('bid-control-card');
                
                const blockButtons = document.querySelectorAll('.block-action-btn');

                // Toggle Standby vs Controls
                if (data.current_player && data.status !== 'Idle') {
                    blockButtons.forEach(btn => {
                        btn.disabled = true;
                        btn.classList.add('opacity-30', 'cursor-not-allowed');
                        btn.classList.remove('hover:bg-gold-400', 'active:scale-95');
                    });

                    standbyBox.classList.add('hidden');
                    playerCard.classList.remove('hidden');
                    bidControlCard.classList.remove('hidden');

                    const newPlayerId = parseInt(data.current_player.id);
                    lastBiddingStatus = data.status;

                    // Update player details
                    document.getElementById('player-name').innerText = data.current_player.name;
                    document.getElementById('player-role').innerText = data.current_player.role.toUpperCase();
                    document.getElementById('player-place').innerText = "📍 " + data.current_player.place;
                    document.getElementById('player-base-price').innerText = "₹" + data.current_player.base_price;
                    document.getElementById('player-image').src = "../public/uploads/" + data.current_player.profile_image;

                    // Update Active Bid details
                    document.getElementById('active-bid').innerText = "₹" + data.highest_bid;
                    document.getElementById('leading-team').innerText = data.leading_team_name 
                        ? `Held by: ${data.leading_team_name}` 
                        : "Waiting for opening bid...";

                    // Sync Bid changes & play bid sound
                    if (activePlayerId !== newPlayerId) {
                        activePlayerId = newPlayerId;
                        lastBidAmount = data.highest_bid;
                    } else {
                        if (lastBidAmount !== 0 && data.highest_bid > lastBidAmount) {
                            SMCLSoundEngine.playBid();
                        }
                        lastBidAmount = data.highest_bid;
                    }

                    // Sync Auctioneer Status indicator
                    const stLight = document.getElementById('auctioneer-status-light');
                    const stText = document.getElementById('auctioneer-status-text');
                    const pauseBtn = document.getElementById('pause-btn');

                    if (data.status === 'Bidding') {
                        stLight.className = "w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse";
                        stText.innerText = "Bidding Open";
                        stText.className = "text-red-400 font-extrabold uppercase";
                        pauseBtn.innerText = "Pause Bidding";
                    } else if (data.status === 'Paused') {
                        stLight.className = "w-1.5 h-1.5 rounded-full bg-yellow-500";
                        stText.innerText = "Bidding Paused";
                        stText.className = "text-yellow-400 font-extrabold uppercase";
                        pauseBtn.innerText = "Resume Bidding";
                    }

                    // Render Bids Stream logs
                    const bidsStream = document.getElementById('admin-bids-stream');
                    bidsStream.innerHTML = '';

                    if (data.bid_history && data.bid_history.length > 0) {
                        data.bid_history.forEach(log => {
                            const div = document.createElement('div');
                            div.className = "flex items-center justify-between p-2 rounded-lg bg-white/5 border border-white/5 text-[10px]";
                            div.innerHTML = `
                                <div class="flex items-center gap-1.5">
                                    <span class="text-gold-400 font-extrabold">₹${log.bid_amount}</span>
                                    <span class="text-gray-300">${log.team_name}</span>
                                </div>
                                <span class="text-[8px] text-gray-500 font-mono">${log.bid_time}</span>
                            `;
                            bidsStream.appendChild(div);
                        });
                    } else {
                        bidsStream.innerHTML = `<div class="text-center text-[9px] text-gray-600 py-3 font-semibold uppercase tracking-wider">No bids placed.</div>`;
                    }

                } else {
                    blockButtons.forEach(btn => {
                        btn.disabled = false;
                        btn.classList.remove('opacity-30', 'cursor-not-allowed');
                        btn.classList.add('hover:bg-gold-400', 'active:scale-95');
                    });

                    standbyBox.classList.remove('hidden');
                    playerCard.classList.add('hidden');
                    bidControlCard.classList.add('hidden');
                    activePlayerId = null;
                    lastBiddingStatus = 'Idle';
                    lastBidAmount = 0;
                }

            } catch (error) {
                console.error("Auctioneer Sync Error:", error);
            }
        }

        // Action: Bring a Player to Block
        async function bringToBlock(playerId) {
            if (activePlayerId !== null) {
                showToast("Finish current auction before blocking another player!", "error");
                return;
            }

            const basePriceInput = document.getElementById('base_' + playerId);
            const basePrice = basePriceInput ? parseInt(basePriceInput.value) : 0;

            if (isNaN(basePrice) || basePrice < 50) {
                showToast("Please set a valid starting base price (minimum ₹50).", "error");
                return;
            }

            const formData = new FormData();
            formData.append('action', 'start');
            formData.append('player_id', playerId);
            formData.append('base_price', basePrice);

            try {
                const response = await fetch('../api/admin_control.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast(result.message || "Player is now on the block!", "success");
                    fetchAuctioneerState();
                } else {
                    showToast(result.error || "Failed to bring player to block.", "error");
                }
            } catch (error) {
                showToast("Server connection error.", "error");
                console.error(error);
            }
        }

        // Action: Sold, Unsold, Pause Actions
        async function triggerAuctionAction(actionName) {
            const formData = new FormData();
            formData.append('action', actionName);

            try {
                const response = await fetch('../api/admin_control.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast(result.message || "Action processed successfully!", "success");
                    
                    // Trigger sound instantly for quick UI feedback
                    if (actionName === 'sold') {
                        SMCLSoundEngine.playSold();
                    } else if (actionName === 'unsold') {
                        SMCLSoundEngine.playUnsold();
                    }
                    
                    // If player is sold or marked unsold, trigger full page reload on available list 
                    // to dynamically remove the player from the left panel pool immediately
                    if (actionName === 'sold' || actionName === 'unsold') {
                        setTimeout(() => {
                            window.location.reload();
                        }, 850);
                    } else {
                        fetchAuctioneerState();
                    }
                } else {
                    showToast(result.error || "Action rejected by server.", "error");
                }
            } catch (error) {
                showToast("Server connection error.", "error");
                console.error(error);
            }
        }

        // Toast Notification System
        function showToast(message, type = "success") {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            toast.className = `p-4 rounded-xl border flex items-center gap-3 transition-all duration-300 transform translate-x-20 opacity-0 ${
                type === 'success' 
                    ? 'bg-gold-950/85 border-gold-500 text-gold-300 shadow-md shadow-gold-500/10' 
                    : 'bg-red-950/85 border-red-500 text-red-300 shadow-md shadow-red-500/10'
            }`;
            
            toast.style.backdropFilter = "blur(12px)";
            toast.innerHTML = `
                <span class="text-base">${type === 'success' ? '✔️' : '🚨'}</span>
                <span class="text-xs font-semibold leading-relaxed">${message}</span>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('translate-x-20', 'opacity-0');
            }, 50);

            setTimeout(() => {
                toast.classList.add('translate-x-20', 'opacity-0');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3500);
        }
    </script>
    <?php 
        $uploadPath = "../public/uploads/";
        require_once '../public/components/modals.php'; 
    ?>
</body>
</html>
