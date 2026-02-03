<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit();
}

$page_title = 'Редактировать серию товара';

$mysqli = require '../config/database.php';
require '../queries/redaktirovat_dannyye_serii_queries.php';


$seria_id = isset($_GET['seria_id']) ? intval($_GET['seria_id']) : null;
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
$default_prod_date = isset($_GET['prod_date']) ? $_GET['prod_date'] : '';
$default_exp_date = isset($_GET['exp_date']) ? $_GET['exp_date'] : '';

// If seria_id is provided, fetch the series data
if ($seria_id) {
    $seria = getSeriaById($mysqli, $seria_id);
    
    if (!$seria) {
        die('Серия не найдена');
    }
    
    $product_id = $seria['id_tovary_i_uslugi'];
    $is_creating = false;
} else if ($product_id) {
    
    $seria = null;
    $is_creating = true;
    $page_title = 'Создать серию';
} else {
    die('Товар не найден');
}


$product = getProductById($mysqli, $product_id);

if (!$product) {
    die('Товар не найден');
}

$error_message = '';
$success_message = '';


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    if (isset($_POST['nomer']) && isset($_POST['data_izgotovleniya']) && isset($_POST['srok_godnosti'])) {
        $nomer = trim($_POST['nomer']);
        $data_izgotovleniya = !empty($_POST['data_izgotovleniya']) ? $_POST['data_izgotovleniya'] : null;
        $srok_godnosti = !empty($_POST['srok_godnosti']) ? $_POST['srok_godnosti'] : null;
        $guid_tovara = !empty($_POST['guid_tovara']) ? trim($_POST['guid_tovara']) : null;
        
        if (empty($nomer)) {
            $error_message = 'Номер серии не может быть пустым';
        } else {
            
            $existing_seria = seriesNumberExists($mysqli, $nomer);
            $series_saved = false;
            
            if ($existing_seria) {
                
                $product_seria = productSeriesExists($mysqli, $nomer, $product_id);
                
                if ($product_seria) {
                
                    if (updateSeriesDates($mysqli, $data_izgotovleniya, $srok_godnosti, $product_seria['id'])) {
                        $series_saved = true;
                    } else {
                        $error_message = 'Ошибка при сохранении данных';
                    }
                } else {
                    
                    if (insertSeries($mysqli, $product_id, $nomer, $data_izgotovleniya, $srok_godnosti)) {
                        $series_saved = true;
                    } else {
                        $error_message = 'Ошибка при добавлении серии';
                    }
                }
            } else {
                
                if ($seria) {
                    
                    if (updateSeries($mysqli, $nomer, $data_izgotovleniya, $srok_godnosti, $seria['id'])) {
                        $series_saved = true;
                    } else {
                        $error_message = 'Ошибка при сохранении данных';
                    }
                } else {
                    
                    if (insertSeries($mysqli, $product_id, $nomer, $data_izgotovleniya, $srok_godnosti)) {
                        $series_saved = true;
                    } else {
                        $error_message = 'Ошибка при добавлении серии';
                    }
                }
            }
            
            
            if ($series_saved) {
                if ($guid_tovara) {
                    saveProductGUID($mysqli, $product_id, $guid_tovara);
                }
                $_SESSION['success_message'] = 'Данные успешно сохранены';
                header('Location: serii.php?product_id=' . $product_id);
                exit();
            }
        }
    } else {
        $error_message = 'Отсутствуют обязательные поля';
    }
}


if (isset($_POST['cancel'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

include '../header.php';
?>

<div class="page-body">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><?= $is_creating ? 'Создать серию' : 'Редактировать серию' ?>: <?= htmlspecialchars($product['naimenovanie']) ?></h3>
            </div>

            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="max-width: 500px;">
                    <div class="mb-3" style="position: relative;">
                        <label class="form-label" for="nomer">Номер серии</label>
                        <input class="form-control" type="text" id="nomer" name="nomer" value="<?= htmlspecialchars($seria['nomer'] ?? '') ?>" placeholder="Введите серию..." autocomplete="off" required>
                        <div id="seria-dropdown" class="autocomplete-dropdown" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="data_izgotovleniya">Дата выпуска</label>
                        <input class="form-control" type="date" id="data_izgotovleniya" name="data_izgotovleniya" value="<?= htmlspecialchars($seria['data_izgotovleniya'] ?? $default_prod_date) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="srok_godnosti">Срок годности</label>
                        <input class="form-control" type="date" id="srok_godnosti" name="srok_godnosti" value="<?= htmlspecialchars($seria['srok_godnosti'] ?? $default_exp_date) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="guid_tovara">GUID товара</label>
                        <input class="form-control" type="text" id="guid_tovara" name="guid_tovara" value="<?= htmlspecialchars($product['vetis_guid'] ?? '') ?>"placeholder="Введите GUID..." autocomplete="off">
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary"><?= $seria ? 'Сохранить' : 'Сохранить' ?></button>
                        <a href="javascript:history.back()" class="btn">Отменить</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script src="../js/redaktirovat_dannyye_serii.js"></script>
