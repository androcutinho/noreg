<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../queries/zakaz_pokupatelya_query.php';
require '../queries/schet_na_oplatu_query.php';
require '../queries/otgruzki_tovarov_queries.php';
require '../queries/zosdat_edit_specifikatsiu.php';

$zakaz_id = isset($_GET['zakaz_id']) ? intval($_GET['zakaz_id']) : null;
$error = '';

if (!$zakaz_id) {
    die("Заказ не найден.");
}

$page_title = 'Заказ покупателя';

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $result = udalitZakazDokument($mysqli, $zakaz_id);
    if ($result['success']) {
        header('Location: spisok.php');
        exit;
    } else {
        $error = $result['error'];
    }
}

$zakaz = getZakazHeader($mysqli, $zakaz_id);

if (!$zakaz) {
    die("Заказ не найден.");
}

$line_items = getZakazStrokiItemsPokupatieliu($mysqli, $zakaz['id_index']);


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
$date = DateTime::createFromFormat('Y-m-d', $zakaz['data_dokumenta']);
$formatted_date = $date ? $date->format('j') . ' ' . $mecyats_na_russkom[(int)$date->format('n')] . ' ' . $date->format('Y') . ' г.' : $zakaz['data_dokumenta'];

include '../header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>
<div class="card-body">
        <div class="row mb-3 d-print-none" style="margin-top: 30px;">
                    <div class="col-auto ms-auto">
                        <button type="button" class="btn btn-primary" onclick="javascript:window.print();">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2"></path>
                                <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4"></path>
                                <path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z"></path>
                            </svg>
                            Печать
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='../noreg_specifikacii/redaktirovanie.php?zakaz_id=<?= htmlspecialchars($zakaz_id) ?>&pokupatelya=1';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"></path>
                                <path d="M7 8h10"></path>
                                <path d="M7 12h10"></path>
                                <path d="M7 16h10"></path>
                              </svg>
                            Создать спецификацию
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='../otgruzki_tovarov/redaktirovanie.php?zakaz_id=<?= htmlspecialchars($zakaz_id) ?>&pokupatelya=1';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"></path>
                                <path d="M7 8h10"></path>
                                <path d="M7 12h10"></path>
                                <path d="M7 16h10"></path>
                              </svg>
                            Отгрузить товары
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='../schet_na_oplatu/redaktirovanie.php?zakaz_id=<?= htmlspecialchars($zakaz_id) ?>';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"></path>
                                <path d="M7 8h10"></path>
                                <path d="M7 12h10"></path>
                                <path d="M7 16h10"></path>
                              </svg>
                            Создать счет
                        </button>
                         <?php if (!$zakaz['utverzhden']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta('utverzhden', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-4 4"></path>
                                <path d="M5.828 18.172a2.828 2.828 0 0 0 4 0l10.586 -10.586a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-10.586 10.586a2.828 2.828 0 0 0 0 4z"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                              </svg>
                            Утвердить
                        </button>
                        <?php endif; ?>
                        <?php if ($zakaz['utverzhden']): ?>
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
                        <?php if (!$zakaz['zakryt']): ?>
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
                        <?php if ($zakaz['zakryt']): ?>
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
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены? Этот заказ будет удален.')) window.location.href='prosmotr.php?zakaz_id=<?= htmlspecialchars($zakaz_id) ?>&action=delete';">
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
                <!-- Header -->
                <div style="margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px;">
                    <h2 style="margin: 0; font-weight: bold;">
                        Заказ покупателя № <?= htmlspecialchars($zakaz['nomer']) ?> от <?= htmlspecialchars($formatted_date) ?>
                    </h2>
                </div>

                <div style="position: absolute; right: 0px;">
                        <?php if ($zakaz['utverzhden']): ?>
                            <div class="ribbon bg-red">Утвержден</div>
                        <?php else: ?>
                            <div class="ribbon bg-secondary">Черновик</div>
                        <?php endif; ?>
                    </div>

                <!-- Organization and Vendor Info -->
                <div style="margin-bottom: 30px;">
                    <div style="margin-bottom: 15px;">
                        <span style="font-weight: bold;">Поставщик<br/>(Исполнитель):</span>
                        <span><?= htmlspecialchars($zakaz['naimenovanie_organizacii'] ?? '') ?>, ИНН <?= htmlspecialchars($zakaz['inn_organizacii'] ?? '') ?>, КПП <?= htmlspecialchars($zakaz['kpp_organizacii'] ?? '') ?></span>
                    </div>
                    <div>
                        <span style="font-weight: bold;">Покупатель<br/>(Заказчик):</span>
                        <span><?= htmlspecialchars($zakaz['naimenovanie_postavschika'] ?? '') ?>, ИНН <?= htmlspecialchars($zakaz['inn_postavschika'] ?? '') ?>, КПП <?= htmlspecialchars($zakaz['kpp_postavschika'] ?? '') ?></span>
                    </div>
                </div>

                

                <!-- tovary Table -->
                <div style="margin-bottom: 30px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="border: 1px solid #000;">
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">№</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Товары (работы, услуги)</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Кол-во</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Ед.</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Цена</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($line_items)): ?>
                                <?php $row_num = 1; ?>
                                <?php foreach ($line_items as $item): ?>
                                    <tr style="border: 1px solid #000;">
                                        <td style="border: 1px solid #000; padding: 8px; text-align: center;"><?= $row_num ?></td>
                                        <td style="border: 1px solid #000; padding: 8px;"><?= htmlspecialchars($item['naimenovanie_tovara'] ?? '') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= htmlspecialchars($item['kolichestvo'] ?? '') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: center;"><?= htmlspecialchars($item['naimenovanie_edinitsii'] ?? '') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format(floatval($item['ed_cena'] ?? 0), 2, '.', ' ') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format(floatval($item['summa'] ?? 0), 2, '.', ' ') ?></td>
                                    </tr>
                                    <?php $row_num++; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="border: 1px solid #000; padding: 8px; text-align: center;">Товары не добавлены</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div style="margin-bottom: 30px; text-align: right;">
                     <div style="margin-bottom: 10px;">
                        <strong>Подытог:</strong> <span><?= number_format($podytog, 2, '.', ' ') ?></span>
                    </div>
                     <div style="margin-bottom: 10px;">
                        <strong>НДС (<?= htmlspecialchars($stavka_nds_tekst) ?>):</strong> <span><?= number_format($summa_nds, 2, '.', ' ') ?></span>
                    </div>
                     <div style="margin-bottom: 10px;">
                        <strong>Итого:</strong> <span><?= number_format($obshchaya_summa, 2, '.', ' ') ?></span>
                    </div>
                   
                   
                </div>

                
                <div style="margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px;">
                    <p>
                        Всего наименований: <?= count($line_items) ?>, на сумму <?= number_format($obshchaya_summa, 2, ',', ' ') ?> руб.
                    </p>
                </div>

                
                <div style="margin-bottom: 40px; padding: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-top: 40px;">
                        <div style="text-align: center;">
                            <p style="margin-bottom: 30px;">Поставщик _______________________________________</p>
                            <p style="margin: 0; margin-right: -90px;">м.п.</p>
                        </div>
                        <div style="text-align: center;">
                            <p style="margin-bottom: 30px;">Покупатель ______________________________________</p>
                           
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

        <?php 

$svyazannye_dokumenty = getRelatedDocumentsByIndexOsnovanie($mysqli, $zakaz['id_index']);
?>

    <?php if (!empty($svyazannye_dokumenty)): ?>
    <div class="card d-print-none" >
        <div class="card-body">
            <h3 style="margin-bottom: 20px; font-size: 16px; font-weight: bold;">Связанные документы</h3>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
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
                        <?php foreach ($svyazannye_dokumenty as $doc): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($doc['document_type']) ?>
                            </td>
                            <td>
                                <?php 
                                
                                $table_name = '';
                                if (stripos($doc['document_type'], 'Отгрузк') !== false) {
                                    $link = "../otgruzki_tovarov/prosmotr.php?id=";
                                } elseif (stripos($doc['document_type'], 'Спецификац') !== false) {
                                    $link = "../noreg_specifikacii/prosmotr.php?id=";
                                } else {
                                    $link = "../schet_na_oplatu/prosmotr.php?id=";
                                }
                                ?>
                                <a href="<?= $link . htmlspecialchars($doc['id']) ?>" class="text-primary">
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
    <?php endif;?>
</div>

<script>
function redaktirovatDokument() {
    const isClosed = <?= json_encode((bool)$zakaz['zakryt']) ?>;
    if (isClosed) {
        alert('Этот документ закрыт и не может быть отредактирован.');
        return;
    }
    window.location.href = 'redaktirovanie.php?zakaz_id=<?= json_encode($zakaz_id) ?>';
}

function ObnovitPoleDokumenta(fieldName, value) {
    const documentId = <?= json_encode($zakaz_id) ?>;
    const tableName = 'zakazy_pokupatelei';
    
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