// public/js/auction.js

const playerIdOnBlock = 1; // Dynamically set this to the ID of the player currently being auctioned
const teamId = 5; // Dynamically set this to the logged-in manager's Team ID

// 1. Fetch live auction data every 1.5 seconds
setInterval(fetchLiveAuctionData, 1500);

async function fetchLiveAuctionData() {
    try {
        const response = await fetch(`../api/get_live_bid.php?player_id=${playerIdOnBlock}`);
        const data = await response.json();

        // Update the UI with the fetched data
        const currentBidEl = document.getElementById('current-bid');
        const leadingTeamEl = document.getElementById('leading-team');

        if (currentBidEl) {
            currentBidEl.innerText = '₹' + data.highest_bid;
        }
        if (leadingTeamEl) {
            leadingTeamEl.innerText = data.leading_team_name || 'No bids yet';
        }
    } catch (error) {
        console.error("Error fetching live data:", error);
    }
}

// 2. Submit a new bid securely
async function placeBid(bidAmount) {
    const formData = new FormData();
    formData.append('player_id', playerIdOnBlock);
    formData.append('team_id', teamId);
    formData.append('bid_amount', bidAmount);

    try {
        const response = await fetch('../api/place_bid.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Instantly update UI or trigger a success sound/animation
            console.log("Bid placed successfully!");
            fetchLiveAuctionData(); // Immediately fetch the new data
        } else {
            // Show error (e.g., "Not enough purse" or "Squad full")
            alert("Bid Failed: " + result.error);
        }
    } catch (error) {
        console.error("Bid submission failed:", error);
    }
}
