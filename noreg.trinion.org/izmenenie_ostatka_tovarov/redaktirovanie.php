<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../queries/database_queries.php';
require '../queries/izmenenie_ostatka_tovarov_queries.php';



$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$id = $is_edit ? intval($_GET['id']) : null;


$page_title = $is_edit ? 'Редактировать изменение остатка товаров' : 'Новое изменение остатка товаров';
$data_vypuska = date('Y-m-d');
$naimenovanie_tovara = '';
$naimenovanie_sklada = '';
$id_sklada = '';
$naimenovanie_otvetstvennogo = '';
$id_otvetstvennogo = '';
$document = null;
$line_items = [];


if ($is_edit) {
    $document = getDokumentHeader($mysqli, $id);
    
    if (!$document) {
        die("Документ не найден.");
    }
    
    $line_items = getStrokiDokumentovItems($mysqli, $document['id_index']);
    $data_vypuska = $document['data_dokumenta'];
    $naimenovanie_sklada = $document['naimenovanie_sklada'] ?? '';
    $naimenovanie_otvetstvennogo = $document['naimenovanie_otvetstvennogo'] ?? '';
    $id_sklada = $document['id_sklada'] ?? '';
    $id_otvetstvennogo = $document['id_otvetstvennogo'] ?? '';
} 


