<?php

// Load environment variables from .env file into array
function loadEnvFile($filePath) {
    $env = [];
    
    if (!file_exists($filePath)) {
        die("Error: .env file not found at $filePath");
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        die("Error: Could not read .env file at $filePath");
    }
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Only process lines with =
        if (strpos($line, '=') === false) {
            continue;
        }
        
        // Split on first = only (important for passwords with =)
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        
        // Remove quotes if present
        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
            (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
            $value = substr($value, 1, -1);
        }
        
        $env[$key] = $value;
    }
    
    return $env;
}

// Load .env file from server root
$envPath = '/home/trinion-noreg/.env';

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
