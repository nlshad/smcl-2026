<?php
// public/index.php
session_start();
require_once '../config/db.php';

// Fetch registration status
$stmt = $pdo->prepare("SELECT registration_enabled FROM auction_state WHERE id = 1");
$stmt->execute();
$regStatus = $stmt->fetch();
$registrationEnabled = $regStatus ? (bool)$regStatus['registration_enabled'] : true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMCL 2026 — Live Auction Arena</title>
    <?php require_once 'components/ui_head.php'; ?>
</head>
<body class="text-gray-150 min-h-screen flex flex-col justify-between">
    
    <!-- Top Navigation Bar -->
    <header class="w-full glass-panel border-b border-gold-500/10 px-4 py-3 sm:px-6 sm:py-4 flex items-center justify-between z-10 sticky top-0">
        <!-- Logo -->
        <div class="flex items-center gap-2 sm:gap-3">
            <img src="uploads/league_logo.png" alt="SMCL Logo" class="w-8 h-8 sm:w-9 sm:h-9 object-contain">
            <div>
                <h1 class="text-lg sm:text-xl font-black uppercase tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-gold-300 via-gold-400 to-amber-600 leading-none">
                    SMCL 2026
                </h1>
                <p class="text-[8px] sm:text-[9px] text-gray-500 uppercase tracking-widest font-bold mt-0.5">Panamaram Turf</p>
            </div>
        </div>

        <!-- Live Auction Status Indicator -->
        <div class="flex items-center gap-2 sm:gap-4">
            <button id="sound-toggle-btn" onclick="toggleMute()" class="flex items-center justify-center bg-black/40 border border-gold-500/10 hover:border-gold-500/35 w-8 h-8 rounded-full text-xs transition duration-200" title="Toggle Sound Effects">
                <i id="sound-icon" class="fa-solid fa-volume-high text-xs sm:text-sm text-gold-400"></i>
            </button>

            <div class="hidden sm:flex items-center gap-2 bg-black/40 border border-white/5 rounded-full px-3 py-1 text-xs">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse live-dot" id="status-light"></span>
                <span class="text-gray-400 font-semibold tracking-wider uppercase text-[10px]" id="status-text">Live Arena</span>
            </div>

            <!-- Login Quick Redirects -->
            <div class="flex gap-1.5 sm:gap-2 text-[9px] sm:text-xs">
                <!-- PWA Install Button -->
                <button id="pwa-install-btn" class="hidden text-[9px] sm:text-[10px] font-bold uppercase tracking-wider bg-gold-950/40 border border-gold-500/20 text-gold-400 hover:bg-gold-500/10 hover:border-gold-500/40 px-2.5 py-1.5 sm:px-3.5 sm:py-2 rounded-lg transition flex items-center justify-center gap-1">
                    <i class="fa-solid fa-cloud-arrow-down text-gold-400"></i> App
                </button>
                <?php if ($registrationEnabled): ?>
                    <a href="register.php" class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider bg-gold-950/40 border border-gold-500/20 text-gold-400 hover:bg-gold-500/10 hover:border-gold-500/40 px-2.5 py-1.5 sm:px-3.5 sm:py-2 rounded-lg transition flex items-center justify-center">
                        Register
                    </a>
                <?php else: ?>
                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider bg-red-950/20 border border-red-500/20 text-red-400 px-2.5 py-1.5 sm:px-3.5 sm:py-2 rounded-lg cursor-not-allowed flex items-center justify-center">
                        Closed
                    </span>
                <?php endif; ?>
                <a href="login.php" class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider bg-gold-500 hover:bg-gold-400 text-black px-2.5 py-1.5 sm:px-3.5 sm:py-2 rounded-lg transition font-extrabold shadow-md shadow-gold-500/5 flex items-center justify-center">
                    Portals
                </a>
            </div>
        </div>
    </header>

    <!-- Main Live Dashboard Arena -->
    <main class="flex-grow p-4 md:p-6 max-w-7xl w-full mx-auto grid grid-cols-12 gap-6 relative">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(212,163,12,0.03)_0%,transparent_75%)] pointer-events-none"></div>

        <!-- LEFT SIDE: Bento-Grid Auction Section (8 Cols) -->
        <div class="col-span-12 lg:col-span-8 grid grid-cols-12 gap-6" id="auction-grid">
            
            <!-- Standard Standby Box (Will show when current_player is NULL) -->
            <div id="standby-box" class="col-span-12 glass-panel rounded-2xl p-10 text-center flex flex-col items-center justify-center border border-gold-500/10 min-h-[450px]">
                <i class="fa-solid fa-baseball-bat-ball text-6xl text-gold-400 animate-bounce mb-4 block"></i>
                <h2 class="text-2xl font-extrabold text-gold-400">SMCL Live Auction Desk</h2>
                <p class="text-gray-400 max-w-md mt-2 text-sm">
                    Welcome to the Shamsu Memorial Cricket League Franchise Auction. Bidding will commence shortly as the Auctioneer brings the next player to the block.
                </p>
                <div class="mt-6 flex gap-4">
                    <span class="text-xs text-gray-500 uppercase tracking-widest bg-white/5 border border-white/5 px-3 py-1.5 rounded-lg">
                        Venue: Panamaram Turf
                    </span>
                    <span class="text-xs text-gray-500 uppercase tracking-widest bg-white/5 border border-white/5 px-3 py-1.5 rounded-lg">
                        Date: 29 May 2026
                    </span>
                </div>
            </div>

            <!-- Active Player Profile Bento (5 Cols) -->
            <div id="player-card" class="hidden col-span-12 md:col-span-5 glass-panel rounded-2xl overflow-hidden border border-gold-500/15 flex flex-col justify-between relative group">
                <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-gold-500/10 to-transparent pointer-events-none"></div>
                <!-- Profile Image -->
                <div class="p-5 flex-grow flex flex-col items-center justify-center">
                    <div class="w-36 h-40 rounded-xl overflow-hidden border-2 border-gold-500/30 bg-black/60 shadow-lg relative flex items-center justify-center cursor-zoom-in" onclick="openImageLightbox(document.getElementById('player-image').src, document.getElementById('player-name').innerText);">
                        <img src="uploads/player_placeholder.jpg" id="player-image" alt="Player Image" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='uploads/player_placeholder.jpg';">
                        <div class="absolute top-2 right-2 bg-black/70 px-2 py-0.5 rounded text-[8px] border border-white/10 uppercase tracking-wider text-gold-400" id="player-status-tag">
                            Bidding
                        </div>
                    </div>
                    <!-- Player Name and Origin -->
                    <div class="text-center mt-4">
                        <span class="text-[9px] uppercase tracking-widest text-gold-400 font-bold bg-gold-950/50 border border-gold-500/20 px-2.5 py-1 rounded-md" id="player-role">
                            ALL-ROUNDER
                        </span>
                        <h2 class="text-2xl font-black text-white mt-2 tracking-tight" id="player-name">---</h2>
                        <p class="text-xs text-gray-400 mt-1 flex items-center justify-center gap-1">
                            <i class="fa-solid fa-location-dot text-gray-500"></i>
                            <span id="player-place">Wayanad</span>
                        </p>
                    </div>
                </div>
                <!-- Bottom Stats -->
                <div class="bg-black/40 border-t border-white/5 px-5 py-3.5 flex justify-between items-center text-xs">
                    <span class="text-gray-500 font-semibold uppercase tracking-wider">Base Price</span>
                    <span class="text-gold-400 font-extrabold text-base" id="player-base-price">₹100</span>
                </div>
            </div>

            <!-- Active Bid Center Console Bento (7 Cols) -->
            <div id="bid-card" class="hidden col-span-12 md:col-span-7 flex flex-col gap-6">
                <!-- Current High Bid Box -->
                <div class="glass-panel rounded-2xl p-6 border border-gold-500/15 flex flex-col justify-between relative overflow-hidden min-h-[200px] flex-grow">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_right,rgba(218,165,32,0.06)_0%,transparent_50%)] pointer-events-none"></div>
                    <div>
                        <span class="text-[9px] uppercase tracking-widest font-bold text-gray-500">Active High Bid</span>
                        <div class="flex items-baseline mt-1 gap-2">
                            <span class="text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-gold-300 via-gold-400 to-amber-500 tracking-tight transition duration-200" id="current-bid">
                                ₹0
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-white/5 pt-4">
                        <span class="text-[9px] uppercase tracking-widest font-bold text-gray-500">Leading Franchise</span>
                        <div class="flex items-center gap-3 mt-1.5">
                            <div id="leading-team-logo-container" class="w-9 h-9 rounded bg-black/40 border border-gold-500/20 flex items-center justify-center overflow-hidden p-0.5">
                                <i class="fa-solid fa-crown text-xl text-gold-400" id="leading-team-crown"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-white" id="leading-team">---</h3>
                                <p class="text-[9px] text-gold-500 uppercase tracking-widest font-bold">High Bidholder</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Bids History Timeline -->
                <div class="glass-panel rounded-2xl p-5 border border-gold-500/15 flex flex-col min-h-[160px]">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2 border-b border-white/5 pb-2">
                        <i class="fa-solid fa-chart-line text-xs text-gold-400"></i> Bid Flow History
                    </h3>
                    <div class="flex-grow overflow-y-auto pr-1 space-y-2.5 max-h-36" id="bid-history-list">
                        <!-- Polled Bids will append here -->
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT SIDE: Franchise Standings Leaderboard (4 Cols) -->
        <aside class="col-span-12 lg:col-span-4 glass-panel rounded-2xl p-5 border border-gold-500/15 flex flex-col">
            <div class="border-b border-white/5 pb-3 mb-4">
                <h3 class="text-base font-bold text-gold-400 flex items-center gap-2">
                    <i class="fa-solid fa-wallet text-base text-gray-400"></i> Franchise Purses
                </h3>
                <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-semibold">Live Budget & Squad Sizes</p>
            </div>

            <!-- Team Leaderboard Grid -->
            <div class="flex-grow space-y-3" id="teams-leaderboard">
                <!-- Polled leaderboard will render here -->
            </div>
        </aside>

        <!-- COMPLETED PLAYERS SECTION -->
        <section class="col-span-12 glass-panel rounded-2xl p-6 border border-gold-500/10 mt-2 relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom,rgba(212,163,12,0.02)_0%,transparent_70%)] pointer-events-none"></div>
            
            <div class="border-b border-white/5 pb-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-black text-gold-400 flex items-center gap-2 uppercase tracking-tight">
                        <i class="fa-solid fa-clipboard-list text-base text-gray-400"></i> Player Auctions Status
                    </h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">Real-time status of all verified player auctions</p>
                </div>
                <!-- Search and Filters Container -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3.5 w-full sm:w-auto">
                    <!-- Filter Chips -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 shrink-0 scrollbar-none" id="public-status-filter-container">
                        <button onclick="setStatusFilter('all')" class="status-chip px-3 py-1.5 rounded-lg border text-[10px] uppercase font-bold tracking-wider transition bg-gold-500/10 border-gold-500 text-gold-400" data-filter="all">ALL</button>
                        <button onclick="setStatusFilter('Sold')" class="status-chip px-3 py-1.5 rounded-lg border text-[10px] uppercase font-bold tracking-wider transition border-white/5 bg-zinc-900 text-gray-400 hover:border-white/10 hover:text-white" data-filter="Sold">SOLD</button>
                        <button onclick="setStatusFilter('Unsold')" class="status-chip px-3 py-1.5 rounded-lg border text-[10px] uppercase font-bold tracking-wider transition border-white/5 bg-zinc-900 text-gray-400 hover:border-white/10 hover:text-white" data-filter="Unsold">UNSOLD</button>
                        <button onclick="setStatusFilter('Available')" class="status-chip px-3 py-1.5 rounded-lg border text-[10px] uppercase font-bold tracking-wider transition border-white/5 bg-zinc-900 text-gray-400 hover:border-white/10 hover:text-white" data-filter="Available">AVAILABLE</button>
                    </div>
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-56">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]"></i>
                        <input type="text" id="player-search-input" oninput="renderCompletedPlayers()" placeholder="Search players, roles, places..."
                               class="w-full bg-black/60 border border-white/10 rounded-xl pl-8 pr-3 py-1.5 text-[11px] text-white focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition placeholder-gray-600">
                    </div>
                </div>
            </div>

            <!-- Completed Players Grid (Adaptive cols) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="completed-players-grid">
                <!-- Dynamically populated completed cards -->
            </div>
            
            <!-- Standby Empty state inside completed table -->
            <div id="completed-empty-box" class="text-center text-[10px] text-gray-500 py-10 uppercase tracking-widest font-bold hidden">
                No finalized auctions yet. Bids are ongoing!
            </div>
        </section>

    </main>

    <!-- Footer Area -->
    <footer class="w-full glass-panel border-t border-gold-500/10 px-6 py-4 text-center text-xs text-gray-500 mt-6">
        <p>© 2026 Shamsu Memorial Cricket League (SMCL). Built for premium turf events.</p>
    </footer>

    <script>
        // Track the current bidding state parameters locally to detect changes
        let activePlayerId = null;
        let lastBidAmount = 0;
        let isMuted = false;
        let allCompletedPlayers = [];
        let activeStatusFilter = 'all';

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
                    
                    // Satisfaction gavel-click knock sound
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
                    
                    // Double Gavel Strike
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
                    
                    // Premium Gold Major Celebration Arpeggio (C5 -> E5 -> G5 -> C6)
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
                    
                    // Sad downward slide tone
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

        // Initialize audio engine on first click anywhere (bypasses browser autoplay limits)
        document.addEventListener('click', () => {
            SMCLSoundEngine.init();
        }, { once: true });

        // Fetch live state immediately on load and then every 1.5 seconds (1500ms)
        fetchState();
        setInterval(fetchState, 1500);

        async function fetchState() {
            try {
                const response = await fetch('../api/get_live_state.php');
                const data = await response.json();

                if (data.error) {
                    console.error(data.error);
                    return;
                }

                // 1. Update Status Header Bar
                const statusLight = document.getElementById('status-light');
                const statusText = document.getElementById('status-text');

                if (data.status === 'Bidding') {
                    statusLight.className = "w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse live-dot";
                    statusText.innerText = "Bidding Active";
                    statusText.className = "text-red-400 font-extrabold tracking-wider uppercase text-[10px]";
                } else if (data.status === 'Paused') {
                    statusLight.className = "w-2.5 h-2.5 rounded-full bg-yellow-500 animate-pulse";
                    statusLight.style.boxShadow = "0 0 10px #eab308";
                    statusText.innerText = "Bidding Paused";
                    statusText.className = "text-yellow-400 font-extrabold tracking-wider uppercase text-[10px]";
                } else {
                    statusLight.className = "w-2.5 h-2.5 rounded-full bg-gray-500";
                    statusLight.style.boxShadow = "none";
                    statusText.innerText = "Arena Idle";
                    statusText.className = "text-gray-500 font-extrabold tracking-wider uppercase text-[10px]";
                }

                // 2. Control Layout Visibility based on Active Player
                const standbyBox = document.getElementById('standby-box');
                const playerCard = document.getElementById('player-card');
                const bidCard = document.getElementById('bid-card');

                if (data.current_player && data.status !== 'Idle') {
                    standbyBox.classList.add('hidden');
                    playerCard.classList.remove('hidden');
                    bidCard.classList.remove('hidden');

                    const newPlayerId = parseInt(data.current_player.id);

                    // Sync Player details
                    document.getElementById('player-name').innerText = data.current_player.name;
                    document.getElementById('player-role').innerText = data.current_player.role.toUpperCase();
                    document.getElementById('player-place').innerText = data.current_player.place;
                    document.getElementById('player-base-price').innerText = "₹" + data.current_player.base_price;
                    document.getElementById('player-image').src = "uploads/" + data.current_player.profile_image;
                    
                    const tag = document.getElementById('player-status-tag');
                    tag.innerText = data.status === 'Paused' ? 'PAUSED' : 'BIDDING';
                    tag.className = data.status === 'Paused' 
                        ? 'absolute top-2 right-2 bg-yellow-500/80 px-2 py-0.5 rounded text-[8px] border border-yellow-400/30 uppercase tracking-wider text-black font-extrabold' 
                        : 'absolute top-2 right-2 bg-red-600/80 px-2 py-0.5 rounded text-[8px] border border-red-500/30 uppercase tracking-wider text-white';

                    // Sync Bidding Box details
                    const bidText = document.getElementById('current-bid');
                    bidText.innerText = "₹" + data.highest_bid;

                    // Micro-animation flash if bid changes & play Sound!
                    if (activePlayerId !== newPlayerId) {
                        activePlayerId = newPlayerId;
                        lastBidAmount = data.highest_bid;
                    } else {
                        if (lastBidAmount !== 0 && data.highest_bid > lastBidAmount) {
                            bidText.classList.add('scale-105', 'text-yellow-300');
                            setTimeout(() => {
                                bidText.classList.remove('scale-105', 'text-yellow-300');
                            }, 400);
                            SMCLSoundEngine.playBid();
                        }
                        lastBidAmount = data.highest_bid;
                    }

                    const logoContainer = document.getElementById('leading-team-logo-container');
                    const leadingTeamEl = document.getElementById('leading-team');
                    if (data.leading_team_name) {
                        leadingTeamEl.innerText = data.leading_team_name;
                        leadingTeamEl.className = "text-base font-extrabold text-white cursor-pointer hover:text-gold-400 transition";
                        leadingTeamEl.onclick = () => openTeamDetailsModal(data.leading_team_id);
                        const logoSrc = data.leading_team_logo ? "uploads/" + data.leading_team_logo : "uploads/team_placeholder.jpg";
                        logoContainer.innerHTML = `<img src="${logoSrc}" class="w-full h-full object-contain">`;
                    } else {
                        leadingTeamEl.innerText = "No bids placed yet";
                        leadingTeamEl.className = "text-base font-extrabold text-white";
                        leadingTeamEl.onclick = null;
                        logoContainer.innerHTML = `<i class="fa-solid fa-crown text-xl text-gold-400" id="leading-team-crown"></i>`;
                    }
                    // Sync Bids History Feed
                    const historyList = document.getElementById('bid-history-list');
                    historyList.innerHTML = '';

                    if (data.bid_history && data.bid_history.length > 0) {
                        data.bid_history.forEach(log => {
                            const li = document.createElement('div');
                            li.className = "flex items-center justify-between p-2.5 rounded-lg bg-white/5 border border-white/5 text-xs transition hover:bg-white/10";
                            li.innerHTML = `
                                <div class="flex items-center gap-2">
                                    <span class="text-gold-400 font-bold">₹${log.bid_amount}</span>
                                    <span class="text-gray-300 font-medium">${log.team_name}</span>
                                </div>
                            `;
                            historyList.appendChild(li);
                        });
                    } else {
                        historyList.innerHTML = `
                            <div class="text-center text-[10px] text-gray-500 py-6 uppercase font-semibold tracking-wider">
                                Waiting for opening bid...
                            </div>
                        `;
                    }

                } else {
                    // Player has transitioned off the block!
                    if (activePlayerId !== null) {
                        const oldPlayerId = activePlayerId;
                        activePlayerId = null;
                        lastBidAmount = 0;
                        checkPastPlayerStatus(oldPlayerId);
                    }

                    standbyBox.classList.remove('hidden');
                    playerCard.classList.add('hidden');
                    bidCard.classList.add('hidden');
                }

                // 3. Sync Leaderboards Standings
                const leaderboard = document.getElementById('teams-leaderboard');
                leaderboard.innerHTML = '';

                if (data.teams && data.teams.length > 0) {
                    data.teams.forEach(team => {
                        const spent = (team.total_purse - team.remaining_purse);
                        const isLeading = (data.leading_team_id && data.leading_team_id === parseInt(team.id));

                        const div = document.createElement('div');
                        div.className = `p-3.5 rounded-xl border transition cursor-pointer flex flex-col justify-between ${
                            isLeading 
                                ? 'bg-gold-500/10 border-gold-500 shadow-md shadow-gold-500/5 hover:bg-gold-500/20' 
                                : 'bg-black/30 border-white/5 hover:border-white/10 hover:bg-white/5'
                        }`;
                        div.onclick = () => openTeamDetailsModal(team.id);
                        const logoSrc = team.logo ? "uploads/" + team.logo : "uploads/team_placeholder.jpg";
                        div.innerHTML = `
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <img src="${logoSrc}" class="w-7 h-7 rounded object-contain bg-black/40 p-0.5 border border-white/10 shadow-md">
                                    <span class="text-sm font-bold text-white">${team.team_name}</span>
                                    ${isLeading ? '<span class="text-[8px] uppercase tracking-widest font-extrabold text-gold-400 bg-gold-950/60 px-1.5 py-0.5 rounded border border-gold-500/20 animate-pulse">High Bidder</span>' : ''}
                                </div>
                                <span class="text-xs font-mono font-bold text-gold-400">₹${team.remaining_purse} left</span>
                            </div>
                            <div class="flex items-center justify-between mt-2.5 text-[10px] text-gray-400">
                                <span>Squad: <strong class="text-gray-300 font-bold">${team.current_squad_size}/${team.max_squad_size} players</strong></span>
                                <span>Spent: <strong class="text-gray-300 font-mono font-bold">₹${spent}</strong></span>
                            </div>
                        `;
                        leaderboard.appendChild(div);
                    });
                } else {
                    leaderboard.innerHTML = `
                        <div class="text-center text-xs text-gray-500 py-6">
                            No teams registered yet.
                        </div>
                    `;
                }

                // 4. Sync Completed Player Auctions
                allCompletedPlayers = data.all_players || [];
                renderCompletedPlayers();

            } catch (error) {
                console.error("Dashboard synchronization error:", error);
            }
        }

        // Filter handler
        function setStatusFilter(filterValue) {
            activeStatusFilter = filterValue;
            
            // Update chip styles
            const chips = document.querySelectorAll('#public-status-filter-container .status-chip');
            chips.forEach(chip => {
                if (chip.getAttribute('data-filter') === filterValue) {
                    chip.className = "status-chip px-3 py-1.5 rounded-lg border text-[10px] uppercase font-bold tracking-wider transition bg-gold-500/10 border-gold-500 text-gold-400";
                } else {
                    chip.className = "status-chip px-3 py-1.5 rounded-lg border text-[10px] uppercase font-bold tracking-wider transition border-white/5 bg-zinc-900 text-gray-400 hover:border-white/10 hover:text-white";
                }
            });

            renderCompletedPlayers();
        }

        // Render completed players list dynamically (supports real-time search filtering)
        function renderCompletedPlayers() {
            const completedGrid = document.getElementById('completed-players-grid');
            const completedEmpty = document.getElementById('completed-empty-box');
            const searchInput = document.getElementById('player-search-input');
            const searchQuery = searchInput ? searchInput.value.toLowerCase().trim() : '';

            completedGrid.innerHTML = '';

            let soldCount = 0;
            let unsoldCount = 0;
            let availableCount = 0;

            // Compute overall totals from unfiltered array
            allCompletedPlayers.forEach(p => {
                if (p.auction_status === 'Sold') soldCount++;
                else if (p.auction_status === 'Unsold') unsoldCount++;
                else if (p.auction_status === 'Available') availableCount++;
            });

            // Update chip counts
            const allChip = document.querySelector('#public-status-filter-container [data-filter="all"]');
            if (allChip) allChip.innerText = `ALL (${allCompletedPlayers.length})`;

            const soldChip = document.querySelector('#public-status-filter-container [data-filter="Sold"]');
            if (soldChip) soldChip.innerText = `SOLD (${soldCount})`;

            const unsoldChip = document.querySelector('#public-status-filter-container [data-filter="Unsold"]');
            if (unsoldChip) unsoldChip.innerText = `UNSOLD (${unsoldCount})`;

            const availChip = document.querySelector('#public-status-filter-container [data-filter="Available"]');
            if (availChip) availChip.innerText = `AVAILABLE (${availableCount})`;

            // Apply search query and status filter
            const filteredPlayers = allCompletedPlayers.filter(p => {
                // Status Filter
                if (activeStatusFilter !== 'all' && p.auction_status !== activeStatusFilter) {
                    return false;
                }

                if (!searchQuery) return true;
                return (
                    p.name.toLowerCase().includes(searchQuery) ||
                    p.role.toLowerCase().includes(searchQuery) ||
                    p.place.toLowerCase().includes(searchQuery) ||
                    (p.team_name && p.team_name.toLowerCase().includes(searchQuery))
                );
            });

            if (filteredPlayers.length > 0) {
                completedEmpty.classList.add('hidden');
                completedGrid.classList.remove('hidden');

                filteredPlayers.forEach(p => {
                    const card = document.createElement('div');
                    card.className = "glass-panel rounded-2xl p-5 border border-gold-500/10 hover:border-gold-500/20 cursor-pointer transition-all duration-300 relative group flex flex-col justify-between overflow-hidden shadow-lg shadow-black/40";
                    card.onclick = () => openPlayerDetailsModal(p.id);
                    
                    card.innerHTML = `
                        <!-- Top Info Row -->
                        <div class="flex items-center justify-between pb-4 border-b border-white/5">
                            <div class="flex items-center gap-3.5">
                                <!-- Player Profile Picture -->
                                <div class="w-12 h-12 rounded-xl overflow-hidden border border-gold-500/25 bg-black/60 shadow-md cursor-zoom-in" onclick="event.stopPropagation(); openImageLightbox('uploads/' + (p.profile_image ? p.profile_image : 'player_placeholder.jpg'), `${p.name}`);">
                                    <img src="uploads/${p.profile_image ? p.profile_image : 'player_placeholder.jpg'}" alt="${p.name}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='uploads/player_placeholder.jpg';">
                                </div>
                                <!-- Name & Details -->
                                <div>
                                    <h4 class="text-sm font-extrabold text-white group-hover:text-gold-400 transition-colors">${p.name}</h4>
                                    <p class="text-[9px] text-gray-400 mt-0.5">${p.role} &bull; ${p.place}</p>
                                </div>
                            </div>
                            <!-- Pill Badge -->
                            <span class="px-2 py-0.5 rounded text-[8px] uppercase tracking-wider font-extrabold ${
                                p.auction_status === 'Sold' 
                                    ? 'bg-emerald-500/10 border border-emerald-500/25 text-emerald-400' 
                                    : (p.auction_status === 'Unsold' 
                                        ? 'bg-red-500/10 border border-red-500/25 text-red-400' 
                                        : 'bg-blue-500/10 border border-blue-500/25 text-blue-400')
                            }">
                                ${p.auction_status}
                            </span>
                        </div>

                        <!-- Bottom Price and Team Grid -->
                        <div class="grid grid-cols-3 gap-2 pt-3.5 text-center items-center">
                            <!-- Base Price -->
                            <div class="border-r border-white/5 flex flex-col">
                                <span class="text-[8px] uppercase tracking-wider text-gray-500 font-bold">Base Price</span>
                                <span class="text-xs font-black text-gray-200 mt-1 font-mono">₹${p.base_price}</span>
                            </div>
                            <!-- Final Price -->
                            <div class="border-r border-white/5 flex flex-col">
                                <span class="text-[8px] uppercase tracking-wider text-gray-500 font-bold">Final Price</span>
                                <span class="text-xs font-black text-gold-400 mt-1 font-mono">${p.auction_status === 'Sold' ? '₹' + p.sold_price : '—'}</span>
                            </div>
                            <!-- Team -->
                            <div class="flex flex-col items-center justify-center ${p.auction_status === 'Sold' ? 'cursor-pointer hover:bg-white/5 p-1 rounded transition' : ''}" ${p.auction_status === 'Sold' ? `onclick="event.stopPropagation(); openTeamDetailsModal(${p.team_id})"` : ''}>
                                <span class="text-[8px] uppercase tracking-wider text-gray-500 font-bold">Team</span>
                                ${p.auction_status === 'Sold' 
                                    ? `<div class="flex items-center gap-1.5 mt-1 justify-center max-w-[90px]">
                                         <img src="uploads/${p.team_logo ? p.team_logo : 'team_placeholder.jpg'}" class="w-4 h-4 rounded object-contain bg-black/40 p-0.5 border border-white/10">
                                         <span class="text-[10px] font-extrabold text-white tracking-tight truncate">${p.team_name}</span>
                                       </div>` 
                                    : '<span class="text-xs font-bold text-gray-600 mt-1">—</span>'
                                }
                            </div>
                        </div>
                    `;
                    completedGrid.appendChild(card);
                });
            } else {
                completedEmpty.classList.remove('hidden');
                completedEmpty.innerText = searchQuery ? "No completed players match your query." : "No finalized auctions yet. Bids are ongoing!";
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

        // PWA Install Prompt Script
        let deferredPrompt;
        const installBtn = document.getElementById('pwa-install-btn');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (installBtn) {
                installBtn.classList.remove('hidden');
            }
        });

        if (installBtn) {
            installBtn.addEventListener('click', (e) => {
                if (!deferredPrompt) return;
                installBtn.classList.add('hidden');
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User installed the PWA app');
                    }
                    deferredPrompt = null;
                });
            });
        }

    </script>
    <?php 
        $uploadPath = "uploads/";
        require_once 'components/modals.php'; 
    ?>
</body>
</html>
