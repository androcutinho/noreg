<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../config/database_config.php';
require '../queries/database_queries.php';
require '../queries/zosdat_edit_specifikatsiu.php';

// Check if editing or creating
$spec_id = $_GET['id'] ?? null;
$is_edit = !empty($spec_id);

// Handle delete action
if ($is_edit && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $delete_result = deleteSpecification($mysqli, $spec_id);
    if ($delete_result['success']) {
        header('Location: index.php');
        exit;
    } else {
        $error = $delete_result['error'];
    }
}

if ($is_edit) {
    $page_title = 'Редактирование спецификации к договору';
} else {
    $page_title = 'Новая спецификация к договору';
}

// Initialize variables for creation
$data_dogovora = date('Y-m-d');
$nomer_specifikacii = '';
$gorod = '';
$organization_name = '';
$organization_id = '';
$kontragenti_name = '';
$kontragenti_id = '';
$planiruemaya_data_postavki = date('Y-m-d');
$usloviya_otgruzki = '';
$usloviya_oplaty = '';
$inye_usloviya = '';
$sotrudniki_name = '';
$sotrudniki_id = '';
$podpisant_postavshchika_dolzhnost = '';
$podpisant_postavshchika_fio = '';
$line_items = [];

// Load existing data if editing
if ($is_edit) {
    $spec_result = getSpecificationById($mysqli, $spec_id);
    if (!$spec_result['success']) {
        header('Location: index.php');
        exit;
    }
    
    $spec = $spec_result['data'];
    $data_dogovora = $spec['data_dogovora'];
    $nomer_specifikacii = $spec['nomer_specifikacii'];
    $gorod = $spec['gorod'];
    $nomer_dogovora = $spec['nomer_dogovora'];
    $organization_id = $spec['id_organizacii'];
    $kontragenti_id = $spec['id_kontragenti'];
    $usloviya_otgruzki = $spec['usloviya_otgruzki'];
    $usloviya_oplaty = $spec['usloviya_oplaty'];
    $inye_usloviya = $spec['inye_usloviya'];
    $sotrudniki_id = $spec['id_sotrudniki'];
    $podpisant_postavshchika_dolzhnost = $spec['podpisant_postavshchika_dolzhnost'];
    $podpisant_postavshchika_fio = $spec['podpisant_postavshchika_fio'];
    
    $org_query = $mysqli->query("SELECT naimenovanie FROM organizacii WHERE id = " . intval($organization_id));
    if ($org = $org_query->fetch_assoc()) {
        $organization_name = $org['naimenovanie'];
    }
    
    $vendor_query = $mysqli->query("SELECT naimenovanie FROM kontragenti WHERE id = " . intval($kontragenti_id));
    if ($vendor = $vendor_query->fetch_assoc()) {
        $kontragenti_name = $vendor['naimenovanie'];
    }
    
    $sotrudnik_query = $mysqli->query("SELECT CONCAT(COALESCE(familiya, ''), ' ', COALESCE(imya, ''), ' ', COALESCE(otchestvo, '')) as fio FROM sotrudniki WHERE id = " . intval($sotrudniki_id));
    if ($sotrudnik = $sotrudnik_query->fetch_assoc()) {
        $sotrudniki_name = trim($sotrudnik['fio']);
    }
    
    
    $line_items_result = getSpecificationLineItems($mysqli, $spec_id);
    if ($line_items_result['success']) {
        $line_items = $line_items_result['data'];
    }
}

$nds_rates = getAllNdsRates($mysqli);
$units = getAllUnits($mysqli);

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validation
    $validations = [
        'data_dogovora' => 'Требуется дата договора',
        'nomer_specifikacii' => 'Требуется номер спецификации',
        'gorod' => 'Требуется указать город',
        'nomer_dogovora' => 'Требуется указать номер договора',
        'organization_id' => 'Требуется выбрать организацию',
        'kontragenti_id' => 'Требуется выбрать поставщика',
        'sotrudniki_id' => 'Требуется выбрать ответственного',
    ];
    
    foreach ($validations as $field => $errorMsg) {
        if (empty($_POST[$field])) {
            $error = $errorMsg;
            break;
        }
    }
    
    if (!$error && (!isset($_POST['products']) || empty($_POST['products']))) {
        $error = 'Требуется добавить хотя бы один товар';
    }
    
    // Validate that each product has a delivery date
    if (!$error && isset($_POST['products'])) {
        foreach ($_POST['products'] as $index => $product) {
            if (empty($product['planiruemaya_data_postavki'])) {
                $error = 'Требуется указать плановую дату поставки для всех товаров';
                break;
            }
        }
    }
    
    if (!$error) {
        if ($is_edit) {
            
            $result = updateSpecification($mysqli, $spec_id, $_POST);
            
            if (!$result['success']) {
                $error = $result['error'];
            } else {
                
                $line_result = updateSpecificationLineItems($mysqli, $spec_id, $_POST['products']);
                
                if (!$line_result['success']) {
                    $error = $line_result['error'];
                } else {
                    
                    header('Location: index.php');
                    exit;
                }
            }
        } else {
            
            $result = createSpecification($mysqli, $_POST);
            
            if (!$result['success']) {
                $error = $result['error'];
            } else {
                $doc_id = $result['id'];
                
                
                $line_result = createSpecificationLineItems($mysqli, $doc_id, $_POST['products']);
                
                if (!$line_result['success']) {
                    $error = $line_result['error'];
                } else {
                    
                    header('Location: index.php');
                    exit;
                }
            }
        }
    }
}

