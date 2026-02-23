<?php

session_start();


if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit();
}

$page_title = 'Редактировать склад';

$mysqli = require '../config/database.php';
require '../queries/spisok_skladov_queries.php';

$sklad_id = isset($_GET['sklad_id']) ? intval($_GET['sklad_id']) : null;


if ($sklad_id) {
    $sklad = getSkladById($mysqli, $sklad_id);
    
    if (!$sklad) {
        die('Склад не найден');
    }
    
    $is_creating = false;
} else {
    $sklad = null;
    $is_creating = true;
    $page_title = 'Создать склад';
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
            $error_message = 'Название склада не может быть пустым';
        } else {
            
            
            if (skladNameExists($mysqli, $naimenovanie, $sklad_id)) {
                $error_message = 'Склад с таким названием уже существует';
            } else {
                $sklad_saved = false;
                
                if ($is_creating) {
                    
                    if (insertSklad($mysqli, $naimenovanie)) {
                        $sklad_saved = true;
                    } else {
                        $error_message = 'Ошибка при добавлении склада';
                    }
                } else {
                    
                    if (updateSklad($mysqli, $sklad_id, $naimenovanie)) {
                        $sklad_saved = true;
                    } else {
                        $error_message = 'Ошибка при сохранении данных';
                    }
                }
                
                if ($sklad_saved) {
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

include '../header.php';
?>

<div class="page-body">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><?= $is_creating ? 'Создать склад' : 'Редактировать склад' ?></h3>
            </div>

            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="max-width: 500px;">
                    <div class="mb-3">
                        <label class="form-label" for="naimenovanie">Название склада</label>
                        <input class="form-control" type="text" id="naimenovanie" name="naimenovanie" value="<?= htmlspecialchars($sklad['naimenovanie'] ?? '') ?>" placeholder="Введите название склада..." autocomplete="off">
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

<?php include '../footer.php'; ?>
