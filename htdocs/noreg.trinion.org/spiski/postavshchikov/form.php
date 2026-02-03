<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit();
}

$page_title = 'Редактировать поставщика';

$mysqli = require '../../config/database.php';
require '../../queries/spisok_postavshchikov_queries.php';

$postavshchik_id = isset($_GET['postavshchik_id']) ? intval($_GET['postavshchik_id']) : null;

// If postavshchik_id is provided, fetch the postavshchik data
if ($postavshchik_id) {
    $postavshchik = getPostavshchikById($mysqli, $postavshchik_id);
    
    if (!$postavshchik) {
        die('Поставщик не найден');
    }
    
    $is_creating = false;
} else {
    $postavshchik = null;
    $is_creating = true;
    $page_title = 'Создать поставщика';
}

$error_message = '';
$success_message = '';

if (isset($_POST['cancel'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    if (isset($_POST['naimenovanie'])) {
        $naimenovanie = trim($_POST['naimenovanie']);
        
        if (empty($naimenovanie)) {
            $error_message = 'Название поставщика не может быть пустым';
        } else {
            
            // Check if name already exists (excluding current postavshchik if editing)
            if (postavshchikNameExists($mysqli, $naimenovanie, $postavshchik_id)) {
                $error_message = 'Поставщик с таким названием уже существует';
            } else {
                $postavshchik_saved = false;
                
                if ($is_creating) {
                    // Create new postavshchik
                    if (insertPostavshchik($mysqli, $naimenovanie)) {
                        $postavshchik_saved = true;
                    } else {
                        $error_message = 'Ошибка при добавлении поставщика';
                    }
                } else {
                    // Update existing postavshchik
                    if (updatePostavshchik($mysqli, $postavshchik_id, $naimenovanie)) {
                        $postavshchik_saved = true;
                    } else {
                        $error_message = 'Ошибка при сохранении данных';
                    }
                }
                
                if ($postavshchik_saved) {
                    $_SESSION['success_message'] = 'Данные успешно сохранены';
                    header('Location: index.php');
                    exit();
                }
            }
        }
    } else {
        $error_message = 'Отсутствуют обязательные поля';
    }
}

include '../../header.php';
?>

<div class="page-body">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><?= $is_creating ? 'Создать поставщика' : 'Редактировать поставщика' ?></h3>
            </div>

            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="max-width: 500px;">
                    <div class="mb-3">
                        <label class="form-label" for="naimenovanie">Название поставщика</label>
                        <input class="form-control" type="text" id="naimenovanie" name="naimenovanie" value="<?= htmlspecialchars($postavshchik['naimenovanie'] ?? '') ?>" placeholder="Введите название поставщика...">
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <?= $is_creating ? 'Создать' : 'Сохранить' ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="history.back()">Отмена</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../footer.php'; ?>
