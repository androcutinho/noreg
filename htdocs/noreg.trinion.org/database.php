<?php

// Load environment variables from .env file
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') === false || strpos($line, '#') === 0) {
            continue;
        }
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remove quotes if present
        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
            (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
            $value = substr($value, 1, -1);
        }
        
        putenv("$key=$value");
    }
}

// Load .env file from project root
$envPath = __DIR__ . '/../../.env';
loadEnv($envPath);

// Database connection configuration from environment variables
$host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'noreg';
$username = getenv('DB_USER') ?: 'noreg';
$password = getenv('DB_PASSWORD') ?: '';

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
