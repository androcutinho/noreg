<?php

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
