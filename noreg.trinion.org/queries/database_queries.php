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

function ObnovitPoleDokumenta($mysqli, $table_name, $field_name, $document_id, $value) {
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
        $stmt = $mysqli->prepare($sql);
        
        if (!$stmt) {
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
        $stmt->close();
        
        
        if ($table_name === 'izmenenie_ostatka_tovarov' && $field_name === 'utverzhden') {
            require_once __DIR__ . '/izmenenie_ostatka_tovarov_queries.php';
            $result = handleUtverzhdenChange($mysqli, $document_id, $value);
            return $result;
        }
        
        if ($table_name === 'postupleniya_tovarov' && $field_name === 'utverzhden') {
            require_once __DIR__ . '/postuplenie_queries.php';
            $result = handleUtverzhdenChange($mysqli, $document_id, $value);
            return $result;
        }
        
        
        if ($table_name === 'otgruzki_tovarov_pokupatelyam' && $field_name === 'utverzhden') {
            require_once __DIR__ . '/otgruzki_tovarov_queries.php';
            $result = handleUtverzhdenChange($mysqli, $document_id, $value);
            return $result;
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


function linkDocumentsByIndex($mysqli, $zakaz_id, $doc_id, $table_name = 'noreg_specifikacii_k_zakazam', $order_table = 'zakazy_pokupatelei') {
    try {
       
        $zakaz_query = "SELECT id_index FROM `" . $mysqli->real_escape_string($order_table) . "` WHERE id = ?";
        $stmt = $mysqli->prepare($zakaz_query);
        if (!$stmt) {
            return ['success' => false];
        }
        $stmt->bind_param('i', $zakaz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $zakaz_row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$zakaz_row) {
            return ['success' => false];
        }
        $index_osnovanie = $zakaz_row['id_index'];
        
        
        $doc_query = "SELECT id_index FROM `" . $mysqli->real_escape_string($table_name) . "` WHERE id = ?";
        $doc_stmt = $mysqli->prepare($doc_query);
        if (!$doc_stmt) {
            return ['success' => false];
        }
        $doc_stmt->bind_param('i', $doc_id);
        $doc_stmt->execute();
        $doc_result = $doc_stmt->get_result();
        $doc_row = $doc_result->fetch_assoc();
        $doc_stmt->close();
        
        if (!$doc_row) {
            return ['success' => false];
        }
        $index_osnovannyj = $doc_row['id_index'];
        
        
        $table_query = "SELECT id FROM index_tablic WHERE nazvanie_tablicy = ?";
        $table_stmt = $mysqli->prepare($table_query);
        if (!$table_stmt) {
            return ['success' => false];
        }
        $table_stmt->bind_param('s', $table_name);
        $table_stmt->execute();
        $table_result = $table_stmt->get_result();
        $table_row = $table_result->fetch_assoc();
        $table_stmt->close();
        
        if (!$table_row) {
            return ['success' => false];
        }
        $id_index_tablic = $table_row['id'];
        
        
        $insert_query = "
            INSERT INTO svyazi_dokumentov (index_osnovanie, index_osnovannyj, id_index_tablic)
            VALUES (?, ?, ?)
        ";
        $insert_stmt = $mysqli->prepare($insert_query);
        if (!$insert_stmt) {
            return ['success' => false];
        }
        $insert_stmt->bind_param('iii', $index_osnovanie, $index_osnovannyj, $id_index_tablic);
        
        if (!$insert_stmt->execute()) {
            return ['success' => false];
        }
        $insert_stmt->close();
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getParentDocumentByIndexOsnovannyj($mysqli, $id_index) {
    try {

        $query = "
            SELECT 
                sd.index_osnovanie,
                sd.id_index_tablic
            FROM svyazi_dokumentov sd
            WHERE sd.index_osnovannyj = ?
            LIMIT 1
        ";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            error_log('Failed to prepare parent document query: ' . $mysqli->error);
            return null;
        }
        
        $stmt->bind_param('i', $id_index);
        if (!$stmt->execute()) {
            error_log('Failed to execute parent document query: ' . $stmt->error);
            $stmt->close();
            return null;
        }
        
        $result = $stmt->get_result();
        $relationship = $result->fetch_assoc();
        $stmt->close();
        
        if (!$relationship) {
            return null;
        }

        $parent_index = $relationship['index_osnovanie'];
        
    
        $parent_table = null;
        $check_query = "SELECT id FROM zakazy_pokupatelei WHERE id_index = ? LIMIT 1";
        $check_stmt = $mysqli->prepare($check_query);
        if ($check_stmt) {
            $check_stmt->bind_param('i', $parent_index);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $parent_table = 'zakazy_pokupatelei';
            }
            $check_stmt->close();
        }
        
        if (!$parent_table) {
            $check_query = "SELECT id FROM zakazy_postavshchikam WHERE id_index = ? LIMIT 1";
            $check_stmt = $mysqli->prepare($check_query);
            if ($check_stmt) {
                $check_stmt->bind_param('i', $parent_index);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $parent_table = 'zakazy_postavshchikam';
                }
                $check_stmt->close();
            }
        }
        
        if (!$parent_table) {
            return null;
        }
        
        $nomer_column = 'nomer';
        $date_column = 'data_dokumenta';
        $employee_column = 'id_otvetstvennyj';
        $has_utverzhden = true;
        
        if ($parent_table === 'zakazy_pokupatelei' || $parent_table === 'zakazy_postavshchikam') {
            $nomer_column = 'nomer';
            $date_column = 'data_dokumenta';
            $employee_column = 'id_otvetstvennyj';
            $has_utverzhden = true;
        }
        
        $utverzhden_clause = $has_utverzhden ? "COALESCE(doc.utverzhden, 0) as utverzhden," : "0 as utverzhden,";
        $parent_table_escaped = '`' . str_replace('`', '``', $parent_table) . '`';
        
        $doc_query = "
            SELECT 
                doc.id,
                doc." . $mysqli->real_escape_string($nomer_column) . " as nomer,
                doc." . $mysqli->real_escape_string($date_column) . " as data_dokumenta,
                " . $utverzhden_clause . "
                CONCAT(COALESCE(s.familiya, ''), ' ', COALESCE(s.imya, ''), ' ', COALESCE(s.otchestvo, '')) AS naimenovanie_otvetstvennogo
            FROM " . $parent_table_escaped . " doc
            LEFT JOIN sotrudniki s ON doc." . $mysqli->real_escape_string($employee_column) . " = s.id
            WHERE doc.id_index = ?
        ";
        
        $doc_stmt = $mysqli->prepare($doc_query);
        if (!$doc_stmt) {
            error_log('Failed to prepare parent details query: ' . $mysqli->error);
            return null;
        }
        
        $doc_stmt->bind_param('i', $parent_index);
        if (!$doc_stmt->execute()) {
            error_log('Failed to execute parent details query: ' . $doc_stmt->error);
            $doc_stmt->close();
            return null;
        }
        
        $doc_result = $doc_stmt->get_result();
        $doc = $doc_result->fetch_assoc();
        $doc_stmt->close();
        
        if ($doc) {
            $doc['document_type'] = 'Заказ';
            $doc['naimenovanie_otvetstvennogo'] = trim($doc['naimenovanie_otvetstvennogo'] ?? '');
        }
        
        return $doc;
    } catch (Exception $e) {
        error_log('Error in getParentDocumentByIndexOsnovannyj: ' . $e->getMessage());
        return null;
    }
}



?>
