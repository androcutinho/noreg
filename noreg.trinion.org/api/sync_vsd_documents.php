<?php
// Buffer output to prevent accidental whitespace or errors before JSON
ob_start();

// Hide errors from output, log them instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

// Authentication check
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
    // Database and sync logic
    $mysqli = require(__DIR__ . '/../config/database.php');
    require_once(__DIR__ . '/getVetDocumentList.php');
    require_once(__DIR__ . '/vetis_vsd_sync.php');

    // Clear any output from includes
    ob_end_clean();

    // Fetch data from API (do not touch this logic)
    $api_result = fetchDocumentList();

    if (!$api_result['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка загрузки данных VETIS: ' . htmlspecialchars($api_result['error'])
        ]);
        exit;
    }

    // Sync documents to database
    $sync_result = syncDocumentsToDatabase($api_result['data'], $mysqli);

    // Return result as JSON
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
