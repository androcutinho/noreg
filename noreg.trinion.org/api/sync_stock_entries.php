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
            sleep(2);
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

    
    echo json_encode([
        'success' => true,
        'total' => count($all_stock_entries),
        'data' => $all_stock_entries,
        'message' => "Получено " . count($all_stock_entries) . " записей из складского журнала"
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
