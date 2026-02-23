<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../config/database_config.php';
require_once '../queries/id_index_helper.php';
require '../queries/database_queries.php';
require '../queries/schet_na_oplatu_query.php';
require '../queries/platezhi_queries.php';


$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$is_from_schet = isset($_GET['schet_id']) && !empty($_GET['schet_id']);

$id = $is_edit ? intval($_GET['id']) : null;
$schet_id = $is_from_schet ? intval($_GET['schet_id']) : null;

$page_title = $is_edit ? 'Редактировать платеж' : 'Новый платеж';
$data_vypuska = date('Y-m-d\TH:i');
$naimenovanie_postavschika = '';
$id_postavschika = '';
$naimenovanie_organizacii = '';
$id_organizacii = '';
$document = null;
$line_items = [];
$schet_type_vhodyashchij = false;
$schet_type_iskhodyashchij = false;


if ($is_edit) {

    $payment_doc = fetchPaymentForDisplay($mysqli, $id);
    
    if ($payment_doc) {
        
        $vendor_info = fetchContractorInfo($mysqli, $payment_doc['id_kontragenti_platelshik']);
        if ($vendor_info) {
            $id_postavschika = $vendor_info['id'];
            $naimenovanie_postavschika = $vendor_info['naimenovanie'];
        }
        
        
        $org_info = fetchContractorInfo($mysqli, $payment_doc['id_kontragenti_poluchatel']);
        if ($org_info) {
            $id_organizacii = $org_info['id'];
            $naimenovanie_organizacii = $org_info['naimenovanie'];
        }
        
        
        $line_items = fetchPaymentLineItemsForDisplay($mysqli, $payment_doc['nomer']);
        
        $data_vypuska = date('Y-m-d\TH:i');
        $schet_type_vhodyashchij = (bool)$payment_doc['vhodyashchij'];
        $schet_type_iskhodyashchij = (bool)$payment_doc['iskhodyashchij'];
    }
} elseif ($is_from_schet) {

    $schet_data = fetchSchetDataForPayment($mysqli, $schet_id);
    
    if ($schet_data) {
        $id_postavschika = $schet_data['id_postavschika'];
        $naimenovanie_postavschika = $schet_data['naimenovanie_postavschika'];
        $id_organizacii = $schet_data['id_organizacii'];
        $naimenovanie_organizacii = $schet_data['naimenovanie_organizacii'];
        $data_vypuska = date('Y-m-d');
        
        
        $line_items = fetchSchetLineItemsForPayment($mysqli, $schet_data['id_index']);
        
        
        $schet_type_vhodyashchij = !empty($schet_data['pokupatelya']);
        $schet_type_iskhodyashchij = !empty($schet_data['ot_postavshchika']);
    } else {
        die("Счет-фактура не найдена.");
    }
}