include '../header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="page-body">
<div class="container-fluid mt-5">
    <h2 class="card-title" style="font-size: 2rem; margin-top: 20px; margin-bottom: 30px;">
        <?= htmlspecialchars($page_title) ?>
    </h2>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="documentForm">   
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="data_dogovora">Дата договора</label>
                        <input class="form-control" type="date" id="data_dogovora" name="data_dogovora"
                        value="<?= htmlspecialchars($_POST['data_dogovora'] ?? $data_dogovora) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="nomer_specifikacii">№ спецификации</label>
                        <input class="form-control" type="text" id="nomer_specifikacii" name="nomer_specifikacii" placeholder="Введите номер спецификации..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['nomer_specifikacii'] ?? $nomer_specifikacii) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="gorod">Город</label>
                        <input class="form-control" type="text" id="gorod" name="gorod" placeholder="Введите город..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['gorod'] ?? $gorod) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="nomer_dogovora">№ договора</label>
                        <input class="form-control" type="text" id="nomer_dogovora" name="nomer_dogovora" placeholder="Введите номер договора..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['nomer_dogovora'] ?? $nomer_dogovora) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="organization_id">Организация</label>
                        <input class="form-control" type="text" id="organization_id" name="organization_name" placeholder="- Выберите организацию -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['organization_name'] ?? $organization_name) ?>">
                        <input type="hidden" name="organization_id" class="organization-id" value="<?= htmlspecialchars($_POST['organization_id'] ?? $organization_id) ?>">
                    </div>

                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="kontragenti_id">Поставщик</label>
                        <input class="form-control" type="text" id="kontragenti_id" name="kontragenti_name" placeholder="- Выберите поставщика -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['kontragenti_name'] ?? $kontragenti_name) ?>">
                        <input type="hidden" name="kontragenti_id" class="kontragenti-id" value="<?= htmlspecialchars($_POST['kontragenti_id'] ?? $kontragenti_id) ?>">
                    </div>
                </div>

                <h2 style="margin-top: 30px;"></h2>
                
                <div class="card">
                <div class="table-responsive">
                <table class="table table-vcenter card-table" id="productsTable">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Товар</th>
                            <th>Ед</th>
                            <th>Кол-во</th>
                            <th>Цена</th>
                            <th>НДС</th>
                            <th>Сумма НДС</th>
                            <th>Сумма</th>
                            <th>Планируемая дата поставки</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="productsBody">
                        <?php if ($is_edit && !empty($line_items)): ?>
                            <?php foreach ($line_items as $index => $item): ?>
                                <tr class="product-row">
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <div class="search-container" style="position: relative;">
                                            <input class="form-control" type="text" name="products[<?= $index ?>][product_name]" placeholder="Введите товар..." autocomplete="off" value="<?= htmlspecialchars($item['product_name']) ?>">
                                            <input type="hidden" name="products[<?= $index ?>][product_id]" class="product-id" value="<?= $item['id_tovary_i_uslugi'] ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="search-container" style="position: relative;">
                                            <input class="form-control" type="text" name="products[<?= $index ?>][unit_name]" placeholder="Введите ед." autocomplete="off" value="<?= htmlspecialchars($item['unit_name']) ?>">
                                            <input type="hidden" name="products[<?= $index ?>][unit_id]" class="unit-id" value="<?= $item['id_edinicy_izmereniya'] ?>">
                                        </div>
                                    </td>
                                    <td><input class="form-control" type="text" name="products[<?= $index ?>][quantity]" placeholder="0" autocomplete="off" value="<?= $item['kolichestvo'] ?>"></td>
                                    <td><input class="form-control" type="text" name="products[<?= $index ?>][price]" placeholder="0" autocomplete="off" value="<?= $item['cena'] ?>"></td>
                                    <td>
                                        <select class="form-control" name="products[<?= $index ?>][nds_id]">
                                            <option value="">--</option>
                                            <?php foreach ($nds_rates as $nds): ?>
                                                <option value="<?= $nds['id'] ?>" <?= $item['id_stavka_nds'] == $nds['id'] ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input class="form-control" type="text" name="products[<?= $index ?>][summa_stavka]" placeholder="0" autocomplete="off" value="<?= $item['summa_nds'] ?>"></td>
                                    <td><input class="form-control" type="text" name="products[<?= $index ?>][summa]" placeholder="0" autocomplete="off" value="<?= $item['summa'] ?>"></td>
                                    <td><input class="form-control" type="date" name="products[<?= $index ?>][planiruemaya_data_postavki]" value="<?= $item['planiruemaya_data_postavki'] ?>" required></td>
                                    <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="product-row">
                                <td>1</td>
                                <td>
                                    <div class="search-container" style="position: relative;">
                                        <input class="form-control" type="text" name="products[0][product_name]" placeholder="Введите товар..." autocomplete="off">
                                        <input type="hidden" name="products[0][product_id]" class="product-id">
                                    </div>
                                </td>
                                <td>
                                    <div class="search-container" style="position: relative;">
                                        <input class="form-control" type="text" name="products[0][unit_name]" placeholder="Введите ед." autocomplete="off">
                                        <input type="hidden" name="products[0][unit_id]" class="unit-id">
                                    </div>
                                </td>
                                <td><input class="form-control" type="text" name="products[0][quantity]" placeholder="0" autocomplete="off"></td>
                                <td><input class="form-control" type="text" name="products[0][price]" placeholder="0" autocomplete="off"></td>
                                <td>
                                    <select class="form-control" name="products[0][nds_id]">
                                        <option value="">--</option>
                                        <?php foreach ($nds_rates as $nds): ?>
                                            <option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input class="form-control" type="text" name="products[0][summa_stavka]" placeholder="0" autocomplete="off"></td>
                                <td><input class="form-control" type="text" name="products[0][summa]" placeholder="0" autocomplete="off"></td>
                                <td><input class="form-control" type="date" name="products[0][planiruemaya_data_postavki]" required></td>
                                <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                                </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                </div>

                <button type="button" class="btn mt-3 btn-primary" onclick="addRow()">Добавить строку</button>
                
                <h2 style="margin-top: 30px;"></h2>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="usloviya_otgruzki">Условия отгрузки</label>
                        <textarea class="form-control" id="usloviya_otgruzki" name="usloviya_otgruzki" rows="3" placeholder="Введите условия отгрузки..."><?= htmlspecialchars($_POST['usloviya_otgruzki'] ?? $usloviya_otgruzki) ?></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="usloviya_oplaty">Условия оплаты</label>
                        <textarea class="form-control" id="usloviya_oplaty" name="usloviya_oplaty" rows="3" placeholder="Введите условия оплаты..."><?= htmlspecialchars($_POST['usloviya_oplaty'] ?? $usloviya_oplaty) ?></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="inye_usloviya">Иные условия</label>
                        <textarea class="form-control" id="inye_usloviya" name="inye_usloviya" rows="3" placeholder="Введите иные условия..."><?= htmlspecialchars($_POST['inye_usloviya'] ?? $inye_usloviya) ?></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="sotrudniki_id">Ответственный</label>
                        <input class="form-control" type="text" id="sotrudniki_id" name="sotrudniki_name" placeholder="- Выберите ответственного -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['sotrudniki_name'] ?? $sotrudniki_name) ?>">
                        <input type="hidden" name="sotrudniki_id" class="sotrudniki-id" value="<?= htmlspecialchars($_POST['sotrudniki_id'] ?? $sotrudniki_id) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="podpisant_postavshchika_dolzhnost">Подписант поставщика должность</label>
                        <input class="form-control" type="text" id="podpisant_postavshchika_dolzhnost" name="podpisant_postavshchika_dolzhnost" placeholder="Введите должность..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['podpisant_postavshchika_dolzhnost'] ?? $podpisant_postavshchika_dolzhnost) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="podpisant_postavshchika_fio">Подписант поставщика ФИО</label>
                        <input class="form-control" type="text" id="podpisant_postavshchika_fio" name="podpisant_postavshchika_fio" placeholder="Введите ФИО..." autocomplete="off"
                        value="<?= htmlspecialchars($_POST['podpisant_postavshchika_fio'] ?? $podpisant_postavshchika_fio) ?>">
                    </div>
                </div>
                <div class="row" style="margin-top: 20px;">
                    <div class="col-12">
                         <div class="btn-group" role="group" aria-label="Basic example">
                        <button type="submit" class="btn btn-primary">
                            Сохранить
                        </button>
                        <a href="index.php" class="btn">Отмена</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

        
        <script>
            let ndsOptionsTemplate = '<option value="">--</option><?php foreach ($nds_rates as $nds): ?><option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option><?php endforeach; ?>';
        </script>
        
        <script>
            let unitsData = <?php echo json_encode($units); ?>;
        </script>
        
        <script>
            let isEditMode = <?php echo $is_edit ? 'true' : 'false'; ?>;
        </script>
        
        <script src="/js/add_product.js"></script>
        <script src="/js/spec_autocomplete.js"></script>
<?php include '../footer.php'; ?>
