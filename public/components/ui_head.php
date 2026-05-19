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
