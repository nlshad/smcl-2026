<?php
// public/components/modals.php
// Requires $uploadPath to be defined by the parent script (e.g. "uploads/" or "../public/uploads/")
if (!isset($uploadPath)) {
    $uploadPath = "uploads/";
}
?>
<script>
    // Open Player Details Modal Popup
    async function openPlayerDetailsModal(playerId) {
        const modal = document.getElementById('player-details-modal');
        const content = document.getElementById('modal-content');
        
        try {
            const response = await fetch(`../api/get_player_details.php?player_id=${playerId}`);
            const data = await response.json();

            if (!data.success) {
                console.error(data.error);
                return;
            }

            const p = data.player;
            
            // Set profile info
            document.getElementById('modal-player-image').src = p.profile_image ? "<?php echo $uploadPath; ?>" + p.profile_image : "<?php echo $uploadPath; ?>player_placeholder.jpg";
            document.getElementById('modal-player-name').innerText = p.name;
            document.getElementById('modal-player-details').innerHTML = `${p.role.toUpperCase()} &bull; <i class="fa-solid fa-location-dot text-gray-500 text-[10px] inline-block align-middle mr-0.5"></i> ${p.place}`;
            document.getElementById('modal-base-price').innerText = "₹" + p.base_price;
            
            const statusTag = document.getElementById('modal-player-status-tag');
            statusTag.innerText = p.auction_status;
            
            if (p.auction_status === 'Sold') {
                statusTag.className = "px-2.5 py-0.5 rounded text-[8px] uppercase tracking-wider font-extrabold bg-emerald-500/10 border border-emerald-500/25 text-emerald-400";
                document.getElementById('modal-sold-price').innerText = "₹" + p.sold_price;
                document.getElementById('modal-team-name').innerText = p.team_name;
                document.getElementById('modal-bid-history-section').style.display = 'block';
                
                // Show franchise logo in blank space
                const logoContainer = document.getElementById('modal-player-team-logo-container');
                const logoImg = document.getElementById('modal-player-team-logo');
                if (logoContainer && logoImg) {
                    logoImg.src = p.team_logo ? "<?php echo $uploadPath; ?>" + p.team_logo : "<?php echo $uploadPath; ?>team_placeholder.jpg";
                    logoContainer.style.display = 'flex';
                }

                // Render full bid history list
                const listEl = document.getElementById('modal-bid-history-list');
                listEl.innerHTML = '';
                
                if (data.bids && data.bids.length > 0) {
                    data.bids.forEach(b => {
                        const row = document.createElement('div');
                        row.className = "flex items-center justify-between p-2.5 rounded-lg bg-white/5 border border-white/5 text-xs transition hover:bg-white/10";
                        row.innerHTML = `
                            <div class="flex items-center gap-2">
                                <span class="text-gold-400 font-extrabold">₹${b.bid_amount}</span>
                                <span class="text-gray-300 font-medium">${b.team_name}</span>
                            </div>
                            <span class="text-[9px] text-gray-500 font-mono">${b.bid_time}</span>
                        `;
                        listEl.appendChild(row);
                    });
                } else {
                    listEl.innerHTML = `<div class="text-center text-[10px] text-gray-500 py-4 uppercase font-semibold">Opening bid placed at base price of ₹${p.base_price}</div>`;
                }
            } else {
                // Unsold
                statusTag.className = "px-2.5 py-0.5 rounded text-[8px] uppercase tracking-wider font-extrabold bg-red-500/10 border border-red-500/25 text-red-400";
                document.getElementById('modal-sold-price').innerText = "—";
                document.getElementById('modal-team-name').innerText = "—";
                document.getElementById('modal-bid-history-section').style.display = 'none';

                // Hide franchise logo
                const logoContainer = document.getElementById('modal-player-team-logo-container');
                if (logoContainer) {
                    logoContainer.style.display = 'none';
                }
            }

            // Animate opening
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95');
            }, 50);

        } catch (e) {
            console.error("Failed to load player details modal:", e);
        }
    }

    function closeModal() {
        const modal = document.getElementById('player-details-modal');
        const content = document.getElementById('modal-content');
        content.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 150);
    }

    // Close on backdrop click
    document.addEventListener('DOMContentLoaded', () => {
        const pModal = document.getElementById('player-details-modal');
        if (pModal) {
            pModal.addEventListener('click', (e) => {
                if (e.target.id === 'player-details-modal') {
                    closeModal();
                }
            });
        }
        
        const tModal = document.getElementById('team-details-modal');
        if (tModal) {
            tModal.addEventListener('click', (e) => {
                if (e.target.id === 'team-details-modal') {
                    closeTeamModal();
                }
            });
        }
    });

    // Open Team details modal popup
    async function openTeamDetailsModal(teamId) {
        const modal = document.getElementById('team-details-modal');
        const content = document.getElementById('team-modal-content');
        
        try {
            const response = await fetch(`../api/get_team_details.php?team_id=${teamId}`);
            const data = await response.json();

            if (!data.success) {
                console.error(data.error);
                return;
            }

            const t = data.team;
            
            // Set Team info
            document.getElementById('modal-team-logo').src = t.logo ? "<?php echo $uploadPath; ?>" + t.logo : "<?php echo $uploadPath; ?>team_placeholder.jpg";
            document.getElementById('modal-team-title').innerText = t.team_name;
            document.getElementById('modal-team-owner').innerText = "Owner: " + t.owner_name;
            
            // Set Funds
            document.getElementById('modal-team-total').innerText = "₹" + parseInt(t.total_purse).toLocaleString();
            document.getElementById('modal-team-remaining').innerText = "₹" + parseInt(t.remaining_purse).toLocaleString();
            document.getElementById('modal-team-spent').innerText = "₹" + (parseInt(t.total_purse) - parseInt(t.remaining_purse)).toLocaleString();
            
            // Set Squad Size
            document.getElementById('modal-team-squad-count').innerText = `${t.current_squad_size} / ${t.max_squad_size}`;
            
            // Render Players List
            const listEl = document.getElementById('modal-team-players-list');
            listEl.innerHTML = '';
            
            if (data.players && data.players.length > 0) {
                data.players.forEach(p => {
                    const row = document.createElement('div');
                    row.className = "flex items-center justify-between p-2.5 rounded-lg bg-white/5 border border-white/5 text-xs transition hover:bg-white/10 cursor-pointer";
                    row.onclick = () => { closeTeamModal(); setTimeout(() => openPlayerDetailsModal(p.id), 200); };
                    row.innerHTML = `
                        <div class="flex items-center gap-3">
                            <img src="<?php echo $uploadPath; ?>${p.profile_image ? p.profile_image : 'player_placeholder.jpg'}" class="w-8 h-8 rounded-md object-cover border border-gold-500/20 shadow-md">
                            <div>
                                <span class="text-white font-extrabold block group-hover:text-gold-400">${p.name}</span>
                                <span class="text-[9px] text-gray-400 uppercase tracking-wider">${p.role}</span>
                            </div>
                        </div>
                        <span class="text-gold-400 font-mono font-bold">₹${p.sold_price}</span>
                    `;
                    listEl.appendChild(row);
                });
            } else {
                listEl.innerHTML = `<div class="text-center text-[10px] text-gray-500 py-6 uppercase font-semibold">No players purchased yet.</div>`;
            }

            // Animate opening
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95');
            }, 50);

        } catch (e) {
            console.error("Failed to load team details modal:", e);
        }
    }

    function closeTeamModal() {
        const modal = document.getElementById('team-details-modal');
        const content = document.getElementById('team-modal-content');
        content.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 150);
    }
