<?php

/**
 * Fetch current user information
 * @param mysqli $mysqli Database connection
 * @param int $user_id User ID
 * @return array|null User data array or null if not found
 */
function fetchUserById($mysqli, $user_id) {
    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $mysqli->stmt_init();
    
    if (!$stmt->prepare($sql)) {
        return null;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

?>
