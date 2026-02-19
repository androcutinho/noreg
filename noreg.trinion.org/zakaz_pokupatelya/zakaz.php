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

$zakaz_id = isset($_GET['zakaz_id']) ? intval($_GET['zakaz_id']) : null;
$error = '';

if (!$zakaz_id) {
    die("Заказ не найден.");
}

$page_title = 'Заказ покупателя';

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $result = deleteOrderDocument($mysqli, $zakaz_id);
    if ($result['success']) {
        header('Location: index.php');
        exit;
    } else {
        $error = $result['error'];
    }
}

$order = fetchOrderHeader($mysqli, $zakaz_id);

if (!$order) {
    die("Заказ не найден.");
}

$line_items = fetchOrderLineItems($mysqli, $order['id_index']);

// Calculate totals
$total_sum = 0;
$total_nds = 0;
$nds_rates_used = [];

foreach ($line_items as $item) {
    $total_sum += floatval($item['total_amount'] ?? 0);
    $total_nds += floatval($item['nds_amount'] ?? 0);
    if (!empty($item['stavka_nds'])) {
        $nds_rates_used[] = $item['stavka_nds'];
    }
}


$nds_rates_used = array_unique($nds_rates_used);
$nds_rate_text = !empty($nds_rates_used) ? implode(', ', $nds_rates_used) : '0%';


$russian_months = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
$date = DateTime::createFromFormat('Y-m-d', $order['data_dokumenta']);
$formatted_date = $date ? $date->format('j') . ' ' . $russian_months[(int)$date->format('n')] . ' ' . $date->format('Y') . ' г.' : $order['data_dokumenta'];

