<?php

session_start();


if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit();
}

$page_title = 'Редактировать товар';

$mysqli = require '../config/database.php';
require '../queries/spisok_tovarov_queries.php';

$tov_id = isset($_GET['tov_id']) ? intval($_GET['tov_id']) : null;


if ($tov_id) {
    $tov_us = getTovarById($mysqli, $tov_id);
    
    if (!$tov_us) {
        die('Товар и услуг не найден');
    }
    $poserijnyj_uchet = $tov_us['poserijnyj_uchet'] ?? 0;
    $is_creating = false;

} else {
    $tov_us = null;
    $poserijnyj_uchet='';
    $is_creating = true;
    $page_title = 'Создать товар и услуги';
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
        $poserijnyj_uchet = isset($_POST['poserijnyj_uchet']) ? 1 : 0;
        
         if ($is_creating) {
            $tov_id = null;
        } else {
            $tov_id = $tov_us['id'];
        }
        
        if (empty($naimenovanie)) {
            $error_message = 'Название товара не может быть пустым';
        } else {
            
            
            if (TovarNameExists($mysqli, $naimenovanie, $tov_id)) {
                $error_message = 'Товар с таким названием уже существует';
            } else {
                $tovar_saved = false;
                
                if ($is_creating) {
                    
                    if (insertTovar($mysqli, $naimenovanie, $poserijnyj_uchet)) {
                        $tovar_saved = true;
                    } else {
                        $error_message = 'Ошибка при добавлении склада';
                    }
                } else {
                    
                    if (updateTovar($mysqli, $tov_id, $naimenovanie, $poserijnyj_uchet)) {
                        $tovar_saved = true;
                    } else {
                        $error_message = 'Ошибка при сохранении данных';
                    }
                }
                
                if ($tovar_saved) {
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
                <h3 class="card-title mb-0"><?= $is_creating ? 'Создать товар' : 'Редактировать товар' ?></h3>
            </div>

            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="max-width: 500px;">
                    <div class="mb-3">
                        <label class="form-label" for="naimenovanie">Название товара</label>
                        <input class="form-control" type="text" id="naimenovanie" name="naimenovanie" value="<?= htmlspecialchars($tov_us['naimenovanie'] ?? '') ?>" placeholder="Введите название товара..." autocomplete="off">
                    </div>

                      <div class="row" style="margin-top: 20px;">
                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="poserijnyj_uchet" value="1" <?= (isset($_POST['poserijnyj_uchet']) || (!$is_creating && $poserijnyj_uchet)) ? 'checked' : '' ?>>
                            <span class="form-check-label">Посерийный учет</span>
                        </label>
                    </div>
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