<?php
// public/login.php
session_start();
require_once '../config/db.php';

$errorMsg = '';

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: ../admin/index.php");
    exit;
}
if (isset($_SESSION['manager_logged_in']) && $_SESSION['manager_logged_in'] === true) {
    header("Location: ../manager/index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $errorMsg = '❌ Please enter both username and password.';
    } else {
        try {
            // Auto-healing seeder: ensure admin accounts exist in the database with correct hashes
            try {
                $adminsToSeed = [
                    ['admin', 'SMCL@Admin#2026_Secure'],
                    ['siraj', 'Siru@2026']
                ];
                foreach ($adminsToSeed as $adminInfo) {
                    $uName = $adminInfo[0];
                    $uPass = password_hash($adminInfo[1], PASSWORD_BCRYPT);
                    $chk = $pdo->prepare("SELECT id, password FROM admins WHERE username = ?");
                    $chk->execute([$uName]);
                    $existing = $chk->fetch();
                    if ($existing) {
                        // If user is trying to log in with the correct credentials, ensure the DB hash matches
                        if ($username === $uName && $password === $adminInfo[1] && !password_verify($password, $existing['password'])) {
                            $up = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                            $up->execute([$uPass, $existing['id']]);
                        }
                    } else {
                        $ins = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                        $ins->execute([$uName, $uPass]);
                    }
                }
            } catch (Exception $e) {
                // Fail silent: setup.php will create the table if missing
            }

            // 1. Try Admin Login first
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['role'] = 'Admin';
                
                header("Location: ../admin/index.php");
                exit;
            }

            // 2. Try Manager Login next
            $stmt = $pdo->prepare("SELECT * FROM teams WHERE manager_username = :username");
            $stmt->execute(['username' => $username]);
            $team = $stmt->fetch();

            if ($team && password_verify($password, $team['manager_password'])) {
                $_SESSION['manager_logged_in'] = true;
                $_SESSION['manager_username'] = $team['manager_username'];
                $_SESSION['team_id'] = (int)$team['id'];
                $_SESSION['team_name'] = $team['team_name'];
                $_SESSION['role'] = 'Manager';
                
                header("Location: ../manager/index.php");
                exit;
            }

            // 3. If neither matches
            $errorMsg = '❌ Invalid username or password.';

        } catch (Exception $e) {
            $errorMsg = '❌ Connection Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMCL Login Portal</title>
    <link rel="icon" type="image/png" href="uploads/league_logo.png">
    <!-- Tailwind CSS CDN -->
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at center, #151515 0%, #030303 100%);
        }
        h1, h2, h3 {
            font-family: 'Outfit', sans-serif;
        }
        .glass-login {
            background: rgba(22, 22, 22, 0.75);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 163, 12, 0.12);
            box-shadow: 0 15px 45px 0 rgba(0, 0, 0, 0.7);
        }
    </style>
</head>
<body class="text-gray-200 min-h-screen px-4 flex items-center justify-center">
    <div class="max-w-md w-full glass-login rounded-2xl p-8 border border-gold-500/10 shadow-2xl relative">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(212,163,12,0.08)_0%,transparent_60%)] pointer-events-none rounded-2xl"></div>

        <!-- Tournament Emblem / Header -->
        <div class="text-center mb-6 relative">
            <img src="uploads/league_logo.png" alt="SMCL Logo" class="w-16 h-16 object-contain mx-auto mb-3">
            <a href="index.php" class="text-xs uppercase tracking-widest text-gold-500 hover:text-gold-300 font-semibold mb-2 inline-flex items-center gap-1.5 transition">
                <i class="fa-solid fa-arrow-left text-[10px]"></i> Enter Spectator Room
            </a>
            <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-gold-300 via-gold-400 to-amber-600 mt-2">
                SMCL PORTAL
            </h1>
            <p class="text-xs text-gray-500 uppercase tracking-widest font-semibold mt-1">Tournament Administration Logins</p>
        </div>

        <!-- Error Feedback -->
        <?php if (!empty($errorMsg)): ?>
            <div class="bg-red-950/20 border border-red-500/40 text-red-300 text-xs px-4 py-3 rounded-xl mb-6 font-medium flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-red-400 text-sm"></i>
                <div><?php echo $errorMsg; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="login.php" method="POST" class="space-y-6 relative">
            <!-- Username -->
            <div>
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Username</label>
                <input type="text" name="username" required placeholder="e.g. admin or kochi"
                       class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3.5 text-sm text-white focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500/30 transition placeholder-gray-700">
            </div>

            <!-- Password -->
            <div>
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Password</label>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3.5 text-sm text-white focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500/30 transition placeholder-gray-700">
            </div>



            <!-- Submit Button -->
            <button type="submit"
                    class="w-full bg-gradient-to-r from-gold-500 to-amber-600 text-black font-extrabold uppercase text-xs tracking-wider py-4 px-6 rounded-xl hover:from-gold-400 hover:to-gold-500 transition duration-300 shadow-lg shadow-gold-500/10 active:scale-95">
                Authenticate & Enter
            </button>
        </form>

        <div class="mt-8 border-t border-white/5 pt-4 text-center text-xs text-gray-500">
            Forgot credentials? Contact the SMCL Tournament Coordinator.
        </div>
    </div>
</body>
</html>