include '../header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="container-fluid">
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
                        <button type="button" class="btn btn-primary" onclick="window.location.href='../otgruzki_tovarov_pokupatelyam/form.php?zakaz_id=<?= htmlspecialchars($zakaz_id) ?>';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"></path>
                                <path d="M7 8h10"></path>
                                <path d="M7 12h10"></path>
                                <path d="M7 16h10"></path>
                              </svg>
                            Отгрузить товары
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='../schet_na_oplatu/form.php?zakaz_id=<?= htmlspecialchars($zakaz_id) ?>';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"></path>
                                <path d="M7 8h10"></path>
                                <path d="M7 12h10"></path>
                                <path d="M7 16h10"></path>
                              </svg>
                            Создать счет
                        </button>
                         <?php if (!$order['utverzhden']): ?>
                        <button type="button" class="btn btn-primary" onclick="updateDocumentField('utverzhden', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-4 4"></path>
                                <path d="M5.828 18.172a2.828 2.828 0 0 0 4 0l10.586 -10.586a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-10.586 10.586a2.828 2.828 0 0 0 0 4z"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                              </svg>
                            Утвердить
                        </button>
                        <?php endif; ?>
                        <?php if ($order['utverzhden']): ?>
                        <button type="button" class="btn btn-primary" onclick="updateDocumentField('utverzhden', false)">
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
                        <button type="button" class="btn btn-primary" onclick="editDocument();">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path>
                                <path d="M16 5l3 3"></path>
                            </svg>
                            Редактировать
                        </button>
                        <?php if (!$order['zakryt']): ?>
                        <button type="button" class="btn btn-primary" onclick="updateDocumentField('zakryt', true);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M10.507 10.498l-1.507 1.502v3h3l1.493 -1.498m2 -2.01l4.89 -4.907a2.1 2.1 0 0 0 -2.97 -2.97l-4.913 4.896"></path>
                                <path d="M16 5l3 3"></path>
                                <path d="M3 3l18 18"></path>
                              </svg>
                            Закрить
                        </button>
                        <?php endif; ?>
                        <?php if ($order['zakryt']): ?>
                        <button type="button" class="btn btn-primary" onclick="updateDocumentField('zakryt', false);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 10m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                <path d="M21 12a9 9 0 1 1 -18 0a9 9 0 0 1 18 0z"></path>
                                <path d="M12.5 11.5l-4 4l1.5 1.5"></path>
                                <path d="M12 15l-1.5 -1.5"></path>
                              </svg>
                            Открыть
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены? Этот заказ будет удален.')) window.location.href='zakaz.php?zakaz_id=<?= htmlspecialchars($zakaz_id) ?>&action=delete';">
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
                        Заказ покупателя № <?= htmlspecialchars($order['nomer']) ?> от <?= htmlspecialchars($formatted_date) ?>
                    </h2>
                </div>

                <div style="position: absolute; right: 0px;">
                        <?php if ($order['utverzhden']): ?>
                            <div class="ribbon bg-red">Утвержден</div>
                        <?php else: ?>
                            <div class="ribbon bg-secondary">Черновик</div>
                        <?php endif; ?>
                    </div>

                <!-- Organization and Vendor Info -->
                <div style="margin-bottom: 30px;">
                    <div style="margin-bottom: 15px;">
                        <span style="font-weight: bold;">Поставщик<br/>(Исполнитель):</span>
                        <span><?= htmlspecialchars($order['organization_name'] ?? '') ?>, ИНН <?= htmlspecialchars($order['organization_inn'] ?? '') ?>, КПП <?= htmlspecialchars($order['organization_kpp'] ?? '') ?></span>
                    </div>
                    <div>
                        <span style="font-weight: bold;">Покупатель<br/>(Заказчик):</span>
                        <span><?= htmlspecialchars($order['vendor_name'] ?? '') ?>, ИНН <?= htmlspecialchars($order['vendor_inn'] ?? '') ?>, КПП <?= htmlspecialchars($order['vendor_kpp'] ?? '') ?></span>
                    </div>
                </div>

                

                <!-- Products Table -->
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
                                        <td style="border: 1px solid #000; padding: 8px;"><?= htmlspecialchars($item['product_name'] ?? '') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= htmlspecialchars($item['quantity'] ?? '') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: center;"><?= htmlspecialchars($item['unit_name'] ?? '') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format(floatval($item['unit_price'] ?? 0), 2, '.', ' ') ?></td>
                                        <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format(floatval($item['total_amount'] ?? 0), 2, '.', ' ') ?></td>
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
                     <div>
                        <strong>НДС (<?= htmlspecialchars($nds_rate_text) ?>):</strong> <span><?= number_format($total_nds, 2, '.', ' ') ?></span>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>Итого:</strong> <span><?= number_format($total_sum, 2, '.', ' ') ?></span>
                    </div>
                   
                </div>

                
                <div style="margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px;">
                    <p>
                        Всего наименований: <?= count($line_items) ?>, на сумму <?= number_format($total_sum, 2, ',', ' ') ?> руб.
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

               
                <?php 
                $related_documents = [];
                if (!empty($order['id_scheta_na_oplatu_pokupatelyam'])) {
                    $schet_ids_json = $order['id_scheta_na_oplatu_pokupatelyam'];
                    $doc_items = json_decode($schet_ids_json, true);
                    
                    if (!is_array($doc_items)) {
                        $doc_items = [$doc_items];
                    }
                    
                    
                    $doc_items = array_filter($doc_items, function($item) {
                        return !is_null($item) && $item !== '';
                    });
                    
                    if (!empty($doc_items)) {
                        foreach ($doc_items as $doc_item) {
                            
                            if (is_array($doc_item) && isset($doc_item['id']) && isset($doc_item['type'])) {
                                $doc_id = $doc_item['id'];
                                $doc_type = $doc_item['type'];
                            } else {
                                
                                $doc_id = is_array($doc_item) ? ($doc_item['id'] ?? $doc_item) : $doc_item;
                                $doc_type = 'invoice';
                            }
                            
                            
                            if ($doc_type === 'shipment') {
                                $doc = fetchOtgruzkiHeader($mysqli, intval($doc_id));
                                if ($doc) {
                                    $doc['document_type'] = 'shipment';
                                    $related_documents[] = $doc;
                                }
                            } else {
        
                                $doc = fetchSchetHeader($mysqli, intval($doc_id));
                                if ($doc) {
                                    $doc['document_type'] = 'invoice';
                                    $related_documents[] = $doc;
                                }
                            }
                        }
                    }
                }
                ?>
                
                <?php if (!empty($related_documents)): ?>
                <div>    
                <div style="margin-top: 40px; margin-bottom: 30px;">
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
                                <?php foreach ($related_documents as $doc): ?>
                                <tr>
                                    <td>
                                        <?= ($doc['document_type'] === 'shipment') ? 'Отгрузка' : 'Счет' ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $link = ($doc['document_type'] === 'shipment') 
                                            ? "../otgruzki_tovarov_pokupatelyam/otgruzki.php?id=" 
                                            : "../schet_na_oplatu/schet.php?id=";
                                        ?>
                                        <a href="<?= $link . htmlspecialchars($doc['id']) ?>" class="text-primary">
                                            <?= htmlspecialchars($doc['nomer'] ?? '') ?>
                                        </a>
                                    </td>
                                    <td class="text-secondary"><?= htmlspecialchars($doc['responsible_name'] ?? '') ?></td>
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
function editDocument() {
    const isClosed = <?= json_encode((bool)$order['zakryt']) ?>;
    if (isClosed) {
        alert('Этот документ закрыт и не может быть отредактирован.');
        return;
    }
    window.location.href = 'form.php?zakaz_id=<?= json_encode($zakaz_id) ?>';
}

function updateDocumentField(fieldName, value) {
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