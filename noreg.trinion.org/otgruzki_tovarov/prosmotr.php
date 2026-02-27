<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../queries/otgruzki_tovarov_queries.php';
require '../queries/database_queries.php';

$page_title = 'Отгрузки товаров';

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$error = '';

if (!$id) {
    die("Счет не найден.");
}


if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $result = deleteOtgruzkiDocument($mysqli, $id);
    if ($result['success']) {
        header('Location: spisok.php');
        exit;
    } else {
        $error = $result['error'];
    }
}

$otgruzki = fetchOtgruzkiHeader($mysqli, $id);

if (!$otgruzki) {
    die("Отгрузка не найдена.");
}

$parent_doc = getParentDocumentByIndexOsnovannyj($mysqli, $otgruzki['id_index']);

$line_items = fetchOtgruzkiLineItems($mysqli, $otgruzki['id_index']);


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
$date = DateTime::createFromFormat('Y-m-d', $otgruzki['data_dokumenta']);
$formatted_date = $date ? $date->format('j') . ' ' . $mecyats_na_russkom[(int)$date->format('n')] . ' ' . $date->format('Y') . ' г.' : $otgruzki['data_dokumenta'];

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
                        <?php if (!$otgruzki['utverzhden']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta('utverzhden', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-4 4"></path>
                                <path d="M5.828 18.172a2.828 2.828 0 0 0 4 0l10.586 -10.586a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-10.586 10.586a2.828 2.828 0 0 0 0 4z"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                              </svg>
                            Утвердить
                        </button>
                        <?php endif; ?>
                        <?php if ($otgruzki['utverzhden']): ?>
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
                        <button type="button" class="btn btn-primary" onclick="window.location.href='redaktirovanie.php?id=<?= htmlspecialchars($id) ?>&ot_postavshchika=<?= $otgruzki['ot_postavshchika'] ? '1' : '0' ?>';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path>
                                <path d="M16 5l3 3"></path>
                            </svg>
                            Редактировать
                        </button>
                        <?php if (!$otgruzki['zakryt']): ?>
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
                        <?php if ($otgruzki['zakryt']): ?>
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
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены? Отгрузка будет удалена.')) window.location.href='prosmotr.php?id=<?= htmlspecialchars($id) ?>&action=delete';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler;"
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
                <div style="position: absolute; right: 0px; top: 160px;">
                        <?php if ($otgruzki['utverzhden']): ?>
                            <div class="ribbon bg-red">Утвержден</div>
                        <?php else: ?>
                            <div class="ribbon bg-secondary">Черновик</div>
                        <?php endif; ?>
                    </div>

                <!-- Header -->
                <div style="margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px;">
                    <h2 style="margin: 0; font-weight: bold;">
                        Отгрузка № <?= htmlspecialchars($otgruzki['nomer']) ?> от <?= htmlspecialchars($formatted_date) ?>
                    </h2>
                </div>

                
                <div style="margin-bottom: 30px;">
                    <?php if ($otgruzki['ot_postavshchika']): ?>
            
                        <div style="margin-bottom: 15px;">
                            <span>Поставщик<br/>(Исполнитель):</span>
                            <span style="font-weight: bold;"><?= htmlspecialchars($otgruzki['naimenovanie_organizacii'] ?? '') ?>, ИНН <?= htmlspecialchars($otgruzki['inn_organizacii'] ?? '') ?>, КПП <?= htmlspecialchars($otgruzki['kpp_organizacii'] ?? '') ?></span>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <span>Покупатель<br/>(Получатель):</span>
                            <span style="font-weight: bold;"><?= htmlspecialchars($otgruzki['naimenovanie_postavschika'] ?? '') ?>, ИНН <?= htmlspecialchars($otgruzki['inn_postavschika'] ?? '') ?>, КПП <?= htmlspecialchars($otgruzki['kpp_postavschika'] ?? '') ?></span>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: 15px;">
                            <span>Поставщик<br/>(Исполнитель):</span>
                            <span style="font-weight: bold;"><?= htmlspecialchars($otgruzki['naimenovanie_organizacii'] ?? '') ?>, ИНН <?= htmlspecialchars($otgruzki['inn_organizacii'] ?? '') ?>, КПП <?= htmlspecialchars($otgruzki['kpp_organizacii'] ?? '') ?></span>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <span>Покупатель<br/>(Получатель):</span>
                            <span style="font-weight: bold;"><?= htmlspecialchars($otgruzki['naimenovanie_postavschika'] ?? '') ?>, ИНН <?= htmlspecialchars($otgruzki['inn_postavschika'] ?? '') ?>, КПП <?= htmlspecialchars($otgruzki['kpp_postavschika'] ?? '') ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="margin-bottom: 15px;">
                        <span>Склад отгрузки:</span>
                        <span style="font-weight: bold;"><?= htmlspecialchars($otgruzki['naimenovanie_sklada'] ?? 'Не указан') ?></span>
                    </div>
                    <div>
                        <span><?= $otgruzki['ot_postavshchika'] ? 'Заказ поставщику' : 'Заказ покупателя' ?>:</span>
                        <span style="font-weight: bold;"><?= htmlspecialchars($otgruzki['customer_order_nomer'] ?? 'Не указан') ?></span>
                    </div>
                </div>

                

                
                <div style="margin-bottom: 30px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="border: 1px solid #000;">
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">№</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Товары (работы, услуги)</th>
                                <?php if (!$otgruzki['ot_postavshchika']): ?>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Серия</th>
                                <?php endif; ?>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Кол-во</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Ед.</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Цена</th>
                                <?php if (!$otgruzki['ot_postavshchika']): ?>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">Склад</th>
                                <?php endif; ?>
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
                                        <?php if (!$otgruzki['ot_postavshchika']): ?>
                                        <td style="border: 1px solid #000; padding: 8px;"><?= htmlspecialchars($item['naimenovanie_serii'] ?? '-') ?></td>
                                        <?php endif; ?>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= htmlspecialchars($item['kolichestvo'] ?? '') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: center;"><?= htmlspecialchars($item['naimenovanie_edinitsii'] ?? '') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format(floatval($item['ed_cena'] ?? 0), 2, '.', ' ') ?></td>
                                        <?php if (!$otgruzki['ot_postavshchika']): ?>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: center;"><?= htmlspecialchars($item['naimenovanie_sklada'] ?? 'Не указан') ?></td>
                                        <?php endif; ?>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format(floatval($item['summa'] ?? 0), 2, '.', ' ') ?></td>
                                    </tr>
                                    <?php $row_num++; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="border: 1px solid #000; padding: 8px; text-align: center;">Товары не добавлены</td>
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

                <!-- Text representation of sum -->
                <div style="margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px;">
                    <p>
                        Всего наименований: <?= count($line_items) ?>, на сумму <?= number_format($obshchaya_summa, 2, ',', ' ') ?> руб.
                    </p>
                </div>

                <!-- Signature section -->
                <div style="margin-bottom: 40px; padding: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-top: 40px;">
                        <div style="text-align: center;">
                            <p style="margin-bottom: 30px;">Поставащик _______________________________________</p>
                            <p style="margin: 0; margin-right: -90px;">м.п.</p>
                        </div>
                        <div style="text-align: center;">
                            <p style="margin-bottom: 30px;">Покупатель ______________________________________</p>
                           
                        </div>
                    </div>
                </div>
                </div>
                </div>

                <!-- Related documents -->
                <?php if (!empty($parent_doc)): ?>
                <div class="card d-print-none">
                    <div class="card-body">
                    <h4 style="margin-bottom: 20px; font-size: 16px; font-weight: bold;">Связанные документы</h4>
                    <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th style="padding: 8px;">Тип документа</th>
                                <th style="padding: 8px;">Номер</th>
                                <th style="padding: 8px;">Ответственный</th>
                                <th style="padding: 8px;">Дата</th>
                                <th style="padding: 8px;">Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 8px;"><?= htmlspecialchars($parent_doc['document_type']) ?></td>
                                <td style="padding: 8px;">
                                    <?php 
                                        if ($parent_doc['document_type'] === 'Заказ') {
                                            $check_query = "SELECT id FROM zakazy_pokupatelei WHERE id = ?";
                                            $check_stmt = $mysqli->prepare($check_query);
                                            $check_stmt->bind_param('i', $parent_doc['id']);
                                            $check_stmt->execute();
                                            $customer_order = $check_stmt->get_result()->num_rows > 0;
                                            $check_stmt->close();
                                            
                                            if ($customer_order) {
                                                $link = '../zakaz_pokupatelya/prosmotr.php?zakaz_id=' . htmlspecialchars($parent_doc['id']);
                                            } else {
                                                $link = '../zakaz_postavschiku/prosmotr.php?zakaz_id=' . htmlspecialchars($parent_doc['id']);
                                            }
                                        }
                                    ?>
                                    <a href="<?= $link ?>"><?= htmlspecialchars($parent_doc['nomer']) ?></a>
                                </td>
                                <td style="padding: 8px;"><?= htmlspecialchars($parent_doc['naimenovanie_otvetstvennogo'] ?? '') ?></td>
                                <td style="padding: 8px;">
                                    <?php 
                                        $doc_date = DateTime::createFromFormat('Y-m-d', $parent_doc['data_dokumenta']);
                                        echo $doc_date ? $doc_date->format('d.m.Y') : htmlspecialchars($parent_doc['data_dokumenta']);
                                    ?>
                                </td>
                                <td style="padding: 8px;"><?= $parent_doc['utverzhden'] ? 'Да' : 'Нет' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>
                </div>
                <?php endif; ?>

                
            </div>
        </div>
    

<script>
function redaktirovatDokument() {
    const isClosed = <?= json_encode((bool)$otgruzki['zakryt']) ?>;
    if (isClosed) {
        alert('Этот документ закрыт и не может быть отредактирован.');
        return;
    }
    window.location.href = 'redaktirovanie.php?id=<?= json_encode($id) ?>';
}

function ObnovitPoleDokumenta(fieldName, value) {
    const documentId = <?= json_encode($id) ?>;
    const tableName = 'otgruzki_tovarov_pokupatelyam';
    
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