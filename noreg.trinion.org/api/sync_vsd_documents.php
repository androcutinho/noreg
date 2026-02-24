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
    require_once(__DIR__ . '/getVetDocumentList.php');
    require_once(__DIR__ . '/vetis_vsd_sync.php');

    
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
    
    
    $all_documents = [];
    $api_errors = [];
    
    foreach ($guids as $index => $guid) {
        
        if ($index > 0) {
            sleep(2);
        }
        
        $api_result = fetchDocumentList($guid);
        
        if ($api_result['success'] && isset($api_result['data'])) {
            $all_documents = array_merge($all_documents, $api_result['data']);
        } else {
            $api_errors[] = "Ошибка загрузки данных для кода $guid: " . ($api_result['error'] ?? 'Неизвестная ошибка');
        }
    }
    
    if (empty($all_documents)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Не удалось загрузить документы из API. ' . implode('; ', $api_errors)
        ]);
        exit;
    }

    
    $sync_result = syncDocumentsToDatabase($all_documents, $mysqli);

    
    echo json_encode([
        'success' => true,
        'inserted' => $sync_result['inserted'],
        'updated' => $sync_result['updated'],
        'skipped' => $sync_result['skipped'],
        'total' => $sync_result['total'],
        'message' => "Синхронизация завершена. Добавлено: {$sync_result['inserted']}, обновлено: {$sync_result['updated']}, пропущено: {$sync_result['skipped']}"
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
