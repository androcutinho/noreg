<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../queries/schet_na_oplatu_query.php';

$page_title = 'Счет на оплату';

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$error = '';

if (!$id) {
    die("Счет не найден.");
}


if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $result = deleteSchetDocument($mysqli, $id);
    if ($result['success']) {
        header('Location: index.php');
        exit;
    } else {
        $error = $result['error'];
    }
}

$schet = fetchSchetHeader($mysqli, $id);

if (!$schet) {
    die("Счет не найден.");
}

$line_items = fetchSchetLineItems($mysqli, $schet['id_index']);

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
$date = DateTime::createFromFormat('Y-m-d', $schet['data_dokumenta']);
$formatted_date = $date ? $date->format('j') . ' ' . $russian_months[(int)$date->format('n')] . ' ' . $date->format('Y') . ' г.' : $schet['data_dokumenta'];

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
                        <button type="button" class="btn btn-primary" onclick="updateDocumentField('utverzhden', true);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-4 4"></path>
                                <path d="M5.828 18.172a2.828 2.828 0 0 0 4 0l10.586 -10.586a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-10.586 10.586a2.828 2.828 0 0 0 0 4z"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                              </svg>
                            Утвердить
                        </button>
                        <button type="button" class="btn btn-primary" onclick="updateDocumentField('utverzhden', false);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-2 2"></path>
                                <path d="M10 10l-4.172 4.172a2.828 2.828 0 1 0 4 4l4.172 -4.172"></path>
                                <path d="M16 12l4.414 -4.414a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-4.414 4.414"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                                <path d="M3 3l18 18"></path>
                              </svg>
                            Отменить утверждение
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='form.php?id=<?= htmlspecialchars($id) ?>';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path>
                                <path d="M16 5l3 3"></path>
                            </svg>
                            Редактировать
                        </button>
                        <button type="button" class="btn btn-primary" onclick="updateDocumentField('zakryt', true);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M10.507 10.498l-1.507 1.502v3h3l1.493 -1.498m2 -2.01l4.89 -4.907a2.1 2.1 0 0 0 -2.97 -2.97l-4.913 4.896"></path>
                                <path d="M16 5l3 3"></path>
                                <path d="M3 3l18 18"></path>
                              </svg>
                            Закрить
                        </button>
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены? Этот заказ будет удален.')) window.location.href='schet.php?id=<?= htmlspecialchars($id) ?>&action=delete';">
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
                <!-- Bank Account Info Table -->
                <div style="margin-bottom: 30px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                        <tbody>
                            <tr>
                                <td style="border: 1px solid #000; padding: 8px; width: 40%; vertical-align: top;">
                                    <div style="margin-bottom: 4px;"><?= htmlspecialchars($schet['bank_name'] ?? '') ?></div>
                                    <div style="font-size: 10px; color: #999;">Банк получателя</div>
                                </td>
                                <td style="border: 1px solid #000; padding: 8px; width: 10%; text-align: left; vertical-align: middle;">
                                    <div style="font-size: 10px; color: #999;">БИК</div>
                                </td>
                                <td style="border: 1px solid #000; padding: 8px; width: 25%; text-align: left; vertical-align: middle;">
                                    <div ><?= htmlspecialchars($schet['bik_bank'] ?? '') ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #000; padding: 0; width: 50%; vertical-align: middle;">
                                    <div style="display: flex; height: 100%;">
                                        <div style="flex: 1; padding: 8px; display: flex; align-items: center;">
                                            <div >ИНН <?= htmlspecialchars($schet['organization_inn'] ?? '') ?></div>
                                        </div>
                                        <div style="flex: 1; border-left: 1px solid #000; padding: 8px; display: flex; align-items: center;">
                                            <div>КПП <?= htmlspecialchars($schet['organization_kpp'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: left; vertical-align: middle;">
                                    <div style="font-size: 10px; color: #999;">Сч. №</div>
                                </td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: left; vertical-align: middle;">
                                    <div><?= htmlspecialchars($schet['correspondent_account'] ?? '') ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #000; padding: 8px; vertical-align: top;">
                                    <div style="margin-bottom: 4px;"><?= htmlspecialchars($schet['organization_name'] ?? '') ?></div>
                                    <div style="font-size: 10px; color: #999;">Получатель</div>
                                </td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: left; vertical-align: middle;">
                                    <div style="font-size: 10px; color: #999;">Сч. №</div>
                                </td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: left; vertical-align: middle;">
                                    <div><?= htmlspecialchars($schet['account_number'] ?? '') ?></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="position: absolute; right: 0px; top: 160px;">
                        <?php if ($schet['utverzhden']): ?>
                            <div class="ribbon bg-red">Утвержден</div>
                        <?php else: ?>
                            <div class="ribbon bg-secondary">Черновик</div>
                        <?php endif; ?>
                    </div>

                <!-- Header -->
                <div style="margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px;">
                    <h2 style="margin: 0; font-weight: bold;">
                        Счет на оплату № <?= htmlspecialchars($schet['nomer']) ?> от <?= htmlspecialchars($formatted_date) ?>
                    </h2>
                </div>

                <!-- Organization and Vendor Info -->
                <div style="margin-bottom: 30px;">
                    <div style="margin-bottom: 15px;">
                        <span >Поставщик<br/>(Исполнитель):</span>
                        <span style="font-weight: bold;"><?= htmlspecialchars($schet['organization_name'] ?? '') ?>, ИНН <?= htmlspecialchars($schet['organization_inn'] ?? '') ?>, КПП <?= htmlspecialchars($schet['organization_kpp'] ?? '') ?></span>
                    </div>
                    <div>
                        <span>Покупатель<br/>(Заказчик):</span>
                        <span style="font-weight: bold;"><?= htmlspecialchars($schet['vendor_name'] ?? '') ?>, ИНН <?= htmlspecialchars($schet['vendor_inn'] ?? '') ?>, КПП <?= htmlspecialchars($schet['vendor_kpp'] ?? '') ?></span>
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
                    <div style="margin-bottom: 10px;">
                        <strong>Итого:</strong> <span><?= number_format($total_sum, 2, '.', ' ') ?></span>
                    </div>
                    <div>
                        <strong>НДС (<?= htmlspecialchars($nds_rate_text) ?>):</strong> <span><?= number_format($total_nds, 2, '.', ' ') ?></span>
                    </div>
                </div>

                <!-- Text representation of sum -->
                <div style="margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px;">
                    <p>
                        Всего наименований: <?= count($line_items) ?>, на сумму <?= number_format($total_sum, 2, ',', ' ') ?> руб.
                    </p>
                </div>

                <!-- Signature section -->
                <div style="margin-bottom: 40px; padding: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-top: 40px;">
                        <div style="text-align: center;">
                            <p style="margin-bottom: 30px;">Руководитель _______________________________________</p>
                            <p style="margin: 0; margin-right: -90px;">м.п.</p>
                        </div>
                        <div style="text-align: center;">
                            <p style="margin-bottom: 30px;">Бухгалтер ______________________________________</p>
                           
                        </div>
                    </div>
                </div>

                
            </div>
        </div>
    </div>
</div>

<script>
function editDocument() {
    const isClosed = <?= json_encode((bool)$schet['zakryt']) ?>;
    if (isClosed) {
        alert('Этот документ закрыт и не может быть отредактирован.');
        return;
    }
    window.location.href = 'form.php?id=<?= json_encode($id) ?>';
}

function updateDocumentField(fieldName, value) {
    const documentId = <?= json_encode($id) ?>;
    const tableName = 'scheta_na_oplatu_pokupatelyam';
    
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