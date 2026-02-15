<?php


function fetchTableData($mysqli, $table, $idCol, $nameCol, $orderBy = null, $extraCondition = null) {
    $sql = "SELECT {$idCol}, {$nameCol} FROM {$table}";
    if ($extraCondition) {
        $sql .= " WHERE {$extraCondition}";
    }
    if ($orderBy) {
        $sql .= " ORDER BY {$orderBy}";
    }
    
    $result = $mysqli->query($sql);
    $data = array();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } else {
        error_log("Query error for {$table}: " . $mysqli->error);
    }
    
    return $data;
}

function fetchUsers($mysqli) {
    $users_list = array();
    $users_sql = "SELECT user_id, user_name FROM users WHERE user_id != ? ORDER BY user_name";
    $users_stmt = $mysqli->stmt_init();
    if ($users_stmt->prepare($users_sql)) {
        $users_stmt->bind_param("i", $_SESSION['user_id']);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();
        while ($row = $users_result->fetch_assoc()) {
            $users_list[] = $row;
        }
    } else {
        error_log("Users query error: " . $mysqli->error);
    }
    return $users_list;
}

function getUserRole($mysqli, $user_id) {
    $sql = "SELECT user_role FROM users WHERE user_id = ?";
    $stmt = $mysqli->stmt_init();
    
    if (!$stmt->prepare($sql)) {
        return null;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    return $user ? $user['user_role'] : null;
}

function updateDocumentField($mysqli, $table_name, $field_name, $document_id, $value) {
    try {
        $field_value = $value ? 1 : 0;
        $document_id = intval($document_id);
        
        // Validate field name to prevent SQL injection
        $allowed_fields = ['utverzhden', 'zakryt'];
        if (!in_array($field_name, $allowed_fields)) {
            return array(
                'success' => false,
                'message' => 'Недопустимое имя поля'
            );
        }
        
        $sql = "UPDATE {$table_name} SET {$field_name} = ? WHERE id = ?";
        $stmt = $mysqli->stmt_init();
        
        if (!$stmt->prepare($sql)) {
            return array(
                'success' => false,
                'message' => 'Ошибка подготовки запроса: ' . $mysqli->error
            );
        }
        
        $stmt->bind_param("ii", $field_value, $document_id);
        
        if (!$stmt->execute()) {
            return array(
                'success' => false,
                'message' => 'Ошибка обновления документа: ' . $stmt->error
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Статус документа обновлен'
        );
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Ошибка: ' . $e->getMessage()
        );
    }
}

?>