$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  
    $validations = array(
        'product_date' => 'Требуется дата документа',
        'naimenovanie_sklada' => 'Требуется указать склад',
        'naimenovanie_otvetstvennogo' => 'Требуется указать ответственного'
    );
    
    foreach ($validations as $field => $errorMsg) {
        if (empty($_POST[$field])) {
            $error = $errorMsg;
            break;
        }
    }
    
    
    if ($is_edit && empty($_POST['id_otvetstvennogo']) && !empty($document['id_otvetstvennyj'])) {
        $_POST['id_otvetstvennogo'] = $document['id_otvetstvennyj'];
    }
   
    if (!$error && empty($_POST['id_otvetstvennogo'])) {
        $error = 'Пожалуйста, выберите ответственного из списка пользователей';
    }
    
    
    if (!$error && (!isset($_POST['tovary']) || empty($_POST['tovary']))) {
        $error = 'Требуется добавить хотя бы один товар';
    }
    
    if (!$error && !empty($_POST['tovary'])) {
        foreach ($_POST['tovary'] as $index => $tovar) {
            if (!empty($tovar['naimenovanie_tovara']) || !empty($tovar['kolichestvo'])) {
                if (empty($tovar['naimenovanie_tovara'])) {
                    $error = 'Строка ' . ($index + 1) . ': Требуется указать товар';
                    break;
                }
                if (empty($tovar['kolichestvo'])) {
                    $error = 'Строка ' . ($index + 1) . ': Требуется указать остаток';
                    break;
                }
                
                $ubavit_val = floatval($tovar['ubavit'] ?? 0);
                $pribavit_val = floatval($tovar['pribavit'] ?? 0);
                if ($ubavit_val > 0 && $pribavit_val > 0) {
                    $error = 'Строка ' . ($index + 1) . ': Нельзя одновременно заполнять "Убавить" и "Прибавить"';
                    break;
                }
            }
        }
    }
    
    if (!$error) {
        $user_role = getUserRole($mysqli, $_SESSION['user_id']);
        
        if (!$user_role) {
            $error = "Доступ запрещен. Вам нужны права администратора для доступа к этой странице.";
        } else {
            if ($is_edit) {
                
                $result = obnovitDokument($mysqli, $id, $_POST);
                
                if ($result['success']) {
                    header("Location: prosmotr.php?id=" . $id);
                    exit;
                } else {
                    $error = $result['error'];
                }
            } else {
                
                $result = sozdatDokument($mysqli, $_POST);
                
                if ($result['success']) {
                    header("Location: prosmotr.php?id=" . $result['id']);
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
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="product_date">Дата поступления документа</label>
                        <input class="form-control" type="date" id="product_date" name="product_date"
                        value="<?= htmlspecialchars($_POST['product_date'] ?? $data_vypuska) ?>">
                    </div>

                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="id_sklada">Склад</label>
                        <input type="text" class="form-control" id="id_sklada" name="naimenovanie_sklada" placeholder="- Выберите склад -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_sklada'] ?? $naimenovanie_sklada) ?>">
                        <input type="hidden" name="id_sklada" class="id-sklada" value="<?= htmlspecialchars($_POST['id_sklada'] ?? $id_sklada) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3" style="position: relative;">
                        <label class="form-label" for="id_otvetstvennogo">Ответственный</label>
                        <input type="text" class="form-control" id="id_otvetstvennogo" name="naimenovanie_otvetstvennogo" placeholder="- Выберите ответственного -" autocomplete="off"
                        value="<?= htmlspecialchars($_POST['naimenovanie_otvetstvennogo'] ?? $naimenovanie_otvetstvennogo) ?>">
                        <input type="hidden" name="id_otvetstvennogo" class="id-otvetstvennogo" value="<?= htmlspecialchars($_POST['id_otvetstvennogo'] ?? $id_otvetstvennogo) ?>">
                    </div>
                </div>

                <h2 style="margin-top: 30px;"></h2>
                
                <div class="card">
                <div class="table-responsive">
                <table class="table table-vcenter card-table" id="tablitsaTovarov">
                    <thead>
                        <tr>
                            <th class="col-num">№</th>
                            <th class="col-tovar">Товар</th>
                            <th class="col-seria">Серия</th>
                            <th class="col-ostatok">Остаток</th>
                            <th class="col-ubavit">Убавить</th>
                            <th class="col-pribavit">Прибавить</th>
                            <th class="col-action"></th>
                        </tr>
                    </thead>
                    <tbody id="tovaryBody">
                        <?php if (!empty($_POST['tovary'])): ?>
                            <?php $row_index = 0; ?>
                            <?php foreach ($_POST['tovary'] as $submitted_item): ?>
                        <tr class="tovar-row">
                            <td class="col-num"><?= $row_index + 1 ?></td>
                            <td class="col-tovar">
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($submitted_item['naimenovanie_tovara'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_tovara]" class="id-tovara" value="<?= htmlspecialchars($submitted_item['id_tovara'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-seria">
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_serii]" placeholder="Введите серию..." autocomplete="off"
                                    value="<?= htmlspecialchars($submitted_item['naimenovanie_serii'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_serii]" class="id-serii" value="<?= htmlspecialchars($submitted_item['id_serii'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-ostatok"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['kolichestvo'] ?? '') ?>"></td>
                            <td class="col-ubavit"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][ubavit]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['ubavit'] ?? '') ?>"></td>
                            <td class="col-pribavit"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][pribavit]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($submitted_item['pribavit'] ?? '') ?>"></td>
                            <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php elseif ($is_edit && !empty($line_items)): ?>
                            <?php $row_index = 0; ?>
                            <?php foreach ($line_items as $item): ?>
                        <tr class="tovar-row">
                            <td class="col-num"><?= $row_index + 1 ?></td>
                            <td class="col-tovar">
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($item['naimenovanie_tovara'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_tovara]" class="id-tovara" value="<?= htmlspecialchars($item['id_tovara'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-seria">
                                <div class="search-container">
                                    <input class="form-control" type="text" name="tovary[<?= $row_index ?>][naimenovanie_serii]" placeholder="Введите серию..." autocomplete="off"
                                    value="<?= htmlspecialchars($item['naimenovanie_serii'] ?? '') ?>">
                                    <input type="hidden" name="tovary[<?= $row_index ?>][id_serii]" class="id-serii" value="<?= htmlspecialchars($item['id_serii'] ?? '') ?>">
                                </div>
                            </td>
                            <td class="col-ostatok"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($item['kolichestvo'] ?? '') ?>"></td>
                            <td class="col-ubavit"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][ubavit]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($item['ubavit'] ?? '') ?>"></td>
                            <td class="col-pribavit"><input class="form-control" type="text" name="tovary[<?= $row_index ?>][pribavit]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($item['pribavit'] ?? '') ?>"></td>
                            <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                            <?php $row_index++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="tovar-row">
                            <td class="col-num">1</td>
                            <td class="col-tovar">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_tovara]" placeholder="Введите товар..." autocomplete="off"
                                    value="<?= htmlspecialchars($naimenovanie_tovara) ?>">
                                    <input type="hidden" name="tovary[0][id_tovara]" class="id-tovara">
                                </div>
                            </td>
                            <td class="col-seria">
                                <div class="search-container" style="position: relative;">
                                    <input class="form-control" type="text" name="tovary[0][naimenovanie_serii]" placeholder="Введите серию..." autocomplete="off"
                                    value="<?= htmlspecialchars($vetis_data_loaded ? substr($product_guid, 0, 36) : '') ?>">
                                    <input type="hidden" name="tovary[0][id_serii]" class="id-serii">
                                </div>
                            </td>
                            <td class="col-ostatok"><input class="form-control" type="text" name="tovary[0][kolichestvo]" placeholder="0" autocomplete="off" value="<?= htmlspecialchars($kolichestvo) ?>"></td>
                            <td class="col-ubavit"><input class="form-control" type="text" name="tovary[0][ubavit]" placeholder="0" autocomplete="off"></td>
                            <td class="col-pribavit"><input class="form-control" type="text" name="tovary[0][pribavit]" placeholder="0" autocomplete="off"></td>
                            <td class="col-action"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash delete-row" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" onclick="deleteRow(this)"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7l16 0"></path><path d="M10 11l0 6"></path><path d="M14 11l0 6"></path><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                </div>

                <button type="button" class="btn mt-3 btn-primary" onclick="addRow()">Добавить строку</button>
                
                <div class="row" style="margin-top: 20px;">
                    <div class="col-12">
                         <div class="btn-group" role="group" aria-label="Basic example">
                        <button type="submit" class="btn btn-primary">
                            <?= $is_edit ? 'Сохранить' : 'Сохранить' ?>
                        </button>
                        <a href="<?= $is_edit ? 'prosmotr.php?id=' . htmlspecialchars($id) : 'spisok.php' ?>" class="btn">Отмена</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
        <script>
            
            document.getElementById('documentForm').addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    const target = event.target;
                    
                    if (target.closest('#tablitsaTovarov')) {
                        event.preventDefault();
                    }
                }
            });
        </script>
        
        <script src="https://cdn.jsdelivm.net/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
        
        <script>
           
            const formConfig = {
                columns: [
                    { key: 'tovar', label: 'Товар', type: 'autocomplete' },
                    { key: 'seria', label: 'Серия', type: 'autocomplete' },
                    { key: 'ostatok', label: 'Остаток', type: 'text' },
                    { key: 'ubavit', label: 'Убавить', type: 'text' },
                    { key: 'pribavit', label: 'Прибавить', type: 'text' }
                ]
            };
            
       
        </script>
        <script src="../js/add_product.js"></script>
</div>
<?php include '../footer.php'; ?>