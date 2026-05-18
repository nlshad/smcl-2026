<?php
// public/register.php
session_start();
require_once '../config/db.php';

$successMsg = '';
$errorMsg = '';

// Handle Registration Form Post
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $place = trim($_POST['place'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $utr = trim($_POST['utr'] ?? '');

    // Form Validations
    if (empty($name) || empty($mobile) || empty($place) || empty($role) || empty($utr)) {
        $errorMsg = '❌ All fields are required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errorMsg = '❌ Mobile number must be exactly 10 digits.';
    } elseif (strlen($utr) < 8 || strlen($utr) > 20) {
        $errorMsg = '❌ Please enter a valid UPI UTR / Reference number (8-20 characters).';
    } else {
        try {
            // Check if UTR already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE payment_utr = ?");
            $stmt->execute([$utr]);
            if ($stmt->fetchColumn() > 0) {
                $errorMsg = '❌ This UPI UTR / Reference number has already been registered.';
            } else {
                // File Upload Handling
                if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                    $errorMsg = '❌ Please select a profile image to upload.';
                } else {
                    $fileTmpPath = $_FILES['profile_image']['tmp_name'];
                    $fileName = $_FILES['profile_image']['name'];
                    $fileSize = $_FILES['profile_image']['size'];
                    $fileType = $_FILES['profile_image']['type'];
                    
                    // Verify size (Max 2MB)
                    $maxSize = 2 * 1024 * 1024;
                    if ($fileSize > $maxSize) {
                        $errorMsg = '❌ Image size too large. Maximum limit is 2MB.';
                    } else {
                        // Strict MIME-type checking
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg'];
                        // Use mime_content_type if available, otherwise fallback
                        $actualMime = function_exists('mime_content_type') ? mime_content_type($fileTmpPath) : $fileType;
                        
                        if (!in_array($actualMime, $allowedMimes)) {
                            $errorMsg = '❌ Invalid file type. Only JPG, JPEG, and PNG images are allowed.';
                        } else {
                            // Extract file extension safely
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            if (!in_array($fileExt, ['jpg', 'jpeg', 'png'])) {
                                $fileExt = ($actualMime === 'image/png') ? 'png' : 'jpg';
                            }
                            
                            // Generate unique sanitized name
                            $newFileName = uniqid('player_', true) . '.' . $fileExt;
                            $uploadDir = __DIR__ . '/uploads/';
                            
                            // Check if directory exists
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            $destPath = $uploadDir . $newFileName;
                            
                            if (move_uploaded_file($fileTmpPath, $destPath)) {
                                // Save to Database
                                $stmt = $pdo->prepare("INSERT INTO players (name, mobile, place, role, profile_image, payment_utr, payment_status, base_price) VALUES (?, ?, ?, ?, ?, ?, 'Pending', 100)");
                                $stmt->execute([$name, $mobile, $place, $role, $newFileName, $utr]);
                                
                                $successMsg = "🎉 Registration submitted successfully! Your payment reference (UTR: $utr) is queued for Admin approval.";
                                // Reset form values
                                $name = $mobile = $place = $role = $utr = '';
                            } else {
                                $errorMsg = '❌ Image upload failed. Please try again.';
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $errorMsg = '❌ Database Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Registration — SMCL 2026</title>
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
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at center, #181818 0%, #080808 100%);
        }
        h1, h2, h3, h4 {
            font-family: 'Outfit', sans-serif;
        }
        .glass-card {
            background: rgba(25, 25, 25, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(218, 165, 32, 0.1);
            box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.6);
        }
    </style>
</head>
<body class="text-gray-200 min-h-screen py-10 px-4 flex items-center justify-center">
    <div class="max-w-4xl w-full glass-card rounded-2xl border border-gold-500/10 overflow-hidden shadow-2xl">
        <!-- Top Banner -->
        <div class="bg-gradient-to-r from-gold-950 via-black to-gold-950 p-6 border-b border-gold-500/20 text-center relative">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(218,165,32,0.15)_0%,transparent_70%)] pointer-events-none"></div>
            <a href="index.php" class="text-xs uppercase tracking-widest text-gold-400 hover:text-gold-300 font-semibold mb-2 inline-block transition">
                ← Back to Live Dashboard
            </a>
            <h1 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-gold-300 to-amber-500 mt-1">
                SHAMSU MEMORIAL CRICKET LEAGUE
            </h1>
            <p class="text-gray-400 text-xs mt-1 uppercase tracking-widest font-semibold">SMCL 2026 — Player Registration Pool</p>
        </div>

        <!-- Feedback Messages -->
        <?php if (!empty($successMsg)): ?>
            <div class="mx-6 mt-6 bg-gold-950/20 border border-gold-500/40 text-gold-300 px-5 py-4 rounded-xl text-sm flex items-center gap-3">
                <span class="text-lg">✔️</span>
                <div><?php echo $successMsg; ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="mx-6 mt-6 bg-red-950/20 border border-red-500/40 text-red-300 px-5 py-4 rounded-xl text-sm flex items-center gap-3">
                <span class="text-lg">🚨</span>
                <div><?php echo $errorMsg; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form & Payment Container (Split layout) -->
        <form action="register.php" method="POST" enctype="multipart/form-data" class="p-6 md:p-8 grid grid-cols-1 md:grid-cols-12 gap-8">
            
            <!-- Left Side: Data Entry Form (7 cols) -->
            <div class="md:col-span-7 space-y-6">
                <h3 class="text-lg font-bold text-gold-400 border-b border-white/5 pb-2">
                    🏏 Player Details
                </h3>

                <!-- Name Input -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. Sanju Samson"
                           class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500/30 transition placeholder-gray-600"
                           value="<?php echo htmlspecialchars($name ?? ''); ?>">
                </div>

                <!-- Mobile & Place -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Mobile Number</label>
                        <input type="tel" name="mobile" required placeholder="10-digit number" pattern="[0-9]{10}"
                               class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500/30 transition placeholder-gray-600"
                               value="<?php echo htmlspecialchars($mobile ?? ''); ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Place / Hometown</label>
                        <input type="text" name="place" required placeholder="e.g. Panamaram"
                               class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500/30 transition placeholder-gray-600"
                               value="<?php echo htmlspecialchars($place ?? ''); ?>">
                    </div>
                </div>

                <!-- Playing Role -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Playing Role</label>
                    <select name="role" required 
                            class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500/30 transition">
                        <option value="" disabled selected class="bg-zinc-950">Select Playing Role</option>
                        <option value="Batsman" <?php echo ($role ?? '') === 'Batsman' ? 'selected' : ''; ?> class="bg-zinc-950">Batsman</option>
                        <option value="Bowler" <?php echo ($role ?? '') === 'Bowler' ? 'selected' : ''; ?> class="bg-zinc-950">Bowler</option>
                        <option value="All-Rounder" <?php echo ($role ?? '') === 'All-Rounder' ? 'selected' : ''; ?> class="bg-zinc-950">All-Rounder</option>
                        <option value="Wicket-Keeper" <?php echo ($role ?? '') === 'Wicket-Keeper' ? 'selected' : ''; ?> class="bg-zinc-950">Wicket-Keeper</option>
                    </select>
                </div>

                <!-- Profile Photo Upload -->
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Profile Image (Max 2MB, JPG/PNG)</label>
                    <div class="relative w-full bg-black/40 border border-dashed border-white/10 rounded-xl p-4 text-center hover:border-gold-500/40 transition">
                        <input type="file" name="profile_image" id="profile_image" required accept="image/*"
                               class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                        <div class="space-y-1" id="upload-prompt">
                            <span class="text-2xl text-gold-400 inline-block">📸</span>
                            <p class="text-xs text-gray-300 font-semibold">Click to upload or drag & drop</p>
                            <p class="text-[10px] text-gray-500">MIME validation will enforce real image files only.</p>
                        </div>
                        <div class="hidden space-y-1 text-gold-400 font-medium text-xs" id="upload-feedback">
                            <span class="text-xl">🌟</span>
                            <p id="file-name-display"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: UPI Payment & QR Code (5 cols) -->
            <div class="md:col-span-5 bg-black/40 rounded-xl p-6 border border-white/5 flex flex-col justify-between">
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-gold-400 border-b border-white/5 pb-2">
                        💳 Payment Verification
                    </h3>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        To join the SMCL 2026 Auction pool, pay a registration fee of <strong class="text-gold-300 font-bold">₹250</strong>. Scan the QR code using any UPI app (GPAY, PhonePe, Paytm).
                    </p>

                    <!-- UPI QR Box -->
                    <div class="bg-white p-4 rounded-xl max-w-[200px] mx-auto border-2 border-gold-500 shadow-lg relative group">
                        <!-- Simulated QR Code SVG -->
                        <svg viewBox="0 0 100 100" class="w-full h-full text-black">
                            <!-- Background Grid Mock -->
                            <rect width="100" height="100" fill="white"/>
                            <!-- Position Anchors -->
                            <rect x="5" y="5" width="25" height="25" fill="black"/>
                            <rect x="8" y="8" width="19" height="19" fill="white"/>
                            <rect x="12" y="12" width="11" height="11" fill="black"/>

                            <rect x="70" y="5" width="25" height="25" fill="black"/>
                            <rect x="73" y="8" width="19" height="19" fill="white"/>
                            <rect x="77" y="12" width="11" height="11" fill="black"/>

                            <rect x="5" y="70" width="25" height="25" fill="black"/>
                            <rect x="8" y="73" width="19" height="19" fill="white"/>
                            <rect x="12" y="77" width="11" height="11" fill="black"/>

                            <!-- QR Pattern Matrix -->
                            <rect x="35" y="10" width="10" height="5" fill="black"/>
                            <rect x="50" y="5" width="15" height="10" fill="black"/>
                            <rect x="35" y="20" width="15" height="10" fill="black"/>
                            
                            <rect x="10" y="35" width="5" height="15" fill="black"/>
                            <rect x="25" y="45" width="15" height="5" fill="black"/>
                            <rect x="5" y="55" width="15" height="10" fill="black"/>

                            <rect x="45" y="35" width="20" height="20" fill="black"/>
                            <rect x="50" y="40" width="10" height="10" fill="white"/>
                            <rect x="75" y="35" width="20" height="10" fill="black"/>

                            <rect x="35" y="70" width="15" height="15" fill="black"/>
                            <rect x="55" y="80" width="25" height="15" fill="black"/>
                            <rect x="80" y="60" width="15" height="35" fill="black"/>
                            <rect x="85" y="65" width="5" height="10" fill="white"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center bg-black/80 opacity-0 group-hover:opacity-100 transition duration-300 rounded-xl">
                            <span class="text-[10px] text-gold-400 font-bold text-center px-2">UPI ID: smcl2026@upi</span>
                        </div>
                    </div>

                    <div class="text-center">
                        <span class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold bg-white/5 px-2.5 py-1 rounded border border-white/5 inline-block">
                            UPI ID: smcl2026@upi
                        </span>
                    </div>

                    <!-- UTR Field -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">UPI UTR / 12-Digit Reference No.</label>
                        <input type="text" name="utr" required placeholder="e.g. 612345678901"
                               class="w-full bg-black/60 border border-gold-500/30 rounded-xl px-4 py-3 text-sm text-gold-300 font-mono focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500/30 transition placeholder-gray-700"
                               value="<?php echo htmlspecialchars($utr ?? ''); ?>">
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                        class="w-full bg-gradient-to-r from-gold-500 to-amber-600 text-black font-extrabold uppercase text-xs tracking-wider py-4 px-6 rounded-xl hover:from-gold-400 hover:to-gold-500 transition duration-300 mt-6 shadow-lg shadow-gold-500/10 active:scale-95">
                    Submit Registration
                </button>
            </div>
        </form>
    </div>

    <!-- Script for File Input Preview -->
    <script>
        const fileInput = document.getElementById('profile_image');
        const promptDiv = document.getElementById('upload-prompt');
        const feedbackDiv = document.getElementById('upload-feedback');
        const nameDisplay = document.getElementById('file-name-display');

        fileInput.addEventListener('change', (e) => {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                nameDisplay.innerText = file.name + ' (' + (file.size/1024/1024).toFixed(2) + ' MB)';
                promptDiv.classList.add('hidden');
                feedbackDiv.classList.remove('hidden');
            } else {
                promptDiv.classList.remove('hidden');
                feedbackDiv.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
