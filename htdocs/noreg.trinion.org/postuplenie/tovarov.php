<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

// Handle document deletion if delete action is requested
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    require '../config/database_config.php';
    require '../queries/delete_product_queries.php';
    
    if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
        die("ID документа не предоставлен.");
    }
    
    $mysqli = require '../config/database.php';
    $document_id = intval($_GET['product_id']);
    
    $result = deleteArrivalDocument($mysqli, $document_id);
    
    if ($result['success']) {
        header('Location: index.php');
        exit;
    } else {
        die("Error: " . $result['message']);
    }
}

$mysqli = require '../config/database.php';

require '../queries/view_product_queries.php';


if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    die("ID документа не предоставлен.");
}

$document_id = intval($_GET['product_id']);

$document = fetchDocumentHeader($mysqli, $document_id);

if (!$document) {
    die("Документ не найден.");
}

$line_items = fetchDocumentLineItems($mysqli, $document['id_index']);

$totals = calculateTotals($line_items);
$subtotal = $totals['subtotal'];
$vat_total = $totals['vat_total'];
$total_due = $totals['total_due'];

$page_title = 'Детали документа поступлення';

include '../header.php';
?>
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
                         <button type="button" class="btn btn-primary" onclick="updateDocumentField('utverzhden', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-4 4"></path>
                                <path d="M5.828 18.172a2.828 2.828 0 0 0 4 0l10.586 -10.586a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-10.586 10.586a2.828 2.828 0 0 0 0 4z"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                              </svg>
                            Утвердить
                        </button>
                        <button type="button" class="btn btn-primary" onclick="updateDocumentField('utverzhden', false)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-2 2"></path>
                                <path d="M10 10l-4.172 4.172a2.828 2.828 0 1 0 4 4l4.172 -4.172"></path>
                                <path d="M16 12l4.414 -4.414a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-4.414 4.414"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                                <path d="M3 3l18 18"></path>
                              </svg>
                            Отменить утверждение
                        </button>
                        <button type="button" class="btn btn-primary" onclick="editDocument();">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path>
                                <path d="M16 5l3 3"></path>
                            </svg>
                            Редактировать
                        </button>
                        <button type="button" class="btn btn-primary" onclick="updateDocumentField('zakryt', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M10.507 10.498l-1.507 1.502v3h3l1.493 -1.498m2 -2.01l4.89 -4.907a2.1 2.1 0 0 0 -2.97 -2.97l-4.913 4.896"></path>
                                <path d="M16 5l3 3"></path>
                                <path d="M3 3l18 18"></path>
                              </svg>
                            Закрить
                        </button>
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены?')) window.location.href='tovarov.php?product_id=<?= $document_id ?>&action=delete';">
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
        <div class="card card-lg">
            <div class="card-body">
                <div class="row" style="position: relative;">
                    <div class="col-4">
                        <p class="h3">Организация</p>
                        <address>
                            <?= htmlspecialchars($document['organization'] ?? 'N/A') ?><br>
                        </address>
                    </div>
                    <div class="col-4 text-center">
                        <p class="h3">Дата оформления</p>
                        <address>
                            <?= htmlspecialchars($document['data_dokumenta']) ?><br>
                        </address>
                    </div>
                    <div class="col-4 text-end">
                        <p class="h3">Поставщик</p>
                        <address>
                            <?= htmlspecialchars($document['vendor'] ?? 'N/A') ?><br>
                        </address>
                    </div>
                    <div style="position: absolute; right: -55px; bottom: 90px;">
                        <?php if ($document['utverzhden']): ?>
                            <div class="ribbon bg-red">Утвержден</div>
                        <?php else: ?>
                            <div class="ribbon bg-secondary">Черновик</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 my-5">
                        <h1>Документ поступлення №<?= htmlspecialchars($document['id']) ?></h1>
                    </div>
                </div>
                <table class="table table-transparent table-responsive">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 5%"></th>
                            <th style="width: 45%">Товар</th>
                            <th style="width: 15%">Серия</th>
                            <th class="text-center" style="width: 5%">Кол-во</th>
                            <th class="text-end" style="width: 15%">Цена</th>
                            <th class="text-end" style="width: 15%">Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $item_num = 1;
                        foreach ($line_items as $item): 
                        ?>
                            <tr>
                                <td class="text-center"><?= $item_num ?></td>
                                <td>
                                    <p class="strong mb-1"><?= htmlspecialchars($item['product_name']) ?></p>
                                </td>
                                <td>
                                    <?= htmlspecialchars($item['seria_name'] ?? '-') ?>
                                    <?php if (!empty($item['data_izgotovleniya']) || !empty($item['srok_godnosti'])): ?>
                                        <small class="text-muted d-block">
                                            <?php if (!empty($item['data_izgotovleniya'])): ?>
                                                Дата изготовления: <?= htmlspecialchars(date('d/m/Y', strtotime($item['data_izgotovleniya']))) ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($item['srok_godnosti'])): ?>
                                                Срок годности: <?= htmlspecialchars(date('d/m/Y', strtotime($item['srok_godnosti']))) ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= htmlspecialchars($item['quantity']) ?> <?= htmlspecialchars($item['unit_name'] ?? '') ?></td>
                                <td class="text-end"><?= number_format($item['unit_price'], 2, ',', ' ') ?></td>
                                <td class="text-end"><?= number_format($item['total_amount'], 2, ',', ' ') ?></td>
                            </tr>
                        <?php 
                        $item_num++;
                        endforeach; 
                        ?>
                        <tr style="height: 50px;"><td colspan="6"></td></tr>
                        <tr style="height: 50px;"><td colspan="6"></td></tr>
                        <tr>
                            <td colspan="5" class="strong text-end">Промежуточный итог</td>
                            <td class="text-end"><?= number_format($subtotal, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="strong text-end">Ставка НДС</td>
                            <td class="text-end"><?= htmlspecialchars(!empty($line_items) ? $line_items[0]['vat_rate'] : 0) ?>%</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="strong text-end">НДС к оплате</td>
                            <td class="text-end"><?= number_format($vat_total, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="font-weight-bold text-uppercase text-end">Итого к оплате</td>
                            <td class="font-weight-bold text-end"><?= number_format($total_due, 2, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
                <p class="text-secondary text-center mt-5">Благодарим вас за сотрудничество. Мы надеемся на продолжение работы с вами!</p>
            </div>
        </div>
        </div>

<script>
function editDocument() {
    const isClosed = <?= json_encode((bool)$document['zakryt']) ?>;
    if (isClosed) {
        alert('Этот документ закрыт и не может быть отредактирован.');
        return;
    }
    window.location.href = 'form.php?product_id=<?= json_encode($document_id) ?>';
}

function updateDocumentField(fieldName, value) {
    const documentId = <?= json_encode($document_id) ?>;
    const tableName = 'postupleniya_tovarov';
    
    fetch('../api/toggle_field.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            table_name: tableName,
            document_id: documentId,
            field_name: fieldName,
            value: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>

<?php
include '../footer.php';
?>
