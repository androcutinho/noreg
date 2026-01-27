<?php

// Load shared environment helper
require_once(__DIR__ . '/env_helper.php');

// Load .env file - try multiple locations
$possiblePaths = [
    __DIR__ . '/../../.env',                // Project root
    __DIR__ . '/../../../.env',             // Two levels up
    '/home/trinion-noreg/.env',             // Server home directory
    $_SERVER['DOCUMENT_ROOT'] . '/../.env',  // Parent of web root
];

$envPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $envPath = $path;
        break;
    }
}

if (!$envPath) {
    die("ERROR: .env file not found in any location: " . implode(', ', $possiblePaths));
}

$env = loadEnvFile($envPath);

// Database connection configuration from .env array ONLY
if (empty($env['DB_HOST']) || empty($env['DB_NAME']) || empty($env['DB_USER']) || empty($env['DB_PASSWORD'])) {
    echo "<pre>";
    echo "DEBUG: .env file contents:\n";
    echo file_get_contents($envPath);
    echo "\n\nDEBUG: Parsed ENV array:\n";
    print_r($env);
    echo "</pre>";
    die("ERROR: Missing required keys in .env file (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD)");
}

$host = $env['DB_HOST'];
$db_name = $env['DB_NAME'];
$username = $env['DB_USER'];
$password = $env['DB_PASSWORD'];

try {
    $mysqli = new mysqli($host, $username, $password, $db_name);
    
    if ($mysqli->connect_error) {
        die('Database connection failed: ' . $mysqli->connect_error);
    }
    
    // Set UTF-8 charset
    $mysqli->set_charset('utf8mb4');
    
    return $mysqli;
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}
?>
