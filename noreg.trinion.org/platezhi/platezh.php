<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

$mysqli = require '../config/database.php';
require '../config/database_config.php';
require '../queries/database_queries.php';
require '../queries/platezhi_queries.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$id) {
    die("Платеж не найден.");
}

$payment = fetchPaymentForDisplay($mysqli, $id);

if (!$payment) {
    die("Платеж не найден.");
}


$payer_info = fetchContractorInfo($mysqli, $payment['id_kontragenti_platelshik']);
$recipient_info = fetchContractorInfo($mysqli, $payment['id_kontragenti_poluchatel']);
$display_payer_info = $payment['iskhodyashchij'] ? $recipient_info : $payer_info;
$display_payer_id = $payment['iskhodyashchij'] ? $payment['id_kontragenti_poluchatel'] : $payment['id_kontragenti_platelshik'];
$bank_details = fetchPaymentBankDetails($mysqli, $display_payer_id);
$line_items = fetchPaymentLineItemsForDisplay($mysqli, $payment['nomer']);
$payment_date = new DateTime($payment['data_dokumenta']);
$formatted_date = $payment_date->format('d.m.Y H:i');

$payment_type = $payment['vhodyashchij'] ? 'Входящий платеж' : 'Исходящий платеж';
$page_title= $payment['vhodyashchij'] ? 'Входящий платеж' : 'Исходящий платеж';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    deletePaymentDocument($mysqli, $id);
    header('Location: index.php');
    exit;
}

include '../header.php';
?>

