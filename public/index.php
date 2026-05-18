<?php
// public/index.php
session_start();
require_once '../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMCL 2026 — Live Auction Arena</title>
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
            background: radial-gradient(circle at center, #141414 0%, #020202 100%);
            overflow-x: hidden;
        }
        h1, h2, h3, h4, .font-title {
            font-family: 'Outfit', sans-serif;
        }
        .glass-panel {
            background: rgba(22, 22, 22, 0.65);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(212, 163, 12, 0.1);
            box-shadow: 0 10px 30px 0 rgba(0, 0, 0, 0.55);
        }
        .live-dot {
            box-shadow: 0 0 10px #ef4444;
        }
        /* Custom scrollbar for timeline */
        ::-webkit-scrollbar {
            width: 4px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(212, 163, 12, 0.3);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(212, 163, 12, 0.6);
        }
        @keyframes pulse-gold {
            0%, 100% { transform: scale(1); filter: drop-shadow(0 0 0px rgba(212, 163, 12, 0)); }
            50% { transform: scale(1.02); filter: drop-shadow(0 0 15px rgba(212, 163, 12, 0.45)); }
        }
        .pulse-card {
            animation: pulse-gold 3s infinite ease-in-out;
        }
    </style>
</head>
<body class="text-gray-150 min-h-screen flex flex-col justify-between">
    
    <!-- Top Navigation Bar -->
    <header class="w-full glass-panel border-b border-gold-500/10 px-6 py-4 flex items-center justify-between z-10 sticky top-0">
        <!-- Logo -->
        <div class="flex items-center gap-3">
            <span class="text-xl">🏆</span>
            <div>
                <h1 class="text-xl font-black uppercase tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-gold-300 via-gold-400 to-amber-600">
                    SMCL 2026
                </h1>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-bold">Panamaram Turf</p>
            </div>
        </div>

        <!-- Live Auction Status Indicator -->
        <div class="flex items-center gap-4">
            <div class="hidden sm:flex items-center gap-2 bg-black/40 border border-white/5 rounded-full px-3 py-1 text-xs">
                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse live-dot" id="status-light"></span>
                <span class="text-gray-400 font-semibold tracking-wider uppercase text-[10px]" id="status-text">Live Arena</span>
            </div>

            <!-- Login Quick Redirects -->
            <div class="flex gap-2">
                <a href="register.php" class="text-[10px] font-bold uppercase tracking-wider bg-gold-950/40 border border-gold-500/20 text-gold-400 hover:bg-gold-500/10 hover:border-gold-500/40 px-3.5 py-2 rounded-lg transition">
                    Register
                </a>
                <a href="login.php" class="text-[10px] font-bold uppercase tracking-wider bg-gold-500 hover:bg-gold-400 text-black px-3.5 py-2 rounded-lg transition font-extrabold shadow-md shadow-gold-500/5">
                    Portals
                </a>
            </div>
        </div>
    </header>

    <!-- Main Live Dashboard Arena -->
    <main class="flex-grow p-4 md:p-6 max-w-7xl w-full mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 relative">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(212,163,12,0.03)_0%,transparent_75%)] pointer-events-none"></div>

        <!-- LEFT SIDE: Bento-Grid Auction Section (8 Cols) -->
        <div class="lg:col-span-8 grid grid-cols-1 md:grid-cols-12 gap-6" id="auction-grid">
            
            <!-- Standard Standby Box (Will show when current_player is NULL) -->
            <div id="standby-box" class="col-span-12 glass-panel rounded-2xl p-10 text-center flex flex-col items-center justify-center border border-gold-500/10 min-h-[450px]">
                <span class="text-6xl animate-bounce mb-4">🏏</span>
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
            <div id="player-card" class="hidden md:col-span-5 glass-panel rounded-2xl overflow-hidden border border-gold-500/15 flex flex-col justify-between relative group">
                <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-gold-500/10 to-transparent pointer-events-none"></div>
                <!-- Profile Image -->
                <div class="p-5 flex-grow flex flex-col items-center justify-center">
                    <div class="w-36 h-40 rounded-xl overflow-hidden border-2 border-gold-500/30 bg-black/60 shadow-lg relative flex items-center justify-center">
                        <img src="uploads/player_placeholder.jpg" id="player-image" alt="Player Image" class="w-full h-full object-cover">
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
                        <p class="text-xs text-gray-400 mt-1" id="player-place">📍 Wayanad</p>
                    </div>
                </div>
                <!-- Bottom Stats -->
                <div class="bg-black/40 border-t border-white/5 px-5 py-3.5 flex justify-between items-center text-xs">
                    <span class="text-gray-500 font-semibold uppercase tracking-wider">Base Price</span>
                    <span class="text-gold-400 font-extrabold text-base" id="player-base-price">₹100</span>
                </div>
            </div>

            <!-- Active Bid Center Console Bento (7 Cols) -->
            <div id="bid-card" class="hidden md:col-span-7 grid grid-rows-12 gap-6">
                <!-- Current High Bid Box (7 rows equivalent) -->
                <div class="row-span-7 glass-panel rounded-2xl p-6 border border-gold-500/15 flex flex-col justify-between relative overflow-hidden">
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
                            <span class="text-2xl">👑</span>
                            <div>
                                <h3 class="text-base font-extrabold text-white" id="leading-team">---</h3>
                                <p class="text-[9px] text-gold-500 uppercase tracking-widest font-bold">High Bidholder</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Bids History Timeline (5 rows equivalent) -->
                <div class="row-span-5 glass-panel rounded-2xl p-5 border border-gold-500/15 flex flex-col">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2 border-b border-white/5 pb-2">
                        <span>📊</span> Bid Flow History
                    </h3>
                    <div class="flex-grow overflow-y-auto pr-1 space-y-2.5 max-h-36" id="bid-history-list">
                        <!-- Polled Bids will append here -->
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT SIDE: Franchise Standings Leaderboard (4 Cols) -->
        <aside class="lg:col-span-4 glass-panel rounded-2xl p-5 border border-gold-500/15 flex flex-col">
            <div class="border-b border-white/5 pb-3 mb-4">
                <h3 class="text-base font-bold text-gold-400 flex items-center gap-2">
                    <span>💳</span> Franchise Purses
                </h3>
                <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-semibold">Live Budget & Squad Sizes</p>
            </div>

            <!-- Team Leaderboard Grid -->
            <div class="flex-grow space-y-3" id="teams-leaderboard">
                <!-- Polled leaderboard will render here -->
            </div>
        </aside>

    </main>

    <!-- Footer Area -->
    <footer class="w-full glass-panel border-t border-gold-500/10 px-6 py-4 text-center text-xs text-gray-500 mt-6">
        <p>© 2026 Shamsu Memorial Cricket League (SMCL). Built for premium turf events.</p>
    </footer>

    <!-- Frontend Polling Controller -->
    <script>
        // Track the current bidding state parameters locally to detect changes
        let activePlayerId = null;
        let lastBidAmount = 0;

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

                    // Sync Player details
                    document.getElementById('player-name').innerText = data.current_player.name;
                    document.getElementById('player-role').innerText = data.current_player.role.toUpperCase();
                    document.getElementById('player-place').innerText = "📍 " + data.current_player.place;
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

                    // Micro-animation flash if bid changes
                    if (lastBidAmount !== 0 && data.highest_bid > lastBidAmount) {
                        bidText.classList.add('scale-105', 'text-yellow-300');
                        setTimeout(() => {
                            bidText.classList.remove('scale-105', 'text-yellow-300');
                        }, 400);
                    }
                    lastBidAmount = data.highest_bid;

                    document.getElementById('leading-team').innerText = data.leading_team_name || "No bids placed yet";

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
                                <span class="text-[9px] text-gray-500 font-mono">${log.bid_time}</span>
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
                    standbyBox.classList.remove('hidden');
                    playerCard.classList.add('hidden');
                    bidCard.classList.add('hidden');
                    lastBidAmount = 0;
                }

                // 3. Sync Leaderboards Standings
                const leaderboard = document.getElementById('teams-leaderboard');
                leaderboard.innerHTML = '';

                if (data.teams && data.teams.length > 0) {
                    data.teams.forEach(team => {
                        const spent = (team.total_purse - team.remaining_purse);
                        const isLeading = (data.leading_team_id && data.leading_team_id === parseInt(team.id));

                        const div = document.createElement('div');
                        div.className = `p-3.5 rounded-xl border transition flex flex-col justify-between ${
                            isLeading 
                                ? 'bg-gold-500/10 border-gold-500 shadow-md shadow-gold-500/5' 
                                : 'bg-black/30 border-white/5 hover:border-white/10'
                        }`;
                        div.innerHTML = `
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
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

            } catch (error) {
                console.error("Dashboard synchronization error:", error);
            }
        }
    </script>
</body>
</html>
