<?php
// setup.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php'; // Inherit environment-aware credentials dynamically!

$message = [];
$success = true;

try {
    // 1. Establish connection to MySQL server
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $message[] = "🟢 Connected to MySQL server successfully.";

    // 2. Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $message[] = "🟢 Database '$db' created or verified.";

    // 3. Connect to database
    $pdo->exec("USE `$db`;");

    // 4. Create Tables
    
    // Admins Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    $message[] = "🟢 Table 'admins' created.";

    // Teams Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_name VARCHAR(100) NOT NULL,
        logo VARCHAR(255) NULL,
        manager_username VARCHAR(50) NOT NULL UNIQUE,
        manager_password VARCHAR(255) NOT NULL,
        total_purse INT DEFAULT 10000,
        remaining_purse INT DEFAULT 10000,
        current_squad_size INT DEFAULT 0,
        max_squad_size INT DEFAULT 15,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    $message[] = "🟢 Table 'teams' created.";

    // Players Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        mobile VARCHAR(15) NOT NULL,
        place VARCHAR(100) NOT NULL,
        role ENUM('Batsman', 'Bowler', 'All-Rounder', 'Wicket-Keeper') NOT NULL,
        profile_image VARCHAR(255) NOT NULL,
        payment_utr VARCHAR(20) NOT NULL UNIQUE,
        payment_status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
        base_price INT DEFAULT 100,
        sold_price INT DEFAULT NULL,
        team_id INT DEFAULT NULL,
        auction_status ENUM('Available', 'Bidding', 'Sold', 'Unsold') DEFAULT 'Available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;");
    $message[] = "🟢 Table 'players' created.";

    // Bids Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bids (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        team_id INT NOT NULL,
        bid_amount INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    $message[] = "🟢 Table 'bids' created.";

    // Create Index on bids table for fast querying (Wrapped in a try-catch for safe page-reloads!)
    try {
        $pdo->exec("CREATE INDEX idx_player_bid ON bids(player_id, bid_amount DESC);");
        $message[] = "🟢 Index 'idx_player_bid' created on 'bids' table.";
    } catch (PDOException $e) {
        // If it's a duplicate index error (1061), we can safely ignore it and continue!
        if (str_contains($e->getMessage(), '1061')) {
            $message[] = "🟢 Index 'idx_player_bid' already verified.";
        } else {
            throw $e;
        }
    }

    // Auction State Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS auction_state (
        id INT PRIMARY KEY,
        current_player_id INT NULL,
        current_bid_amount INT DEFAULT 0,
        current_highest_bidder_id INT NULL,
        status ENUM('Idle', 'Bidding', 'Paused') DEFAULT 'Idle',
        registration_enabled TINYINT(1) DEFAULT 1,
        last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (current_player_id) REFERENCES players(id) ON DELETE SET NULL,
        FOREIGN KEY (current_highest_bidder_id) REFERENCES teams(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;");
    $message[] = "🟢 Table 'auction_state' created.";

    // 5. Seed Initial Data
    
    // Seed Admin
    $adminUser = 'admin';
    $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
    $stmt->execute([$adminUser]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute([$adminUser, $adminPass]);
        $message[] = "👤 Seeded Default Super Admin: <strong>username: admin | password: admin123</strong>";
    }

    // Seed Teams
    $defaultTeams = [
        ['Kochi Kings', 'kochi_logo.png', 'kochi', password_hash('kochi123', PASSWORD_BCRYPT), 12000],
        ['Calicut Warriors', 'calicut_logo.png', 'calicut', password_hash('calicut123', PASSWORD_BCRYPT), 10000],
        ['Trivandrum Titans', 'trivandrum_logo.png', 'trivandrum', password_hash('trivandrum123', PASSWORD_BCRYPT), 10000],
        ['Malabar Mavericks', 'malabar_logo.png', 'malabar', password_hash('malabar123', PASSWORD_BCRYPT), 8000],
    ];

    foreach ($defaultTeams as $team) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE manager_username = ?");
        $stmt->execute([$team[2]]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO teams (team_name, logo, manager_username, manager_password, total_purse, remaining_purse) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$team[0], $team[1], $team[2], $team[3], $team[4], $team[4]]);
            $message[] = "🏆 Seeded Franchise Team: <strong>{$team[0]}</strong> (Manager: <strong>{$team[2]}</strong> / <strong>{$team[2]}123</strong>, Purse: ₹{$team[4]})";
        }
    }

    // Seed Players
    $defaultPlayers = [
        ['Sanju Samson', '9988776655', 'Trivandrum', 'Wicket-Keeper', 'player_sanju.jpg', 'UTR1122334455', 'Verified', 500],
        ['Sachin Baby', '9988776644', 'Kochi', 'Batsman', 'player_sachin.jpg', 'UTR1122334466', 'Verified', 300],
        ['Basil Thampi', '9988776633', 'Perumbavoor', 'Bowler', 'player_basil.jpg', 'UTR1122334477', 'Verified', 250],
        ['Sandeep Warrier', '9988776622', 'Thrissur', 'Bowler', 'player_sandeep.jpg', 'UTR1122334488', 'Verified', 250],
        ['Rohan Kunnummal', '9988776611', 'Calicut', 'Batsman', 'player_rohan.jpg', 'UTR1122334499', 'Verified', 200],
        ['Sharafuddeen NM', '9988776600', 'Wayanad', 'All-Rounder', 'player_sharaf.jpg', 'UTR1122334400', 'Pending', 150]
    ];

    foreach ($defaultPlayers as $player) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE payment_utr = ?");
        $stmt->execute([$player[5]]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO players (name, mobile, place, role, profile_image, payment_utr, payment_status, base_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($player);
            $message[] = "🏏 Seeded Player Pool: <strong>{$player[0]}</strong> ({$player[3]}, UTR: {$player[5]}, Status: {$player[6]})";
        }
    }

    // Seed active auction state
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auction_state WHERE id = 1");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO auction_state (id, current_player_id, current_bid_amount, current_highest_bidder_id, status) VALUES (1, NULL, 0, NULL, 'Idle')");
        $message[] = "⚡ Initialized Auction State Row successfully.";
    }

} catch (PDOException $e) {
    $success = false;
    $message[] = "❌ Database initialization failed: " . $e->getMessage();
}