$stavki_nds = [];
$nds_query = "SELECT id, stavka_nds FROM stavki_nds ORDER BY stavka_nds ASC";
$nds_result = $mysqli->query($nds_query);
if ($nds_result) {
    $stavki_nds = $nds_result->fetch_all(MYSQLI_ASSOC);
}

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (empty($schet_id) && !$is_edit) {
        $error = 'Требуется указать счет-фактуру';
    }
    
    if (!$error) {
        $user_role = getUserRole($mysqli, $_SESSION['user_id']);
        
        if (!$user_role) {
            $error = "Доступ запрещен. Вам нужны права администратора для доступа к этой странице.";
        } else {
            if ($is_edit) {
                $result = updatePaymentDocument($mysqli, $id, $_POST + ['schet_id' => $schet_id ?? null]);
                
                if ($result['success']) {
                    $redirect_type = (!empty($_POST['iskhodyashchij']) && $_POST['iskhodyashchij'] == '1') ? 'iskhodyashchij' : 'vhodyashchij';
                    header("Location: spisok.php?type=" . $redirect_type);
                    exit;
                } else {
                    $error = $result['error'];
                }
            } else {
                $result = createPaymentDocument($mysqli, $_POST + ['schet_id' => $schet_id]);
                
                if ($result['success']) {
                    $redirect_type = (!empty($_POST['iskhodyashchij']) && $_POST['iskhodyashchij'] == '1') ? 'iskhodyashchij' : 'vhodyashchij';
                    header("Location: spisok.php?type=" . $redirect_type);
                    exit;
                } else {
                    $error = $result['error'];
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
                <input type="hidden" name="schet_id" value="<?= htmlspecialchars($schet_id ?? '') ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="schet_date">Дата и время платежа</label>
                        <input class="form-control" type="datetime-local" id="schet_date" name="schet_date"
                        value="<?= htmlspecialchars($_POST['schet_date'] ?? ($data_vypuska ?: date('Y-m-d\TH:i'))) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="id_postavschika">Плательщик (Покупатель)</label>
                        <input class="form-control" type="text" id="id_postavschika" name="naimenovanie_postavschika" 
                        value="<?= htmlspecialchars($naimenovanie_postavschika) ?>">
                        <input type="hidden" name="id_postavschika" value="<?= htmlspecialchars($id_postavschika) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="id_organizacii">Получатель (Поставщик)</label>
                        <input class="form-control" type="text" id="id_organizacii" name="naimenovanie_organizacii" 
                        value="<?= htmlspecialchars($naimenovanie_organizacii) ?>">
                        <input type="hidden" name="id_organizacii" value="<?= htmlspecialchars($id_organizacii) ?>">
                    </div>
                </div>

                <input type="hidden" name="vhodyashchij" value="<?= $schet_type_vhodyashchij ? '1' : '0' ?>">
                <input type="hidden" name="iskhodyashchij" value="<?= $schet_type_iskhodyashchij ? '1' : '0' ?>">

                <h3 style="margin-top: 30px; margin-bottom: 15px;"></h3>
                
                <div class="card">
                <div class="table-responsive">
                <table class="table table-vcenter card-table" id="tablitsaTovarov">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Товар</th>
                            <th>Кол-во</th>
                            <th>Цена</th>
                            <th>НДС</th>
                            <th>Сумма НДС</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="productsBody">
                        <?php if (!empty($line_items)): ?>
                            <?php $row_index = 0; ?>
                            <?php foreach ($line_items as $item): ?>
                        <tr class="tovar-row">
                            <td><?= $row_index + 1 ?></td>
                            <td>
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($_POST['tovary'][$row_index]['naimenovanie_tovara'] ?? ($item['naimenovanie_tovara'] ?? '')) ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_tovara]" class="id_tovara" value="<?= htmlspecialchars($item['id_tovara'] ?? '') ?>">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['kolichestvo'] ?? ($item['kolichestvo'] ?? '0')) ?>"></td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][cena]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['cena'] ?? ($item['ed_cena'] ?? '0')) ?>"></td>
                            <td>
                                <select class="form-control" name="tovary[<?= $row_index ?>][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>" <?= ($nds['id'] == ($_POST['tovary'][$row_index]['nds_id'] ?? ($item['nds_id'] ?? ''))) ? 'selected' : '' ?>><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa_stavka]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['summa_stavka'] ?? ($item['summa_nds'] ?? '0')) ?>"></td>
                            <td><input class="form-control" type="text" name="tovary[<?= $row_index ?>][summa]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($_POST['tovary'][$row_index]['summa'] ?? ($item['summa'] ?? '0')) ?>"></td>
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="tovar-row">
                            <td>1</td>
                            <td>
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off">
                                    <input type="hidden" name="tovary[0][id_tovara]" class="id_tovara">
                                </div>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[0][kolichestvo]" placeholder="0" autocomplete="off"></td>
                            <td><input class="form-control" type="text" name="tovary[0][cena]" placeholder="0" autocomplete="off"></td>
                            <td>
                                <select class="form-control" name="tovary[0][nds_id]">
                                    <option value="">--</option>
                                    <?php foreach ($stavki_nds as $nds): ?>
                                        <option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" type="text" name="tovary[0][summa_stavka]" placeholder="0" autocomplete="off"></td>
                            <td><input class="form-control" type="text" name="tovary[0][summa]" placeholder="0" autocomplete="off"></td>
                            <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                </div>

                <button type="button" class="btn mt-3 btn-primary" onclick="addRow()">Добавить строку</button>

                <div class="row" style="margin-top: 40px;">
                    <div class="col-12">
                         <div class="btn-gruppa" role="gruppa" aria-label="Basic example">
                        <button type="submit" class="btn btn-primary">
                            <?= $is_edit ? 'Сохранить' : 'Сохранить' ?>
                        </button>
                        <a href="spisok.php" class="btn">Отмена</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
        <script src="https://cdn.jsdelivm.net/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
        
        <script>
            let ndsOptionsTemplate = '<option value="">--</option>';
            <?php foreach ($stavki_nds as $nds): ?>
                ndsOptionsTemplate += '<option value="<?= $nds['id'] ?>"><?= htmlspecialchars($nds['stavka_nds']) ?></option>';
            <?php endforeach; ?>
            
            function addRow() {
                const table = document.getElementById('productsBody');
                const rowCount = table.querySelectorAll('.tovar-row').length;
                const rowIndex = rowCount;
                
                const newRow = document.createElement('tr');
                newRow.className = 'tovar-row';
                newRow.innerHTML = `
                    <td>${rowIndex + 1}</td>
                    <td>
                        <div class="search-container" style="position: relative;">
                            <input class="form-control" type="text" name="tovary[${rowIndex}][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off">
                            <input type="hidden" name="tovary[${rowIndex}][id_tovara]" class="id_tovara">
                        </div>
                    </td>
                    <td><input class="form-control" type="text" name="tovary[${rowIndex}][kolichestvo]" placeholder="0" autocomplete="off"></td>
                    <td><input class="form-control" type="text" name="tovary[${rowIndex}][cena]" placeholder="0" autocomplete="off"></td>
                    <td>
                        <select class="form-control" name="tovary[${rowIndex}][nds_id]">
                            ${ndsOptionsTemplate}
                        </select>
                    </td>
                    <td><input class="form-control" type="text" name="tovary[${rowIndex}][summa_stavka]" placeholder="0" autocomplete="off"></td>
                    <td><input class="form-control" type="text" name="tovary[${rowIndex}][summa]" placeholder="0" autocomplete="off"></td>
                    <td><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                `;
                
                table.appendChild(newRow);
                updateRowNumbers();
            }
            
            function deleteRow(icon) {
                icon.closest('tr').remove();
                updateRowNumbers();
            }
            
            function updateRowNumbers() {
                const rows = document.querySelectorAll('#productsBody .tovar-row');
                rows.forEach((row, index) => {
                    row.cells[0].textContent = index + 1;
                });
            }
        </script>
</div>
<?php include '../footer.php'; ?>