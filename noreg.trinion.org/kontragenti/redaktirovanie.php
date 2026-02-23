<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit();
}

$page_title = 'Редактировать контрагента';

$mysqli = require '../config/database.php';
require '../queries/spisok_postavshchikov_queries.php';

$postavshchik_id = isset($_GET['postavshchik_id']) ? intval($_GET['postavshchik_id']) : null;


if ($postavshchik_id) {
    $postavshchik = getPostavshchikById($mysqli, $postavshchik_id);
    
    if (!$postavshchik) {
        die('Поставщик не найден');
    }
    
    $is_creating = false;
} else {
    $postavshchik = null;
    $is_creating = true;
    $page_title = 'Создать контрагента';
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
            $error_message = 'Название контрагента не может быть пустым';
        } else {
            
            
            if (postavshchikNameExists($mysqli, $naimenovanie, $postavshchik_id)) {
                $error_message = 'Контрагент с таким названием уже существует';
            } else {
                $kontragenti_type = $_POST['radios-inline'] ?? 'postavshchik';
                $data = [
                    'naimenovanie' => $naimenovanie,
                    'INN' => trim($_POST['INN'] ?? ''),
                    'KPP' => trim($_POST['KPP'] ?? ''),
                    'yuridicheskij_adress' => trim($_POST['yuridicheskij_adress'] ?? ''),
                    'pochtovyj_adress' => trim($_POST['pochtovyj_adress'] ?? ''),
                    'OGRN' => trim($_POST['OGRN'] ?? ''),
                    'polnoe_naimenovanie_organizacii' => trim($_POST['polnoe_naimenovanie_organizacii'] ?? ''),
                    'sokrashchyonnoe_naimenovanie' => trim($_POST['sokrashchyonnoe_naimenovanie'] ?? ''),
                    'v_lice_dlya_documentov' => trim($_POST['v_lice_dlya_documentov'] ?? ''),
                    'postavshchik' => ($kontragenti_type === 'postavshchik') ? 1 : 0,
                    'pokupatel' => ($kontragenti_type === 'pokupatel') ? 1 : 0
                ];
                
                $postavshchik_saved = false;
                
                if ($is_creating) {
                    
                    if (insertPostavshchik($mysqli, $data)) {
                        $postavshchik_saved = true;
                    } else {
                        $error_message = 'Ошибка при добавлении контрагента';
                    }
                } else {
                    
                    if (updatePostavshchik($mysqli, $postavshchik_id, $data)) {
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

include '../header.php';
?>

<div class="page-body">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><?= $is_creating ? 'Создать контрагента' : 'Редактировать контраргента' ?></h3>
            </div>

            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="naimenovanie">Название контрагента</label>
                            <input class="form-control" type="text" id="naimenovanie" name="naimenovanie" value="<?= htmlspecialchars($postavshchik['naimenovanie'] ?? '') ?>" placeholder="Введите название контрагента..." autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="INN">ИНН</label>
                            <input class="form-control" type="text" id="INN" name="INN" value="<?= htmlspecialchars($postavshchik['INN'] ?? '') ?>" placeholder="Введите ИНН" autocomplete="off">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="KPP">КПП</label>
                            <input class="form-control" type="text" id="KPP" name="KPP" value="<?= htmlspecialchars($postavshchik['KPP'] ?? '') ?>" placeholder="Введите КПП" autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="OGRN">ОГРН</label>
                            <input class="form-control" type="text" id="OGRN" name="OGRN" value="<?= htmlspecialchars($postavshchik['OGRN'] ?? '') ?>" placeholder="Введите ОГРН" autocomplete="off">
                        </div>
                    </div>
                    <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="polnoe_naimenovanie_organizacii">Полное наименование организации</label>
                        <input class="form-control" type="text" id="polnoe_naimenovanie_organizacii" name="polnoe_naimenovanie_organizacii" value="<?= htmlspecialchars($postavshchik['polnoe_naimenovanie_organizacii'] ?? '') ?>" placeholder="Введите полное наименование организации" autocomplete="off">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="sokrashchyonnoe_naimenovanie">Сокращённое наименование</label>
                        <input class="form-control" type="text" id="sokrashchyonnoe_naimenovanie" name="sokrashchyonnoe_naimenovanie" value="<?= htmlspecialchars($postavshchik['sokrashchyonnoe_naimenovanie'] ?? '') ?>" placeholder="Введите сокращённое наименование" autocomplete="off">
                    </div>
                     </div>

                     <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="yuridicheskij_adress">Юридический адрес</label>
                        <input class="form-control" type="text" id="yuridicheskij_adress" name="yuridicheskij_adress" value="<?= htmlspecialchars($postavshchik['yuridicheskij_adress'] ?? '') ?>" placeholder="Введите юридический адрес" autocomplete="off">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="pochtovyj_adress">Почтовый адрес</label>
                        <input class="form-control" type="text" id="pochtovyj_adress" name="pochtovyj_adress" value="<?= htmlspecialchars($postavshchik['pochtovyj_adress'] ?? '') ?>" placeholder="Введите почтовый адрес" autocomplete="off">
                    </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="v_lice_dlya_documentov">В лице для документов</label>
                        <input class="form-control" type="text" id="v_lice_dlya_documentov" name="v_lice_dlya_documentov" value="<?= htmlspecialchars($postavshchik['v_lice_dlya_documentov'] ?? '') ?>" placeholder="Введите лица для документов" autocomplete="off">
                    </div>

                    <div style="margin-top: 40px;">
                                <label class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="radios-inline" value="postavshchik" <?= (!isset($postavshchik) || $postavshchik['postavshchik']) ? 'checked' : '' ?>>
                                  <span class="form-check-label">Поставщик</span>
                                </label>
                                <label class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="radios-inline" value="pokupatel" <?= (isset($postavshchik) && $postavshchik['pokupatel']) ? 'checked' : '' ?>>
                                  <span class="form-check-label">Покупатель</span>
                                </label>
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
