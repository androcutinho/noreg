<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../queries/postuplenie_queries.php';

$id_tovara = isset($_GET['id_tovara']) ? intval($_GET['id_tovara']) : null;
$error = '';

if (!$id_tovara) {
    die("Документ не найден.");
}

$page_title = 'Поступление товара';

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $result = udalitPribytieDokument($mysqli, $id_tovara);
    if ($result['success']) {
        header('Location: spisok.php');
        exit;
    } else {
        $error = $result['message'];
    }
}

$document = getDokumentHeader($mysqli, $id_tovara);

if (!$document) {
    die("Документ не найден.");
}

$line_items = getStrokiDokumentovItems($mysqli, $document['id_index']);
$totals = calculateTotals($line_items);
$summa = $totals['subtotal'];
$summa_nds = $totals['vat_total'];

$mecyats_na_russkom = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
$date = DateTime::createFromFormat('Y-m-d', $document['data_dokumenta']);
$formatted_date = $date ? $date->format('j') . ' ' . $mecyats_na_russkom[(int)$date->format('n')] . ' ' . $date->format('Y') . ' г.' : $document['data_dokumenta'];

include '../header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="container-fluid">
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
                         <?php if (!$document['utverzhden']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta('utverzhden', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-4 4"></path>
                                <path d="M5.828 18.172a2.828 2.828 0 0 0 4 0l10.586 -10.586a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-10.586 10.586a2.828 2.828 0 0 0 0 4z"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                              </svg>
                            Утвердить
                        </button>
                        <?php endif; ?>
                        <?php if ($document['utverzhden']): ?>
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
                        <button type="button" class="btn btn-primary" onclick="redaktirovatDokument();">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path>
                                <path d="M16 5l3 3"></path>
                            </svg>
                            Редактировать
                        </button>
                       <?php if (!$document['zakryt']): ?>
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
                        <?php if ($document['zakryt']): ?>
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
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены?')) window.location.href='prosmotr.php?id_tovara=<?= htmlspecialchars($id_tovara) ?>&action=delete';">
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
        <div class="card">
            <div class="card-body">
                
                <div class="mb-3 border-bottom border-dark pb-1">
                    <h2 class="fw-bolder">
                        Поступление товара № <?= htmlspecialchars($id_tovara) ?> от <?= htmlspecialchars($formatted_date) ?>
                    </h2>
                </div>

                <div  class="position-absolute end-0">
                        <?php if ($document['utverzhden']): ?>
                            <div class="ribbon bg-red">Утвержден</div>
                        <?php else: ?>
                            <div class="ribbon bg-secondary">Черновик</div>
                        <?php endif; ?>
                    </div>

                
                <div class="mb-4">
                    <div class="mb-3">
                        <span>Поставщик<br/>(Исполнитель):</span>
                        <span class="fw-bolder fs-4"><?= htmlspecialchars($document['naimenovanie_postavschika'] ?? '') ?>, ИНН <?= htmlspecialchars($document['inn_postavschika'] ?? '') ?>, КПП <?= htmlspecialchars($document['kpp_postavschika'] ?? '') ?></span>
                    </div>
                    <div class="mb-3">
                        <span>Покупатель<br/>(Заказчик):</span>
                        <span class="fw-bolder fs-4"><?= htmlspecialchars($document['org_name'] ?? '') ?>, ИНН <?= htmlspecialchars($document['org_inn'] ?? '') ?>, КПП <?= htmlspecialchars($document['org_kpp'] ?? '') ?></span>
                    </div>
                    <div>
                        <span>Склад:</span>
                        <span class="fw-bolder fs-4"><?= htmlspecialchars($document['naimenovanie_sklada'] ?? '') ?></span>
                    </div>
                </div>

                
                <div class="mb-3">
                    <table class="w-100 border fs-4">
                        <thead>
                            <tr class="border border-dark">
                                <th class="border border-dark p-2 text-center fw-bold">№</th>
                                <th class="border border-dark p-2 text-center fw-bold">Товары</th>
                                <th class="border border-dark p-2 text-center fw-bold">Серия</th>
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
                                    <tr class= "border border-dark">
                                        <td class="border border-dark p-2 text-center"><?= $row_num ?></td>
                                        <td class="border border-dark ps-3"><?= htmlspecialchars($item['naimenovanie_tovara'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($item['naimenovanie_serii'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($item['kolichestvo'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($item['naimenovanie_edinitsii'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= number_format(floatval($item['ed_cena'] ?? 0), 2, '.', ' ') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= number_format(floatval($item['obshchaya_summa'] ?? 0), 2, '.', ' ') ?></td>
                                    </tr>
                                    <?php $row_num++; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class=" border border-dark p-3 text-center">Товары не добавлены</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

             
                <div class="mb-3 text-end">
                    <div class="mb-2">
                        <strong>Подытог:</strong> <span><?= number_format($summa, 2, '.', ' ') ?></span>
                    </div>
                     <div class="mb-2">
                        <strong>НДС:</strong> <span><?= number_format($summa_nds, 2, '.', ' ') ?></span>
                    </div>
                    <div>
                        <strong>Итого:</strong> <span><?= number_format($summa + $summa_nds, 2, '.', ' ') ?></span>
                    </div>
                </div>

                <div class="mb-3 border-bottom border-dark p-3">
                </div>

                <div class="mb-3 p-1">
                    <div class="d-flex justify-content-between mt-5">
                        <div class="text-center">
                            <p class="mb-3">Поставщик _______________________________________</p>
                            <p>м.п.</p>
                        </div>
                         <div class="text-center">
                            <p class="mb-3">Покупатель ______________________________________</p>
                           
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function redaktirovatDokument() {
    const isClosed = <?= json_encode((bool)$document['zakryt']) ?>;
    if (isClosed) {
        alert('Этот документ закрыт и не может быть отредактирован.');
        return;
    }
    window.location.href = 'redaktirovanie.php?id_tovara=<?= json_encode($id_tovara) ?>';
}

function ObnovitPoleDokumenta(fieldName, value) {
    const documentId = <?= json_encode($id_tovara) ?>;
    const tableName = 'postupleniya_tovarov';
    
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