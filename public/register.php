<?php
// public/register.php
session_start();
require_once '../config/db.php';

// Fetch registration status
$stmt = $pdo->prepare("SELECT registration_enabled FROM auction_state WHERE id = 1");
$stmt->execute();
$regStatus = $stmt->fetch();
$registrationEnabled = $regStatus ? (bool)$regStatus['registration_enabled'] : true;

$successMsg = '';
$errorMsg = '';

// Handle Registration Form Post
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$registrationEnabled) {
        $errorMsg = '❌ Registration is currently closed by Admin.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $place = trim($_POST['place'] ?? '');
        $role = trim($_POST['role'] ?? '');

    // Form Validations
    if (empty($name) || empty($mobile) || empty($place) || empty($role)) {
        $errorMsg = '❌ All fields are required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errorMsg = '❌ Mobile number must be exactly 10 digits.';
    } else {
        try {
            // Auto-generate unique registration reference (UTR)
            $utr = 'REG-' . strtoupper(bin2hex(random_bytes(6)));
            
            // File Upload / Cropping Handling
            $croppedData = $_POST['cropped_image_data'] ?? '';
            
            if (!empty($croppedData)) {
                // Handle Base64 cropped image upload
                // Format: data:image/jpeg;base64,/9j/4AAQSkZJRg...
                if (preg_match('/^data:image\/(\w+);base64,/', $croppedData, $typeMatches)) {
                    $imageType = strtolower($typeMatches[1]); // e.g. jpeg, png
                    $fileExt = ($imageType === 'png') ? 'png' : 'jpg';
                    
                    $croppedData = substr($croppedData, strpos($croppedData, ',') + 1);
                    $croppedData = base64_decode($croppedData);
                    
                    if ($croppedData === false) {
                        $errorMsg = '❌ Invalid cropped image data.';
                    } else {
                        // Generate unique sanitized name
                        $newFileName = uniqid('player_', true) . '.' . $fileExt;
                        $uploadDir = __DIR__ . '/uploads/';
                        
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        $destPath = $uploadDir . $newFileName;
                        
                        if (file_put_contents($destPath, $croppedData)) {
                            // Save to Database
                            $stmt = $pdo->prepare("INSERT INTO players (name, mobile, place, role, profile_image, payment_utr, payment_status, base_price) VALUES (?, ?, ?, ?, ?, ?, 'Pending', 100)");
                            $stmt->execute([$name, $mobile, $place, $role, $newFileName, $utr]);
                            
                            $successMsg = "🎉 Registration submitted successfully! Your cropped profile photo is queued for Admin approval.";
                            // Reset form values
                            $name = $mobile = $place = $role = '';
                        } else {
                            $errorMsg = '❌ Saving cropped image failed. Please try again.';
                        }
                    }
                } else {
                    $errorMsg = '❌ Invalid image format.';
                }
            } else {
                // Fallback to standard file upload if cropper wasn't used
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
                        $actualMime = function_exists('mime_content_type') ? mime_content_type($fileTmpPath) : $fileType;
                        
                        if (!in_array($actualMime, $allowedMimes)) {
                            $errorMsg = '❌ Invalid file type. Only JPG, JPEG, and PNG images are allowed.';
                        } else {
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            if (!in_array($fileExt, ['jpg', 'jpeg', 'png'])) {
                                $fileExt = ($actualMime === 'image/png') ? 'png' : 'jpg';
                            }
                            
                            $newFileName = uniqid('player_', true) . '.' . $fileExt;
                            $uploadDir = __DIR__ . '/uploads/';
                            
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            $destPath = $uploadDir . $newFileName;
                            
                            if (move_uploaded_file($fileTmpPath, $destPath)) {
                                $stmt = $pdo->prepare("INSERT INTO players (name, mobile, place, role, profile_image, payment_utr, payment_status, base_price) VALUES (?, ?, ?, ?, ?, ?, 'Pending', 100)");
                                $stmt->execute([$name, $mobile, $place, $role, $newFileName, $utr]);
                                
                                $successMsg = "🎉 Registration submitted successfully! Your profile is queued for Admin approval.";
                                $name = $mobile = $place = $role = '';
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Registration — SMCL 2026</title>
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
    <!-- Cropper.js CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
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
            <img src="uploads/league_logo.png" alt="SMCL Logo" class="w-16 h-16 object-contain mx-auto mb-3">
            <a href="index.php" class="text-xs uppercase tracking-widest text-gold-400 hover:text-gold-300 font-semibold mb-2 inline-flex items-center gap-1.5 transition">
                <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Live Dashboard
            </a>
            <h1 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-gold-300 to-amber-500 mt-1">
                SHAMSU MEMORIAL CRICKET LEAGUE
            </h1>
            <p class="text-gray-400 text-xs mt-1 uppercase tracking-widest font-semibold">SMCL 2026 — Player Registration Pool</p>
        </div>

        <!-- Feedback Messages -->
        <?php if (!empty($successMsg)): ?>
            <div class="mx-6 mt-6 bg-gold-950/20 border border-gold-500/40 text-gold-300 px-5 py-4 rounded-xl text-sm flex items-center gap-3">
                <i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>
                <div><?php echo $successMsg; ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="mx-6 mt-6 bg-red-950/20 border border-red-500/40 text-red-300 px-5 py-4 rounded-xl text-sm flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-red-400 text-lg"></i>
                <div><?php echo $errorMsg; ?></div>
            </div>
        <?php endif; ?>

        <?php if (!$registrationEnabled): ?>
            <!-- Disabled Notice -->
            <div class="p-10 text-center space-y-5">
                <div class="w-16 h-16 bg-red-950/20 border border-red-500/30 text-red-400 rounded-full flex items-center justify-center mx-auto text-2xl">
                    <i class="fa-solid fa-ban font-bold"></i>
                </div>
                <div class="space-y-2">
                    <h3 class="text-xl font-bold text-white tracking-tight">Public Registrations Closed</h3>
                    <p class="text-xs text-gray-400 leading-relaxed max-w-md mx-auto">
                        Player registrations for the SMCL 2026 Season are currently closed. Please contact the league administrators or your franchise managers for more information.
                    </p>
                </div>
                <div class="pt-2">
                    <a href="index.php" class="bg-white/5 border border-white/10 hover:border-white/20 text-[10px] font-bold uppercase tracking-wider px-6 py-3 rounded-xl text-gray-300 hover:text-white transition inline-block">
                        <i class="fa-solid fa-arrow-left mr-1.5 text-[10px]"></i> Back to Live Auction
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Form Container -->
            <form action="register.php" method="POST" enctype="multipart/form-data" class="p-6 md:p-8 max-w-xl mx-auto space-y-6">
            
            <div class="space-y-6">
                <h3 class="text-lg font-bold text-gold-400 border-b border-white/5 pb-2 flex items-center gap-2">
                    <i class="fa-solid fa-baseball-bat-ball text-gold-400 text-lg"></i> Player Registration Details
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
                        <input type="hidden" name="cropped_image_data" id="cropped_image_data">
                        <div class="space-y-1" id="upload-prompt">
                            <i class="fa-solid fa-camera text-gold-400 text-2xl block mx-auto"></i>
                            <p class="text-xs text-gray-300 font-semibold">Click to upload or drag & drop</p>
                            <p class="text-[10px] text-gray-500">MIME validation will enforce real image files only.</p>
                        </div>
                        <div class="hidden space-y-1 text-gold-400 font-medium text-xs animate-pulse" id="upload-feedback">
                            <i class="fa-solid fa-star text-gold-400 text-xl block mx-auto"></i>
                            <p id="file-name-display"></p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                        class="w-full bg-gradient-to-r from-gold-500 to-amber-600 text-black font-extrabold uppercase text-xs tracking-wider py-4 px-6 rounded-xl hover:from-gold-400 hover:to-gold-500 transition duration-300 mt-2 shadow-lg shadow-gold-500/10 active:scale-95">
                    Submit Registration
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Cropping Modal -->
    <div id="cropModal" class="fixed inset-0 z-[100] hidden bg-black/90 backdrop-blur-md flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-zinc-950 border border-gold-500/20 rounded-2xl p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-white/5 pb-3">
                <h3 class="text-base font-bold text-gold-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-crop text-gold-400"></i> Adjust & Crop Photo
                </h3>
                <button type="button" onclick="closeCropModal()" class="text-gray-400 hover:text-white flex items-center justify-center w-6 h-6 rounded-full hover:bg-white/5">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
            
            <!-- Cropper Area -->
            <div class="w-full max-h-[50vh] overflow-hidden rounded-xl bg-black border border-white/10 flex items-center justify-center">
                <img id="cropper-target" src="" class="max-w-full max-h-[50vh]">
            </div>
            
            <p class="text-[10px] text-gray-500 text-center uppercase tracking-wider">Drag to position • Pinch/Scroll to zoom</p>
            
            <!-- Actions -->
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeCropModal()"
                        class="flex-1 bg-zinc-900 border border-white/5 text-gray-400 font-bold uppercase text-[10px] tracking-wider py-3 rounded-xl hover:bg-white/5 transition">
                    Cancel
                </button>
                <button type="button" onclick="performCrop()"
                        class="flex-1 bg-gold-500 text-black font-extrabold uppercase text-[10px] tracking-wider py-3 rounded-xl hover:bg-gold-400 transition">
                    Crop & Save
                </button>
            </div>
        </div>
    </div>

    <!-- Script for File Input Preview & Cropping -->
    <script>
        let cropper = null;
        const fileInput = document.getElementById('profile_image');
        const promptDiv = document.getElementById('upload-prompt');
        const feedbackDiv = document.getElementById('upload-feedback');
        const nameDisplay = document.getElementById('file-name-display');
        
        const cropModal = document.getElementById('cropModal');
        const cropperTarget = document.getElementById('cropper-target');
        const croppedInput = document.getElementById('cropped_image_data');

        fileInput.addEventListener('change', (e) => {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                
                // Show modal & start cropper
                const reader = new FileReader();
                reader.onload = function(event) {
                    cropperTarget.src = event.target.result;
                    cropModal.classList.remove('hidden');
                    
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    cropper = new Cropper(cropperTarget, {
                        aspectRatio: 9 / 10,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 1,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        function closeCropModal() {
            cropModal.classList.add('hidden');
            fileInput.value = ''; // Reset input
            croppedInput.value = '';
            promptDiv.classList.remove('hidden');
            feedbackDiv.classList.add('hidden');
        }

        function performCrop() {
            if (!cropper) return;
            
            // Get cropped canvas optimized for profile cards
            const canvas = cropper.getCroppedCanvas({
                width: 360,
                height: 400,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            // Convert to base64
            const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
            croppedInput.value = dataUrl;
            
            // UI feedback
            nameDisplay.innerText = '✓ Photo cropped and formatted successfully!';
            promptDiv.classList.add('hidden');
            feedbackDiv.classList.remove('hidden');
            cropModal.classList.add('hidden');
        }
    </script>
</body>
</html>
