<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

if (!isset($_GET['uuid']) || empty($_GET['uuid'])) {
    die("UUID документа не предоставлен.");
}

$uuid = $_GET['uuid'];
$mysqli = require 'config/database.php';
require 'config/database_config.php';
require 'queries/database_queries.php';
require 'queries/add_product_queries.php';
require_once(__DIR__ . '/api/vetis_service.php');

$data = fetchVetisDocument($uuid);

if (!$data['success']) {
    die('Ошибка: ' . htmlspecialchars($data['error']));
}
extract($data);

$page_title = 'Редактировать поступление товара из ветис';

$nds_rates = [];
$nds_query = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds ASC";
$nds_result = $mysqli->query($nds_query);
if ($nds_result) {
    $nds_rates = $nds_result->fetch_all(MYSQLI_ASSOC);
}

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Массив валидации
    $validations = array(
        'product_date' => 'Требуется дата документа',
        'warehouse_name' => 'Требуется выбрать склад',
        'organization_name' => 'Требуется выбрать организацию',
        'vendor_name' => 'Требуется выбрать поставщика',
        'responsible_name' => 'Требуется выбрать ответственного'
    );
    
    // Проверить обязательные поля
    foreach ($validations as $field => $errorMsg) {
        if (empty($_POST[$field])) {
            $error = $errorMsg;
            break;
        }
    }
    
    // Проверить товары
    if (!$error && (!isset($_POST['products']) || empty($_POST['products']))) {
        $error = 'Требуется добавить хотя бы один товар';
    }
    
    if (!$error) {
        
        $user_role = getUserRole($mysqli, $_SESSION['user_id']);
        
        if (!$user_role) {
            $error = "Доступ запрещен. Вам нужны права администратора для доступа к этой странице.";
        } else {
            // Create arrival document with VETIS data
            $result = createArrivalDocument($mysqli, $_POST);
            
            if ($result['success']) {
                // Redirect to admin page
                header("Location: admin_page.php");
                exit;
            } else {
                $error = $result['error'];
            }
        }
    }
}

include 'header.php';
?>
<link rel="stylesheet" href="css/add_product.css">

<?php if ($error): ?>
    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>" role="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="page-body">
<div class="container-xl mt-5">
    <h2 class="card-title" style="font-size: 2rem; margin-top: 20px; margin-bottom: 30px;">Новое поступление товара</h2>
    <div class="card">
        <div class="card-body">
            <form method="POST" id="documentForm">   
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="product_date">Дата поступления документа</label>
                        <input class="form-control" type="date" id="product_date" name="product_date" required
                         value="<?= htmlspecialchars($date_issued) ?>">
                    </div>

                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="warehouse_id">Склад</label>
                        <input type="text" class="form-control" id="warehouse_id" name="warehouse_name" placeholder="- Выберите склад -" autocomplete="off" required
                        value="<?= htmlspecialchars($_POST['warehouse_name'] ?? ($document['warehouse_name'] ?? '')) ?>">
                        <input type="hidden" name="warehouse_id" class="warehouse-id" value="<?= htmlspecialchars($_POST['warehouse_id'] ?? ($document['warehouse_id'] ?? '')) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="vendor_id">Поставщик</label>
                        <input class="form-control" type="text" id="vendor_id" name="vendor_name" placeholder="- Выберите поставщика -" autocomplete="off" required value="<?= htmlspecialchars($shipper_name ?? '') ?>">
                        <input type="hidden" name="vendor_id" class="vendor-id">
                    </div>

                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="organization_id">Организация</label>
                        <input class="form-control" type="text" id="organization_id" name="organization_name" placeholder="- Выберите организацию -" autocomplete="off" required
                        value="<?= htmlspecialchars($_POST['organization_name'] ?? ($document['organization_name'] ?? '')) ?>">
                        <input type="hidden" name="organization_id" class="organization-id" value="<?= htmlspecialchars($_POST['organization_id'] ?? ($document['organization_id'] ?? '')) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="responsible_id">Ответственный</label>
                        <input type="text" class="form-control" id="responsible_id" name="responsible_name" placeholder="- Выберите ответственного -" autocomplete="off" required
                        value="<?= htmlspecialchars($_POST['responsible_name'] ?? ($document['responsible_name'] ?? '')) ?>">
                        <input type="hidden" name="responsible_id" class="responsible-id" value="<?= htmlspecialchars($_POST['responsible_id'] ?? ($document['responsible_id'] ?? '')) ?>">
                    </div>
                </div>


                <h2 style="margin-top: 30px;"></h2>
                <div class="card">
                <div class="table-responsive">
                <table class="table table-vcenter card-table" id="productsTable">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>ТОВАР</th>
                            <th>СЕРИЯ</th>
                            <th>ЦЕНА</th>
                            <th>КОЛ-ВО</th>
                            <th>ЕД</th>
                            <th>НДС</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="productsBody">
                        <tr class="product-row">
                            <td>1</td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="products[0][product_name]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($product_name) ?>">
                                    <input type="hidden" name="products[0][product_id]" class="product-id">
                                </div>
                            </td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="products[0][seria_name]" placeholder="Введите серию..." autocomplete="off"
                                    value="<?=htmlspecialchars(substr($product_guid, 0, 36))?>">
                                    <input type="hidden" name="products[0][seria_id]" class="seria-id">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="products[0][price]" placeholder="0" autocomplete="off"></td>
                            <td><input class="form-control" type="text" name="products[0][quantity]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($volume) ?>"></td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="products[0][unit_name]" placeholder="Введите ед." autocomplete="off" required value="<?= htmlspecialchars($unit_name) ?>">
                                    <input type="hidden" name="products[0][unit_id]" class="unit-id">
                                </div>
                            </td>
                            <td>
                                <select class="form-control" name="products[0][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($nds_rates as $nds): ?>
                                        <option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                    </tbody>
                </table>
                </div>
                </div>

                <button type="button" class="btn" onclick="addRow()">+ строка</button>
                <div class="row" style="margin-top: 20px;">
                     <div class="row"> 
                <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="data_izgotovleniya">Дата изготовления</label>
                        <input class="form-control" type="date" id="data_izgotovleniya" name="data_izgotovleniya" autocomplete="off" required value="<?= htmlspecialchars($prod_date) ?>">
                    </div>

                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="srok_godnosti">Срок годности</label>
                        <input class="form-control" type="date" id="srok_godnosti" name="srok_godnosti" autocomplete="off" required value="<?= htmlspecialchars($exp_date) ?>">
                    </div>    
                </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="admin_page.php" class="btn">Отмена</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
        <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
        
        <script>
            let ndsOptionsTemplate = '<option value="">--</option>';
            <?php foreach ($nds_rates as $nds): ?>
                ndsOptionsTemplate += '<option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>';
            <?php endforeach; ?>
        </script>
        <script src="js/add_product.js"></script>
</div>
<?php include 'footer.php'; ?>