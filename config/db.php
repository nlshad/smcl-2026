<?php
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
    // HOSTINGER PRODUCTION CREDENTIALS
    // Replace these values with the database details you create in your Hostinger hPanel!
    $host = 'localhost'; 
    $db   = 'u123456789_smcl_db'; 
    $user = 'u123456789_smcl_user'; 
    $pass = 'your_hostinger_database_password'; 
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