<div class="page-body">
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
            <a href="form.php?id=<?= htmlspecialchars($id) ?>" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                    <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path>
                    <path d="M16 5l3 3"></path>
                </svg>
                Редактировать
            </a>
            <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены, что хотите удалить этот платеж? Это действие нельзя отменить.')) { document.getElementById('deleteForm').submit(); }">
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
    <div style="text-align: center; margin-bottom: 30px;">
        <h4 style="color: #666; margin-bottom: 5px;">
            <?= isset($display_payer_info['naimenovanie']) ? htmlspecialchars($display_payer_info['naimenovanie']) : 'Компания' ?>
        </h4>
    </div>

    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="font-size: 2.5rem; margin-bottom: 0;"><?= $payment_type ?></h1>
    </div>

    
    <div class="row" style="margin-bottom: 40px;">
        
        <div class="col-md-8">
            <div style="border-bottom: 1px solid #ccc; padding-bottom: 15px; margin-bottom: 15px;">
                <div style="display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #eee;">
                    <span style="color: #666; font-size: 0.9rem;">Номер вх. платежа</span>
                    <strong style="font-size: 1rem;"><?= htmlspecialchars($payment['id']) ?></strong>
                </div>
                <div style="display: flex; align-items: center; gap: 50px; padding: 12px 0; border-bottom: 1px solid #eee;">
                    <span style="color: #666; font-size: 0.9rem;">Дата платежа</span>
                    <strong style="font-size: 1rem;"><?= htmlspecialchars($formatted_date) ?></strong>
                </div>
                <div style="display: flex; align-items: center; gap: 25px; padding: 12px 0; border-bottom: 1px solid #eee;">
                    <span style="color: #666; font-size: 0.9rem;">Номер документа</span>
                    <strong style="font-size: 1rem;"><?= htmlspecialchars($payment['nomer']) ?></strong>
                </div>
                <div style="display: flex; align-items: center; gap: 15px; padding: 12px 0;">
                    <span style="color: #666; font-size: 0.9rem;">Платежная система</span>
                    <strong style="font-size: 1rem;">Банковский перевод</strong>
                </div>
            </div>
        </div>

    
        <div class="col-md-4">
            <div style="background-color: #28a745; color: white; padding: 40px 30px; text-align: center; border-radius: 4px; height: 100%;">
                <div style="font-size: 0.9rem; margin-bottom: 15px;">Итого получено</div>
                <div style="font-size: 2.5rem; font-weight: bold;">
                    <?= number_format(floatval($payment['summa']), 2, '.', ' ') ?>
                </div>
            </div>
        </div>
    </div>

   
    <div class="row" style="margin-bottom: 40px;">
        <div class="col-md-6">
            <div style="padding-right: 20px;">
                <h5 style="margin-bottom: 20px; color: #333;">Плательщик</h5>
                <?php if ($display_payer_info): ?>
                    <div style="margin-bottom: 15px;">
                        <strong style="font-weight: 600;"><?= htmlspecialchars($display_payer_info['naimenovanie']) ?></strong>
                    </div>
                    <div style="color: #666; font-size: 0.9rem; line-height: 1.8;">
                        <div>Адрес:</div>
                        <?php if (!empty($display_payer_info['address_fact'])): ?>
                            <div style="margin-bottom: 15px;"><?= htmlspecialchars($display_payer_info['address_fact']) ?></div>
                        <?php else: ?>
                            <div style="margin-bottom: 15px;">—</div>
                        <?php endif; ?>
                        <div>ИНН / КПП: 
                            <?php if (!empty($display_payer_info['inn']) || !empty($display_payer_info['kpp'])): ?>
                                <?= htmlspecialchars($display_payer_info['inn'] ?? '—') ?> / <?= htmlspecialchars($display_payer_info['kpp'] ?? '—') ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-6">
            <div>
                <h5 style="margin-bottom: 20px; color: #333;">Банковские реквизиты плательщика</h5>
                <?php if ($bank_details): ?>
                    <div style="color: #666; font-size: 0.9rem; line-height: 1.8;">
                        <div>
                            <strong>Наименование банка:</strong>
                            <div><?= htmlspecialchars($bank_details['naimenovanie_banka'] ?? '—') ?></div>
                        </div>
                        <div style="margin-top: 15px;">
                            <strong>БИК:</strong>
                            <div><?= htmlspecialchars($bank_details['BIK_banka'] ?? '—') ?></div>
                        </div>
                        <div style="margin-top: 15px;">
                            <strong>Рассчетный счет:</strong>
                            <div><?= htmlspecialchars($bank_details['nomer_korrespondentskogo_scheta'] ?? '—') ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <hr style="margin: 40px 0; border-color: #ddd;">

    
    <div style="margin-bottom: 40px;">
        <h4 style="margin-bottom: 20px; color: #333;">Получено за</h4>
        
        <div class="card">
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background-color: #4a5568; color: black; border: 1px solid #000;">
                            <th style="color: white; font-weight: bold; padding: 12px; border: 1px solid #000; text-align: left;">Номер счета</th>
                            <th style="color: white; font-weight: bold; padding: 12px; border: 1px solid #000; text-align: left;">Дата</th>
                            <th style="color: white; font-weight: bold; padding: 12px; border: 1px solid #000; text-align: right;">Сумма</th>
                            <th style="color: white; font-weight: bold; padding: 12px; border: 1px solid #000; text-align: right;">Оплачено</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($line_items)): ?>
                            <?php foreach ($line_items as $item): ?>
                                <?php 
                                    $item_date = new DateTime($item['schet_date']);
                                    $formatted_item_date = $item_date->format('d.m.Y');
                                    $item_total = floatval($item['summa']) + floatval($item['summa_nds']);
                                ?>
                                <tr style="border: 1px solid #000;">
                                    <td style="border: 1px solid #000; padding: 8px;"><?= htmlspecialchars($item['id_dokumenta']) ?></td>
                                    <td style="border: 1px solid #000; padding: 8px;"><?= htmlspecialchars($formatted_item_date) ?></td>
                                    <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format(floatval($item['summa']), 2, '.', ' ') ?></td>
                                    <td style="border: 1px solid #000; padding: 8px; text-align: right;"><strong><?= number_format($item_total, 2, '.', ' ') ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr style="border: 1px solid #000;">
                                <td colspan="4" style="border: 1px solid #000; padding: 8px; text-align: center; color: #999;">Нет данных платежа</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
</form>

<?php include '../footer.php'; ?>
