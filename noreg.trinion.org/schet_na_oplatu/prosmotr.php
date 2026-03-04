<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../queries/schet_na_oplatu_query.php';
require '../queries/zakaz_pokupatelya_query.php';
require '../queries/database_queries.php';

$page_title = 'Счет на оплату';

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$error = '';

if (!$id) {
    die("Счет не найден.");
}


if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $result = deleteSchetDocument($mysqli, $id);
    if ($result['success']) {
        header('Location: spisok.php');
        exit;
    } else {
        $error = $result['error'];
    }
}

$schet = fetchSchetHeader($mysqli, $id);

if (!$schet) {
    die("Счет не найден.");
}

$line_items = getSchetStrokiItems($mysqli, $schet['id_index']);


$all_related = getRelatedDocumentsByIndexOsnovanie($mysqli, $schet['id_index']);

// Get parent document (zakaz)
$parent_doc = getParentDocumentByIndexOsnovannyj($mysqli, $schet['id_index']);

$related_payments = [];
if (!empty($all_related)) {
    foreach ($all_related as $doc) {
        if ($doc['table_name'] === 'platezhi') {
            $related_payments[] = $doc;
        }
    }
}


$all_related_for_display = [];
if (!empty($parent_doc)) {
    $all_related_for_display[] = $parent_doc;
}
$all_related_for_display = array_merge($all_related_for_display, $related_payments);


$obshchaya_summa = 0;
$summa_nds = 0;
$ispolzuemye_stavki_nds = [];

foreach ($line_items as $item) {
    $podytog += floatval($item['summa'] ?? 0);
    $summa_nds += floatval($item['summa_nds'] ?? 0);
    if (!empty($item['stavka_nds'])) {
        $ispolzuemye_stavki_nds[] = $item['stavka_nds'];
    }
}

$obshchaya_summa = $podytog + $summa_nds;
$ispolzuemye_stavki_nds = array_unique($ispolzuemye_stavki_nds);
$stavka_nds_tekst = !empty($ispolzuemye_stavki_nds) ? implode(', ', $ispolzuemye_stavki_nds) : '0%';


$mecyats_na_russkom = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
$date = DateTime::createFromFormat('Y-m-d', $schet['data_dokumenta']);
$formatted_date = $date ? $date->format('j') . ' ' . $mecyats_na_russkom[(int)$date->format('n')] . ' ' . $date->format('Y') . ' г.' : $schet['data_dokumenta'];