// Ensure local directories exist for uploads
$uploadDir = __DIR__ . '/public/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    $message[] = "📂 Created directory: public/uploads/";
}

// Copy default silhouette image for players if none uploaded
$silhouetteDest = $uploadDir . '/player_placeholder.jpg';
if (!file_exists($silhouetteDest)) {
    // Generate a simple dummy image using GD to prevent broken images
    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor(300, 350);
        $bg = imagecolorallocate($im, 20, 20, 20);
        $fg = imagecolorallocate($im, 218, 165, 32); // Goldenrod
        $text_color = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $bg);
        // Draw a golden frame
        imagerectangle($im, 10, 10, 290, 340, $fg);
        // Put text inside
        imagestring($im, 5, 60, 150, "SMCL PLAYER", $fg);
        imagestring($im, 3, 55, 180, "No Profile Photo", $text_color);
        imagejpeg($im, $silhouetteDest);
        imagedestroy($im);
        $message[] = "🎨 Created a golden mock silhouette at public/uploads/player_placeholder.jpg";
    } else {
        // Fallback: write an empty dummy file
        file_put_contents($silhouetteDest, "");
        $message[] = "🎨 Created placeholder profile photo file.";
    }
}

// Copy the placeholder silhouette to mock players so their profiles render beautifully
foreach (['sanju', 'sachin', 'basil', 'sandeep', 'rohan', 'sharaf'] as $p_slug) {
    copy($silhouetteDest, $uploadDir . "/player_{$p_slug}.jpg");
}
$message[] = "🏏 Mapped default golden silhouettes to all mock seeded players.";

// Copy generated franchise logo files from brain directory
$brainDir = 'C:/Users/Nishad/.gemini/antigravity/brain/0bc3debc-750d-4102-9087-2c6aa5f34d84/';
$logoMappings = [
    $brainDir . 'kochi_logo_1779176830624.png' => $uploadDir . '/kochi_logo.png',
    $brainDir . 'calicut_logo_1779176848493.png' => $uploadDir . '/calicut_logo.png',
    $brainDir . 'trivandrum_logo_1779176865671.png' => $uploadDir . '/trivandrum_logo.png',
    $brainDir . 'malabar_logo_1779176881416.png' => $uploadDir . '/malabar_logo.png',
    $brainDir . 'team_placeholder_1779176900898.png' => $uploadDir . '/team_placeholder.jpg'
];