</script>

<!-- PLAYER DETAILS & BID HISTORY MODAL -->
<div id="player-details-modal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md hidden transition-all duration-300">
    <div class="glass-panel w-full max-w-lg rounded-2xl border border-gold-500/20 shadow-2xl overflow-hidden relative transform scale-95 transition-all duration-300" id="modal-content">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between bg-black/40">
            <h3 class="text-sm font-black text-gold-400 uppercase tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-baseball-bat-ball text-base text-gold-400"></i> Player Auction Summary
            </h3>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-white/5 border border-white/10 hover:border-white/20 text-gray-400 hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
            
            <!-- Player Banner Info -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-5 pb-5 border-b border-white/5">
                <div class="flex flex-col sm:flex-row items-center gap-5">
                    <div class="w-20 h-24 rounded-xl overflow-hidden border border-gold-500/30 bg-black/60 shadow-md">
                        <img src="" id="modal-player-image" class="w-full h-full object-cover">
                    </div>
                    <div class="text-center sm:text-left space-y-1.5">
                        <span class="px-2 py-0.5 rounded text-[7px] uppercase tracking-wider font-extrabold" id="modal-player-status-tag">Status</span>
                        <h4 class="text-lg font-black text-white tracking-tight" id="modal-player-name">Player Name</h4>
                        <p class="text-[10px] text-gray-400" id="modal-player-details">Role &bull; Place</p>
                    </div>
                </div>
                <!-- Franchise Logo for Sold Players -->
                <div id="modal-player-team-logo-container" class="w-16 h-16 rounded bg-black/40 border border-gold-500/20 flex items-center justify-center p-1 overflow-hidden" style="display: none;">
                    <img src="" id="modal-player-team-logo" class="max-w-full max-h-full object-contain rounded">
                </div>
            </div>

            <!-- Price and Team Statistics Grid -->
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white/5 border border-white/5 rounded-xl p-3 text-center">
                    <span class="text-[8px] uppercase tracking-widest text-gray-500 font-bold">Base Price</span>
                    <span class="block text-xs font-black text-gray-200 mt-1 font-mono" id="modal-base-price">₹0</span>
                </div>
                <div class="bg-white/5 border border-white/5 rounded-xl p-3 text-center">
                    <span class="text-[8px] uppercase tracking-widest text-gray-500 font-bold">Final Price</span>
                    <span class="block text-xs font-black text-gold-400 mt-1 font-mono" id="modal-sold-price">₹0</span>
                </div>
                <div class="bg-white/5 border border-white/5 rounded-xl p-3 text-center">
                    <span class="text-[8px] uppercase tracking-widest text-gray-500 font-bold">Winning Team</span>
                    <span class="block text-[10px] font-black text-white mt-1.5 truncate" id="modal-team-name">—</span>
                </div>
            </div>

            <!-- Bid History Section -->
            <div id="modal-bid-history-section" class="space-y-3">
                <h5 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2 border-b border-white/5 pb-2">
                    <i class="fa-solid fa-chart-line text-xs text-gray-400"></i> Complete Bidding Timeline
                </h5>
                <div class="space-y-2 max-h-48 overflow-y-auto pr-1" id="modal-bid-history-list">
                    <!-- populated dynamically -->
                </div>
            </div>

        </div>
    </div>