include '../header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="container-fluid mt-3">
        <div class="row mb-3 d-print-none mt-3">
                    <div class="col-auto ms-auto">
                        <button type="button" class="btn btn-primary" onclick="javascript:window.print();">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2"></path>
                                <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4"></path>
                                <path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z"></path>
                            </svg>
                            Печать
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='../platezhi/redaktirovanie.php?schet_id=<?= htmlspecialchars($id) ?>';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"></path>
                                <path d="M7 8h10"></path>
                                <path d="M7 12h10"></path>
                                <path d="M7 16h10"></path>
                              </svg>
                            Создать платеж
                        </button>
                         <?php if (!$schet['utverzhden']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta('utverzhden', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-4 4"></path>
                                <path d="M5.828 18.172a2.828 2.828 0 0 0 4 0l10.586 -10.586a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-10.586 10.586a2.828 2.828 0 0 0 0 4z"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                              </svg>
                            Утвердить
                        </button>
                        <?php endif; ?>
                        <?php if ($schet['utverzhden']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta('utverzhden', false)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-2 2"></path>
                                <path d="M10 10l-4.172 4.172a2.828 2.828 0 1 0 4 4l4.172 -4.172"></path>
                                <path d="M16 12l4.414 -4.414a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-4.414 4.414"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                                <path d="M3 3l18 18"></path>
                              </svg>
                            Разутвердить
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='redaktirovanie.php?id=<?= htmlspecialchars($id) ?>';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path>
                                <path d="M16 5l3 3"></path>
                            </svg>
                            Редактировать
                        </button>
                       <?php if (!$schet['zakryt']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta('zakryt', true);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M10.507 10.498l-1.507 1.502v3h3l1.493 -1.498m2 -2.01l4.89 -4.907a2.1 2.1 0 0 0 -2.97 -2.97l-4.913 4.896"></path>
                                <path d="M16 5l3 3"></path>
                                <path d="M3 3l18 18"></path>
                              </svg>
                            Закрыть
                        </button>
                        <?php endif; ?>
                        <?php if ($schet['zakryt']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta('zakryt', false);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 10m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                <path d="M21 12a9 9 0 1 1 -18 0a9 9 0 0 1 18 0z"></path>
                                <path d="M12.5 11.5l-4 4l1.5 1.5"></path>
                                <path d="M12 15l-1.5 -1.5"></path>
                              </svg>
                            Открыть
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены? Этот заказ будет удален.')) window.location.href='prosmotr.php?id=<?= htmlspecialchars($id) ?>&action=delete';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M4 7l16 0"></path>
                                <path d="M10 11l0 6"></path>
                                <path d="M14 11l0 6"></path>
                                <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path>
                                <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path>
                            </svg>
                            Удалить
                        </button>
                    </div>
                </div>
        <div class="card mb-5">
            <div class="card-body">
                
                <div class="mb-3">
                    <table class="w-100 border-collapse fs-4">
                        <tbody>
                            <tr>
                                <td class="border border-dark p-3 w-40 align-top">
                                    <div class="mb-3"><?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['bank_name1'] : $schet['bank_name']) ?? '') ?></div>
                                    <div class="fs-4">Банк получателя</div>
                                </td>
                                <td class="border border-dark p-3 w-auto text-start align-middle">
                                    <div class="fs-4">БИК</div>
                                </td>
                                <td class="border border-dark p-3 w-25 text-start align-middle">
                                    <div><?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['bik_bank1'] : $schet['bik_bank']) ?? '') ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-dark w-50 align-middle">
                                    <div class="d-flex h-100">
                                        <div class="d-flex p-3 w-50 align-middle">
                                            <div >ИНН <?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['inn_postavschika'] : $schet['inn_organizacii']) ?? '') ?></div>
                                        </div>
                                        <div class="d-flex border-start border-dark p-3 w-50 align-middle">
                                            <div>КПП <?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['kpp_postavschika'] : $schet['kpp_organizacii']) ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="border border-dark p-3 text-start align-middle">
                                    <div class="fs-4">Сч. №</div>
                                </td>
                                <td class="border border-dark p-3 text-start align-middle">
                                    <div><?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['correspondent_account1'] : $schet['correspondent_account']) ?? '') ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-dark p-3 align-top">
                                    <div class="mt-2"><?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['naimenovanie_postavschika'] : $schet['naimenovanie_organizacii']) ?? '') ?></div>
                                    <div class="fs-4">Получатель</div>
                                </td>
                                <td class="border border-dark p-3 text-start align-middle">
                                    <div class="fs-4">Сч. №</div>
                                </td>
                                <td class="border border-dark p-3 text-start align-middle">
                                    <div><?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['account_number1'] : $schet['account_number']) ?? '') ?></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div  class="position-absolute end-0 d-print-none">
                        <?php if ($schet['utverzhden']): ?>
                            <div class="ribbon bg-red">Утвержден</div>
                        <?php else: ?>
                            <div class="ribbon bg-secondary">Черновик</div>
                        <?php endif; ?>
                    </div>

                
                <div class="mb-3 border-bottom border-dark pb-2 mt-5">
                    <h2 class="fw-bolder">
                        Счет на оплату № <?= htmlspecialchars($schet['nomer']) ?> от <?= htmlspecialchars($formatted_date) ?>
                    </h2>
                </div>

                
                <div class="mb-3">
                    <div class="mb-3">
                        <span >Поставщик<br/>(Исполнитель):</span>
                        <span class="fw-bolder"><?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['naimenovanie_postavschika'] : $schet['naimenovanie_organizacii']) ?? '') ?>, ИНН <?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['inn_postavschika'] : $schet['inn_organizacii']) ?? '') ?>, КПП <?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['kpp_postavschika'] : $schet['kpp_organizacii']) ?? '') ?></span>
                    </div>
                    <div>
                        <span>Покупатель<br/>(Заказчик):</span>
                        <span class="fw-bolder"><?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['naimenovanie_organizacii'] : $schet['naimenovanie_postavschika']) ?? '') ?>, ИНН <?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['inn_organizacii'] : $schet['inn_postavschika']) ?? '') ?>, КПП <?= htmlspecialchars((!empty($schet['ot_postavshchika']) ? $schet['kpp_organizacii'] : $schet['kpp_postavschika']) ?? '') ?></span>
                    </div>
                </div>

                

                
                <div class="mb-3">
                    <table class="w-100 border border-collapse fs-4">
                        <thead>
                            <tr class="border border-dark">
                                <th class="border border-dark p-2 text-center fw-bold">№</th>
                                <th class="border border-dark p-2 text-center fw-bold">Товары (работы, услуги)</th>
                                <th class="border border-dark p-2 text-center fw-bold">Кол-во</th>
                                <th class="border border-dark p-2 text-center fw-bold">Ед.</th>
                                <th class="border border-dark p-2 text-center fw-bold">Цена</th>
                                <th class="border border-dark p-2 text-center fw-bold">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($line_items)): ?>
                                <?php $row_num = 1; ?>
                                <?php foreach ($line_items as $item): ?>
                                    <tr class="border border-dark">
                                        <td class="border border-dark p-2 text-center"><?= $row_num ?></td>
                                        <td class="border border-dark"><?= htmlspecialchars($item['naimenovanie_tovara'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($item['kolichestvo'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($item['naimenovanie_edinitsii'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= number_format(floatval($item['ed_cena'] ?? 0), 2, '.', ' ') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= number_format(floatval($item['summa'] ?? 0), 2, '.', ' ') ?></td>
                                    </tr>
                                    <?php $row_num++; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="border border-dark p-2 text-center">Товары не добавлены</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

               
                <div class="mb-3 text-end">
                    <div class="mt-1">
                        <strong>Подытог:</strong> <span><?= number_format($podytog, 2, '.', ' ') ?></span>
                    </div>
                    <div class="mt-1">
                        <strong>НДС (<?= htmlspecialchars($stavka_nds_tekst) ?>):</strong> <span><?= number_format($summa_nds, 2, '.', ' ') ?></span>
                    </div>
                     <div>
                        <strong>Итого:</strong> <span><?= number_format($obshchaya_summa, 2, '.', ' ') ?></span>
                    </div>
                </div>

    
                <div class="mb-3 border-bottom border-dark p-2">
                    <p>
                        Всего наименований: <?= count($line_items) ?>, на сумму <?= number_format($obshchaya_summa, 2, ',', ' ') ?> руб.
                    </p>
                </div>

          
                <div class="mb-4 p-2">
                    <div class="d-flex justify-content-between mt-4">
                        <div class="text-center">
                            <p class="mt-3">Руководитель _______________________________________</p>
                            <p>м.п.</p>
                        </div>
                        <div class="text-center">
                            <p class="mb-3">Бухгалтер ______________________________________</p>
                           
                        </div>
                    </div>
                </div>
        </div>
            </div>
        <?php if (!empty($all_related_for_display)): ?>
        <div class="card d-print-none">
            <div class="card-body">
                <h3 class="mb-2 fs-3 fw-bolder">Связанные документы</h3>
                <div class="table-responsive">
                    <table class="table border table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Тип документа</th>
                                <th>Номер</th>
                                <th>Ответственный</th>
                                <th>Дата</th>
                                <th class="text-center">Утвержден</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_related_for_display as $doc): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($doc['document_type']) ?>
                                </td>
                                <td>
                                    <?php 
                                        $link = '#';
                                        if ($doc['document_type'] === 'Платеж') {
                                            $link = '../platezhi/prosmotr.php?id=' . htmlspecialchars($doc['id']);
                                        } elseif ($doc['document_type'] === 'Заказ') {
                                            
                                            $check_query = "SELECT id FROM zakazy_pokupatelei WHERE id = ?";
                                            $check_stmt = $mysqli->prepare($check_query);
                                            $check_stmt->bind_param('i', $doc['id']);
                                            $check_stmt->execute();
                                            $customer_order = $check_stmt->get_result()->num_rows > 0;
                                            $check_stmt->close();
                                            
                                            if ($customer_order) {
                                                $link = '../zakaz_pokupatelya/prosmotr.php?zakaz_id=' . htmlspecialchars($doc['id']);
                                            } else {
                                                $link = '../zakaz_postavschiku/prosmotr.php?zakaz_id=' . htmlspecialchars($doc['id']);
                                            }
                                        }
                                    ?>
                                    <a href="<?= $link ?>" class="text-primary">
                                        <?= htmlspecialchars($doc['nomer'] ?? '') ?>
                                    </a>
                                </td>
                                <td class="text-secondary"><?= htmlspecialchars($doc['naimenovanie_otvetstvennogo'] ?? '') ?></td>
                                <td class="text-secondary">
                                    <?php 
                                    $doc_date = DateTime::createFromFormat('Y-m-d', $doc['data_dokumenta']);
                                    echo $doc_date ? $doc_date->format('d.m.Y') : htmlspecialchars($doc['data_dokumenta']);
                                    ?>
                                </td>
                                <td class="text-center text-secondary">
                                    <?= $doc['utverzhden'] ? 'Да' : 'Нет' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
                
    
        </div>
    </div>
</div>
 </div>
<script>
function redaktirovatDokument() {
    const isClosed = <?= json_encode((bool)$schet['zakryt']) ?>;
    if (isClosed) {
        alert('Этот документ закрыт и не может быть отредактирован.');
        return;
    }
    window.location.href = 'redaktirovanie.php?id=<?= json_encode($id) ?>';
}

function ObnovitPoleDokumenta(fieldName, value) {
    const documentId = <?= json_encode($id) ?>;
    const tableName = 'scheta_na_oplatu';
    
    fetch('../api/toggle_field.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            table_name: tableName,
            document_id: documentId,
            field_name: fieldName,
            value: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) location.reload();
    })
    .catch(error => console.error('Error:', error));
}
</script>

<?php include '../footer.php'; ?>