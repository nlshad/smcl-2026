<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set default timezone globally to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// config/db.php

$charset = 'utf8mb4';

// Robust Auto-detect environment (Looks strictly at what is in your browser's address bar)
$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = $hostHeader === 'localhost' 
           || $hostHeader === '127.0.0.1' 
           || str_starts_with($hostHeader, '127.0.0.1:') 
           || str_starts_with($hostHeader, '192.168.');


if ($isLocal) {
    $host = '127.0.0.1';
    $db   = 'smcl_db';
    $user = 'root';
    $pass = '';
} else {
    // Failsafe: Search in 'config/', root 'public_html/', or ONE folder above public_html (Git-Safe & Permanent!)
    $prodFileInConfig = __DIR__ . '/db_prod.php';
    $prodFileInRoot = dirname(__DIR__) . '/db_prod.php';
    $prodFileAboveRoot = dirname(dirname(__DIR__)) . '/db_prod.php';

    // Self-healing scanner: Check all three paths and load the first one that is actually populated!
    $loaded = false;

    if (file_exists($prodFileInConfig)) {
        @include $prodFileInConfig;
        if (isset($host, $db, $user, $pass)) {
            $loaded = true;
        }
    }

    if (!$loaded && file_exists($prodFileInRoot)) {
        @include $prodFileInRoot;
        if (isset($host, $db, $user, $pass)) {
            $loaded = true;
        }
    }

    if (!$loaded && file_exists($prodFileAboveRoot)) {
        @include $prodFileAboveRoot;
        if (isset($host, $db, $user, $pass)) {
            $loaded = true;
        }
    }

    if (!$loaded) {
        $parentDir = dirname(dirname(__DIR__));
        $parentContents = [];
        $permissionStatus = "Readable";

        // Try to scan the parent directory to see what is actually there!
        if (is_dir($parentDir)) {
            $files = @scandir($parentDir);
            if ($files === false) {
                $permissionStatus = "Blocked by server security (open_basedir restriction is active!)";
            } else {
                $parentContents = array_filter($files, function($f) {
                    return $f !== '.' && $f !== '..';
                });
            }
        } else {
            $permissionStatus = "Parent folder not accessible";
        }

        $filesListHTML = "";
        if (!empty($parentContents)) {
            $filesListHTML = "<div style='margin-top: 15px; background: rgba(0,0,0,0.03); padding: 12px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.05);'>
                                <strong style='color: #be123c; font-size: 11px; text-transform: uppercase;'>📁 Live Scan of Parent Folder ($parentDir):</strong>
                                <ul style='font-family: monospace; font-size: 11px; margin: 8px 0 0 0; padding-left: 20px; color: #4b5563;'>";
            foreach ($parentContents as $file) {
                $color = ($file === 'db_prod.php') ? '#047857; font-weight: bold;' : '#374151;';
                $filesListHTML .= "<li style='color: $color;'>$file</li>";
            }
            $filesListHTML .= "</ul></div>";
        } else {
            $filesListHTML = "<div style='margin-top: 15px; color: #991b1b; font-size: 11px; font-weight: bold;'>
                                ⚠️ Parent Folder Scan: Empty or $permissionStatus
                              </div>";
        }

        // Highly descriptive HTML diagnostic block to help you resolve this instantly!
        die("<div style='font-family: sans-serif; max-width: 650px; margin: 40px auto; padding: 25px; border: 1px solid #e11d48; background: #fff1f2; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                <h3 style='color: #be123c; margin-top: 0; font-size: 18px;'>🚨 Secret Credentials File Not Found</h3>
                <p style='color: #4c0519; font-size: 13px; line-height: 1.6;'>
                    The database system is running in Hostinger mode, but it could not locate a valid <strong>db_prod.php</strong> credentials file.
                </p>
                
                <p style='color: #4c0519; font-size: 13px;'>The loader searched these three exact paths:</p>
                <ol style='font-family: monospace; font-size: 11px; background: rgba(0,0,0,0.04); padding: 12px 12px 12px 30px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.03); line-height: 1.7;'>
                    <li>$prodFileInConfig (Wiped on git push)</li>
                    <li>$prodFileInRoot (Wiped on git push)</li>
                    <li style='color: #047857; font-weight: bold;'>$prodFileAboveRoot (PERMANENT & Git-Safe!)</li>
                </ol>
                
                $filesListHTML

                <div style='margin-top: 15px; padding: 12px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; font-size: 12px; color: #78350f; line-height: 1.5;'>
                    <strong>💡 How to fix this instantly:</strong><br>
                    1. Open your <strong>Hostinger File Manager</strong>.<br>
                    2. Navigate to your main directory <code>ppllive.online</code> (where you can see the <code>public_html</code> folder icon).<br>
                    3. Create a file named exactly <code style='background: rgba(0,0,0,0.06); padding: 2px 4px; border-radius: 4px;'>db_prod.php</code> right next to <code>public_html</code>.<br>
                    4. Save your MySQL details inside it starting with <code>&lt;?php</code>!
                </div>
             </div>");
             
        $host = 'localhost'; 
        $db   = 'your_hostinger_db'; 
        $user = 'your_hostinger_user'; 
        $pass = 'your_hostinger_password'; 
    }
}







$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // If the database doesn't exist yet, we allow a fallback connection to establish it in setup.php
     if ($e->getCode() == 1049) { 
         // Database not found, setup.php will handle creating it.
         $pdo = null;
     } else {
         throw new \PDOException($e->getMessage(), (int)$e->getCode());
     }
}
?>
