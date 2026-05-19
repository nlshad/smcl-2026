# scratch/apply_completed_list.py
import os

target_file = r"C:\xampp\htdocs\SMCL\public\index.php"

if not os.path.exists(target_file):
    print("Error: Target file not found.")
    exit(1)

with open(target_file, "r", encoding="utf-8") as f:
    content = f.read()

# Let's target the end of try block in fetchState()
old_marker = """                }

            } catch (error) {"""

# If Windows line endings are used
if old_marker not in content:
    old_marker = old_marker.replace("\n", "\r\n")

if old_marker not in content:
    # Try another specific anchor
    old_marker = """                }
            } catch (error) {"""
    if old_marker not in content:
        old_marker = old_marker.replace("\n", "\r\n")

new_code = """                }

                // 4. Sync Completed Player Auctions
                const completedGrid = document.getElementById('completed-players-grid');
                const completedEmpty = document.getElementById('completed-empty-box');
                const countSoldEl = document.getElementById('count-sold');
                const countUnsoldEl = document.getElementById('count-unsold');

                completedGrid.innerHTML = '';

                let soldCount = 0;
                let unsoldCount = 0;

                if (data.completed_players && data.completed_players.length > 0) {
                    completedEmpty.classList.add('hidden');
                    completedGrid.classList.remove('hidden');

                    data.completed_players.forEach(p => {
                        if (p.auction_status === 'Sold') soldCount++;
                        if (p.auction_status === 'Unsold') unsoldCount++;

                        const card = document.createElement('div');
                        card.className = "glass-panel rounded-2xl p-5 border border-gold-500/10 hover:border-gold-500/20 transition-all duration-300 relative group flex flex-col justify-between overflow-hidden shadow-lg shadow-black/40";
                        
                        card.innerHTML = `
                            <!-- Top Info Row -->
                            <div class="flex items-center justify-between pb-4 border-b border-white/5">
                                <div class="flex items-center gap-3.5">
                                    <!-- Player Profile Picture -->
                                    <div class="w-12 h-12 rounded-xl overflow-hidden border border-gold-500/25 bg-black/60 shadow-md">
                                        <img src="uploads/${p.profile_image}" alt="${p.name}" class="w-full h-full object-cover">
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
                                        : 'bg-red-500/10 border border-red-500/25 text-red-400'
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
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-[8px] uppercase tracking-wider text-gray-500 font-bold">Team</span>
                                    ${p.auction_status === 'Sold' 
                                        ? `<span class="text-xs font-extrabold text-white tracking-tight mt-1 truncate max-w-[80px]">${p.team_name}</span>` 
                                        : '<span class="text-xs font-bold text-gray-600 mt-1">—</span>'
                                    }
                                </div>
                            </div>
                        `;
                        completedGrid.appendChild(card);
                    });
                } else {
                    completedEmpty.classList.remove('hidden');
                }

                countSoldEl.innerText = soldCount;
                countUnsoldEl.innerText = unsoldCount;

            } catch (error) {"""

# Replace
if old_marker in content:
    content = content.replace(old_marker, new_code)
    with open(target_file, "w", encoding="utf-8") as f:
        f.write(content)
    print("SUCCESS: Code successfully injected into public/index.php!")
else:
    print("Error: Could not find old marker in the file.")
    # Print the surrounding lines of where we think the marker is to debug
    idx = content.find("Sync Leaderboards Standings")
    if idx != -1:
        print("Context around 'Sync Leaderboards Standings':")
        print(content[idx:idx+400])
