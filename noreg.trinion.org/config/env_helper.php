<?php

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
        
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        
        if (strpos($line, '=') === false) {
            continue;
        }
        
       
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        
        
        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
            (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
            $value = substr($value, 1, -1);
        }
        
        $env[$key] = $value;
    }
    
    return $env;
}
?>
