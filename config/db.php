<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
    // Failsafe: Search in 'config/', root 'public_html/', or ONE folder above public_html (Git-Safe & Permanent!)
    $prodFileInConfig = __DIR__ . '/db_prod.php';
    $prodFileInRoot = dirname(__DIR__) . '/db_prod.php';
    $prodFileAboveRoot = dirname(dirname(__DIR__)) . '/db_prod.php';

    if (file_exists($prodFileInConfig)) {
        require_once $prodFileInConfig;
    } elseif (file_exists($prodFileInRoot)) {
        require_once $prodFileInRoot;
    } elseif (file_exists($prodFileAboveRoot)) {
        require_once $prodFileAboveRoot;
    } else {
        // Highly descriptive HTML diagnostic block to help you resolve this instantly!
        die("<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 25px; border: 1px solid #e11d48; background: #fff1f2; border-radius: 12px;'>
                <h3 style='color: #be123c; margin-top: 0;'>🚨 Secret Credentials File Not Found</h3>
                <p style='color: #4c0519; font-size: 14px; line-height: 1.5;'>
                    The database system is running in Hostinger mode, but it could not find your secret <strong>db_prod.php</strong> file.
                </p>
                <p style='color: #4c0519; font-size: 14px;'>The server searched these three paths (Option 3 is recommended and Git-safe!):</p>
                <ol style='font-family: monospace; font-size: 12px; background: rgba(0,0,0,0.05); padding: 10px 10px 10px 30px; border-radius: 6px;'>
                    <li>$prodFileInConfig (Wiped on git push)</li>
                    <li>$prodFileInRoot (Wiped on git push)</li>
                    <li style='color: #047857; font-weight: bold;'>$prodFileAboveRoot (PERMANENT & Git-Safe!)</li>
                </ol>
                <p style='color: #4c0519; font-size: 14px; line-height: 1.5;'>
                    Please open your <strong>Hostinger File Manager</strong>, navigate to one folder <strong>ABOVE</strong> <code>public_html</code> (your domain's main folder), create a file named exactly <code>db_prod.php</code>, and save your credentials there!
                </p>
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
