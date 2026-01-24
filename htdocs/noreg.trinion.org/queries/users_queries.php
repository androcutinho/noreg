<?php

/**
 * Check if logged in user is admin
 * @param mysqli $mysqli Database connection
 * @param int $user_id User ID
 * @return bool True if user is admin, false otherwise
 */
function isUserAdminById($mysqli, $user_id) {
    $sql = "SELECT user_role FROM users WHERE user_id = ?";
    $stmt = $mysqli->stmt_init();
    
    if (!$stmt->prepare($sql)) {
        return false;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    return $user && $user['user_role'] ? true : false;
}

/**
 * Fetch all users
 * @param mysqli $mysqli Database connection
 * @return array Array of all users
 */
function fetchAllUsers($mysqli) {
    $sql = "SELECT user_id, user_name, email, user_role FROM users ORDER BY user_id DESC";
    $result = $mysqli->query($sql);
    $users = array();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    return $users;
}

?>