</div>

<!-- TEAM DETAILS MODAL -->
<div id="team-details-modal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md hidden transition-all duration-300">
    <div class="glass-panel w-full max-w-lg rounded-2xl border border-gold-500/20 shadow-2xl overflow-hidden relative transform scale-95 transition-all duration-300" id="team-modal-content">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between bg-black/40">
            <h3 class="text-sm font-black text-gold-400 uppercase tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-crown text-base text-gold-400"></i> Franchise HQ Profile
            </h3>
            <button onclick="closeTeamModal()" class="w-8 h-8 rounded-full bg-white/5 border border-white/10 hover:border-white/20 text-gray-400 hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
            
            <!-- Team Banner Info -->
            <div class="flex flex-col sm:flex-row items-center gap-5 pb-5 border-b border-white/5">
                <div class="w-20 h-20 rounded-xl overflow-hidden border border-gold-500/30 bg-white shadow-md p-1 flex items-center justify-center">
                    <img src="" id="modal-team-logo" class="max-w-full max-h-full object-contain rounded">
                </div>
                <div class="text-center sm:text-left flex-grow space-y-1.5">
                    <h4 class="text-xl font-black text-white tracking-tight uppercase" id="modal-team-title">Team Name</h4>
                    <p class="text-[10px] text-gold-500 uppercase tracking-widest font-extrabold" id="modal-team-owner">Owner: Name</p>
                </div>
            </div>

            <!-- Financial Statistics Grid -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-3 text-center shadow-lg shadow-emerald-500/5">
                    <span class="text-[8px] uppercase tracking-widest text-emerald-500 font-bold">Available Purse</span>
                    <span class="block text-sm font-black text-emerald-400 mt-1 font-mono" id="modal-team-remaining">₹0</span>
                </div>
                <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-3 text-center">
                    <span class="text-[8px] uppercase tracking-widest text-red-400 font-bold">Amount Spent</span>
                    <span class="block text-sm font-black text-red-300 mt-1 font-mono" id="modal-team-spent">₹0</span>
                </div>
                <div class="bg-white/5 border border-white/5 rounded-xl p-3 text-center">
                    <span class="text-[8px] uppercase tracking-widest text-gray-500 font-bold">Total Budget</span>
                    <span class="block text-xs font-black text-gray-400 mt-1 font-mono" id="modal-team-total">₹0</span>
                </div>
                <div class="bg-white/5 border border-white/5 rounded-xl p-3 text-center">
                    <span class="text-[8px] uppercase tracking-widest text-gray-500 font-bold">Squad Filled</span>
                    <span class="block text-xs font-black text-gray-200 mt-1" id="modal-team-squad-count">0 / 0</span>
                </div>
            </div>

            <!-- Purchased Players List -->
            <div class="space-y-3">
                <h5 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2 border-b border-white/5 pb-2">
                    <i class="fa-solid fa-baseball-bat-ball text-xs text-gray-400"></i> Franchise Roster
                </h5>
                <div class="space-y-2 max-h-48 overflow-y-auto pr-1" id="modal-team-players-list">
                    <!-- populated dynamically -->
                </div>
            </div>

        </div>
    </div>
</div>
