import os

target_file = r"C:\xampp\htdocs\SMCL\public\index.php"

with open(target_file, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Add cursor-pointer and onclick to the leaderboard teams
leaderboard_old = """                        const div = document.createElement('div');
                        div.className = `p-3.5 rounded-xl border transition flex flex-col justify-between ${"""

leaderboard_new = """                        const div = document.createElement('div');
                        div.className = `p-3.5 rounded-xl border transition cursor-pointer flex flex-col justify-between ${
                            isLeading 
                                ? 'bg-gold-500/10 border-gold-500 shadow-md shadow-gold-500/5 hover:bg-gold-500/20' 
                                : 'bg-black/30 border-white/5 hover:border-white/20'
                        }`;
                        div.onclick = () => openTeamDetailsModal(team.id);"""

if leaderboard_old in content:
    # Need to be careful not to replace the condition part if we only replace the top lines
    # Let's use a regex or just replace the exact block up to the condition
    content = content.replace(leaderboard_old, """                        const div = document.createElement('div');
                        div.className = `p-3.5 rounded-xl border transition cursor-pointer flex flex-col justify-between ${""")
    
    # Let's find exactly where to insert div.onclick
    div_inner_old = """                        }`;
                        div.innerHTML = `"""
    div_inner_new = """                        }`;
                        div.onclick = () => openTeamDetailsModal(team.id);
                        div.innerHTML = `"""
    content = content.replace(div_inner_old, div_inner_new)

# 2. Insert JS function and HTML modal before closing body
modal_js_and_html = """
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
                document.getElementById('modal-team-logo').src = t.logo ? "uploads/" + t.logo : "uploads/team_placeholder.jpg";
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
                                <img src="uploads/${p.profile_image ? p.profile_image : 'player_placeholder.jpg'}" class="w-8 h-8 rounded-md object-cover border border-white/10">
                                <div>
                                    <span class="text-white font-extrabold block">${p.name}</span>
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

        // Close on backdrop click
        document.getElementById('team-details-modal').addEventListener('click', (e) => {
            if (e.target.id === 'team-details-modal') {
                closeTeamModal();
            }
        });
    </script>

    <!-- TEAM DETAILS MODAL -->
    <div id="team-details-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md hidden transition-all duration-300">
        <div class="glass-panel w-full max-w-lg rounded-2xl border border-gold-500/20 shadow-2xl overflow-hidden relative transform scale-95 transition-all duration-300" id="team-modal-content">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between bg-black/40">
                <h3 class="text-sm font-black text-gold-400 uppercase tracking-tight flex items-center gap-2">
                    <span>👑</span> Franchise HQ Profile
                </h3>
                <button onclick="closeTeamModal()" class="w-8 h-8 rounded-full bg-white/5 border border-white/10 hover:border-white/20 text-gray-400 hover:text-white flex items-center justify-center text-sm transition">
                    ✕
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                
                <!-- Team Banner Info -->
                <div class="flex flex-col sm:flex-row items-center gap-5 pb-5 border-b border-white/5">
                    <div class="w-20 h-20 rounded-xl overflow-hidden border border-gold-500/30 bg-white shadow-md p-1">
                        <img src="" id="modal-team-logo" class="w-full h-full object-contain">
                    </div>
                    <div class="text-center sm:text-left flex-grow space-y-1.5">
                        <h4 class="text-xl font-black text-white tracking-tight uppercase" id="modal-team-title">Team Name</h4>
                        <p class="text-[10px] text-gold-500 uppercase tracking-widest font-extrabold" id="modal-team-owner">Owner: Name</p>
                    </div>
                </div>

                <!-- Financial Statistics Grid -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white/5 border border-emerald-500/20 rounded-xl p-3 text-center shadow-lg shadow-emerald-500/5">
                        <span class="text-[8px] uppercase tracking-widest text-emerald-500 font-bold">Available Purse</span>
                        <span class="block text-sm font-black text-emerald-400 mt-1 font-mono" id="modal-team-remaining">₹0</span>
                    </div>
                    <div class="bg-white/5 border border-white/5 rounded-xl p-3 text-center">
                        <span class="text-[8px] uppercase tracking-widest text-gray-500 font-bold">Amount Spent</span>
                        <span class="block text-sm font-black text-white mt-1 font-mono" id="modal-team-spent">₹0</span>
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
                        <span>🏏</span> Franchise Roster
                    </h5>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1" id="modal-team-players-list">
                        <!-- populated dynamically -->
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>"""

# Replace the closing tags with our new script functions and HTML
closing_tags = """    </script>
</body>
</html>"""

if closing_tags in content:
    content = content.replace(closing_tags, modal_js_and_html)
    with open(target_file, "w", encoding="utf-8") as f:
        f.write(content)
    print("SUCCESS: Franchise Modal code successfully injected into public/index.php!")
else:
    print("Error: Could not find closing tags in the file.")
