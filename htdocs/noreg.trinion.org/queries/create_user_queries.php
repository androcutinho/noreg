<?php

function isUserAdmin($mysqli, $user_id) {
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


function createUser($mysqli, $user_name, $email, $password, $user_role) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (user_name, email, user_password_hash, user_role) VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->stmt_init();
    
    if (!$stmt->prepare($sql)) {
        return array('success' => false, 'message' => "SQL error: " . $mysqli->error);
    }
    
    $stmt->bind_param("sssi", $user_name, $email, $password_hash, $user_role);
    
    if ($stmt->execute()) {
        return array('success' => true, 'message' => 'User created successfully');
    } else {
        if ($mysqli->errno === 1062) {
            return array('success' => false, 'message' => 'Email already exists');
        } else {
            return array('success' => false, 'message' => "Error creating user: " . $mysqli->error);
        }
    }
}

?>
