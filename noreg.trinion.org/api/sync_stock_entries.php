<?php

ob_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');


if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

try {
    $mysqli = require(__DIR__ . '/../config/database.php');
    require_once(__DIR__ . '/GetStockEntryListOperation_v2.php');

    
    ob_end_clean();

    
    $sql = "SELECT DISTINCT enterpriseGuid FROM vetis_predpriyatiya WHERE enterpriseGuid IS NOT NULL AND enterpriseGuid != ''";
    $result = $mysqli->query($sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка запроса к базе данных: ' . htmlspecialchars($mysqli->error)
        ]);
        exit;
    }
    
    $guids = [];
    while ($row = $result->fetch_assoc()) {
        $guids[] = $row['enterpriseGuid'];
    }
    
    if (empty($guids)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Не найдены коды предприятий в таблице vetis_predpriyatiya'
        ]);
        exit;
    }
    
    
    $all_stock_entries = [];
    $api_errors = [];
    
    foreach ($guids as $index => $guid) {
        
        if ($index > 0) {
            sleep(4);
        }
        
        $api_result = fetchDocumentList($guid);
        
        if ($api_result['success'] && isset($api_result['data'])) {
            $all_stock_entries = array_merge($all_stock_entries, $api_result['data']);
        } else {
            $api_errors[] = "Ошибка загрузки данных для кода $guid: " . ($api_result['error'] ?? 'Неизвестная ошибка');
        }
    }
    
    if (empty($all_stock_entries)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Не удалось загрузить записи складского журнала из API. ' . implode('; ', $api_errors)
        ]);
        exit;
    }

    
    
    $saved_count = 0;
    $save_errors = [];
    
    foreach ($all_stock_entries as $entry) {
        try {
            
            $product_check_sql = "SELECT id FROM tovary_i_uslugi WHERE naimenovanie = ? LIMIT 1";
            $product_check_stmt = $mysqli->stmt_init();
            
            $id_tovary_i_uslugi = null;
            
            if ($product_check_stmt->prepare($product_check_sql)) {
                $product_check_stmt->bind_param("s", $entry['product_name']);
                $product_check_stmt->execute();
                $product_check_result = $product_check_stmt->get_result();
                
                if ($product_row = $product_check_result->fetch_assoc()) {
                    
                    $id_tovary_i_uslugi = $product_row['id'];
                } else {
                    
                    $product_insert_sql = "INSERT INTO tovary_i_uslugi (naimenovanie) VALUES (?)";
                    $product_insert_stmt = $mysqli->stmt_init();
                    
                    if ($product_insert_stmt->prepare($product_insert_sql)) {
                        $product_insert_stmt->bind_param("s", $entry['product_name']);
                        
                        if ($product_insert_stmt->execute()) {
                            $id_tovary_i_uslugi = $mysqli->insert_id;
                        } else {
                            throw new Exception('Не удалось вставить товар: ' . $product_insert_stmt->error);
                        }
                        
                        $product_insert_stmt->close();
                    }
                }
                
                $product_check_stmt->close();
            }
            
            
           
            $unit_check_sql = "SELECT id FROM edinicy_izmereniya WHERE naimenovanie = ? LIMIT 1";
            $unit_check_stmt = $mysqli->stmt_init();
            
            $id_edinicy_izmereniya = null;
            
            if ($unit_check_stmt->prepare($unit_check_sql)) {
                $unit_check_stmt->bind_param("s", $entry['unit']);
                $unit_check_stmt->execute();
                $unit_check_result = $unit_check_stmt->get_result();
                
                if ($unit_row = $unit_check_result->fetch_assoc()) {
                
                    $id_edinicy_izmereniya = $unit_row['id'];
                } else {
                    
                    $unit_insert_sql = "INSERT INTO edinicy_izmereniya (naimenovanie) VALUES (?)";
                    $unit_insert_stmt = $mysqli->stmt_init();
                    
                    if ($unit_insert_stmt->prepare($unit_insert_sql)) {
                        $unit_insert_stmt->bind_param("s", $entry['unit']);
                        
                        if ($unit_insert_stmt->execute()) {
                            $id_edinicy_izmereniya = $mysqli->insert_id;
                        } else {
                            throw new Exception('Failed to insert unit: ' . $unit_insert_stmt->error);
                        }
                        
                        $unit_insert_stmt->close();
                    }
                }
                
                $unit_check_stmt->close();
            }
            
            if ($id_tovary_i_uslugi !== null && $id_edinicy_izmereniya !== null) {
                $id_pred = (int)$entry['id_predpriyatiya'];
                $id_tovar = (int)$id_tovary_i_uslugi;
                $id_unit = (int)$id_edinicy_izmereniya;
                $ostatok = (float)$entry['remaining_amount'];
                $vsd_uuid = $entry['vsd_uuid'];
                
                
                $check_sql = "SELECT id FROM vetis_ostatki WHERE id_predpriyatiya = ? AND id_tovary_i_uslugi = ? AND vetis_uuid = ? LIMIT 1";
                $check_stmt = $mysqli->stmt_init();
                
                $entry_exists = false;
                if ($check_stmt->prepare($check_sql)) {
                    $check_stmt->bind_param("iis", $id_pred, $id_tovar, $vsd_uuid);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->fetch_assoc()) {
                        $entry_exists = true;
                    }
                    
                    $check_stmt->close();
                }
                
                if ($entry_exists) {
                    
                    $update_sql = "UPDATE vetis_ostatki SET ostatok = ?, id_edinicy_izmereniya = ? WHERE id_predpriyatiya = ? AND id_tovary_i_uslugi = ? AND vetis_uuid = ?";
                    $update_stmt = $mysqli->stmt_init();
                    
                    if ($update_stmt->prepare($update_sql)) {
                        $update_stmt->bind_param("diiis", $ostatok, $id_unit, $id_pred, $id_tovar, $vsd_uuid);
                        
                        if ($update_stmt->execute()) {
                            $saved_count++;
                        } else {
                            $save_errors[] = "Failed to update entry for product: " . $entry['product_name'];
                        }
                        
                        $update_stmt->close();
                    }
                } else {
                    
                    $insert_sql = "INSERT INTO vetis_ostatki (id_predpriyatiya, id_tovary_i_uslugi, ostatok, id_edinicy_izmereniya, vetis_uuid) VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = $mysqli->stmt_init();
                    
                    if ($insert_stmt->prepare($insert_sql)) {
                        $insert_stmt->bind_param("iidis", $id_pred, $id_tovar, $ostatok, $id_unit, $vsd_uuid);
                        
                        if ($insert_stmt->execute()) {
                            $saved_count++;
                        } else {
                            $save_errors[] = "Failed to insert entry for product: " . $entry['product_name'];
                        }
                        
                        $insert_stmt->close();
                    }
                }
            }
        } catch (Exception $e) {
            $save_errors[] = "Error processing product " . $entry['product_name'] . ": " . $e->getMessage();
        }
    }

    
    echo json_encode([
        'success' => true,
        'total' => count($all_stock_entries),
        'saved' => $saved_count,
        'data' => $all_stock_entries,
        'message' => "Получено " . count($all_stock_entries) . " записей из складского журнала, сохранено " . $saved_count . " записей"
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка сервера: ' . htmlspecialchars($e->getMessage())
    ]);
    exit;
}