foreach ($logoMappings as $src => $dest) {
    if (file_exists($src)) {
        copy($src, $dest);
        $message[] = "🎯 Copied logo file: " . basename($dest);
    } else {
        $message[] = "⚠️ Source logo not found: " . basename($src);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMCL Web Installer</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Inter:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at center, #111111 0%, #000000 100%);
        }
        h1, h2, h3 {
            font-family: 'Outfit', sans-serif;
        }
        .glass-panel {
            background: rgba(20, 20, 20, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(212, 163, 12, 0.15);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body class="text-gray-100 min-h-screen py-12 px-4 flex items-center justify-center">
    <div class="max-w-3xl w-full glass-panel rounded-2xl p-8 border border-gold-500/20">
        <!-- Header -->
        <div class="text-center mb-8 border-b border-gold-500/20 pb-6">
            <span class="text-xs font-semibold uppercase tracking-widest text-gold-400 bg-gold-950/50 px-3 py-1.5 rounded-full border border-gold-500/30">
                Setup Installer v2.0
            </span>
            <h1 class="text-4xl font-extrabold tracking-tight mt-3 text-transparent bg-clip-text bg-gradient-to-r from-gold-300 via-gold-400 to-amber-600">
                Shamsu Memorial Cricket League
            </h1>
            <p class="text-gray-400 mt-2 text-sm">Database auto-initializer for local PHP/MySQL (XAMPP)</p>
        </div>

        <!-- Installation Status -->
        <div class="space-y-3 mb-8">
            <h2 class="text-lg font-bold text-gold-300 mb-2 flex items-center">
                <span class="mr-2 text-base">🛠️</span> Installation Logs
            </h2>
            <div class="bg-black/40 rounded-xl p-5 border border-white/5 font-mono text-xs leading-relaxed max-h-80 overflow-y-auto space-y-2">
                <?php foreach ($message as $msg): ?>
                    <div><?php echo $msg; ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Success Report / Action Links -->
        <?php if ($success): ?>
            <div class="bg-gold-950/20 border border-gold-500/30 rounded-xl p-6 mb-8 text-center">
                <span class="text-3xl">🏆</span>
                <h3 class="text-xl font-bold text-gold-400 mt-2">Environment Setup Successful!</h3>
                <p class="text-gray-300 text-sm mt-1">All MySQL relations, indexes, sample teams, and players are primed for the live auction.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="public/index.php" class="flex items-center justify-between p-4 glass-panel hover:bg-gold-500/10 transition duration-300 rounded-xl border border-gold-500/10 hover:border-gold-500/30 group">
                    <div>
                        <h4 class="font-bold text-gold-400 group-hover:text-gold-300">Spectator Dashboard</h4>
                        <p class="text-xs text-gray-400 mt-1">Live read-only tournament grid.</p>
                    </div>
                    <span class="text-gold-400 text-xl font-semibold">→</span>
                </a>

                <a href="public/register.php" class="flex items-center justify-between p-4 glass-panel hover:bg-gold-500/10 transition duration-300 rounded-xl border border-gold-500/10 hover:border-gold-500/30 group">
                    <div>
                        <h4 class="font-bold text-gold-400 group-hover:text-gold-300">Player Registration</h4>
                        <p class="text-xs text-gray-400 mt-1">Form for collecting player entries.</p>
                    </div>
                    <span class="text-gold-400 text-xl font-semibold">→</span>
                </a>

                <a href="public/login.php" class="flex items-center justify-between p-4 glass-panel hover:bg-gold-500/10 transition duration-300 rounded-xl border border-gold-500/10 hover:border-gold-500/30 group">
                    <div>
                        <h4 class="font-bold text-gold-400 group-hover:text-gold-300">Unified Login</h4>
                        <p class="text-xs text-gray-400 mt-1">Access Admin & Manager panels.</p>
                    </div>
                    <span class="text-gold-400 text-xl font-semibold">→</span>
                </a>

                <div class="p-4 glass-panel rounded-xl border border-white/5 bg-white/5 flex flex-col justify-center">
                    <h4 class="text-xs text-gray-400 uppercase tracking-widest font-semibold">Test Logins</h4>
                    <p class="text-xs text-gold-500 mt-1">Admin: <span class="text-gray-300 font-mono">admin / admin123</span></p>
                    <p class="text-xs text-gold-500">Manager: <span class="text-gray-300 font-mono">kochi / kochi123</span></p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-950/20 border border-red-500/30 rounded-xl p-6 text-center">
                <span class="text-3xl">🚨</span>
                <h3 class="text-xl font-bold text-red-400 mt-2">Setup Interrupted!</h3>
                <p class="text-gray-300 text-sm mt-1">Please ensure Apache and MySQL are running in your XAMPP Control Panel, then reload this page.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
