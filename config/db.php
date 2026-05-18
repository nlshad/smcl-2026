<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// config/db.php

$charset = 'utf8mb4';

// Auto-detect environment (Local XAMPP vs. Hostinger Cloud)
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) 
           || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' 
           || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost';

if ($isLocal) {
    $host = '127.0.0.1';
    $db   = 'smcl_db';
    $user = 'root';
    $pass = '';
} else {
    // HOSTINGER PRODUCTION (Loads from an untracked file for GitHub security!)
    $prodFile = __DIR__ . '/db_prod.php';
    if (file_exists($prodFile)) {
        require_once $prodFile;
    } else {
        // Fallback placeholders in case the file isn't created yet
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
