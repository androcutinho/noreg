<?php

// Database connection configuration
$host = 'localhost';
$db_name = 'noreg';
$username = 'noreg';
$password = 'v6Hz9=BfX&yZA';

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
