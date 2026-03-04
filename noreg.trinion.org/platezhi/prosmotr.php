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

$platezh = fetchPaymentForDisplay($mysqli, $id);

if (!$platezh) {
    die("Платеж не найден.");
}


$payer_info = fetchContractorInfo($mysqli, $platezh['id_kontragenti_platelshik']);
$recipient_info = fetchContractorInfo($mysqli, $platezh['id_kontragenti_poluchatel']);
$display_payer_info = $platezh['iskhodyashchij'] ? $recipient_info : $payer_info;
$display_payer_id = $platezh['iskhodyashchij'] ? $platezh['id_kontragenti_poluchatel'] : $platezh['id_kontragenti_platelshik'];
$bank_details = fetchPaymentBankDetails($mysqli, $display_payer_id);
$line_items = fetchPaymentLineItemsForDisplay($mysqli, $platezh['nomer']);
$data_platezha = new DateTime($platezh['data_dokumenta']);
$formatted_date = $data_platezha->format('d.m.Y H:i');

$payment_type = $platezh['vhodyashchij'] ? 'Входящий платеж' : 'Исходящий платеж';
$page_title= $platezh['vhodyashchij'] ? 'Входящий платеж' : 'Исходящий платеж';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    deletePaymentDocument($mysqli, $id);
    header('Location: spisok.php');
    exit;
}

include '../header.php';
?>

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
            <a href="redaktirovanie.php?id=<?= htmlspecialchars($id) ?>" class="btn btn-primary">
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
    <div class="text-center mb-4">
        <h4 class="text-secondary mb-1">
            <?= isset($display_payer_info['naimenovanie']) ? htmlspecialchars($display_payer_info['naimenovanie']) : 'Компания' ?>
        </h4>
    </div>

    <div class="text-center mb-4">
        <h1 class="fs-1 mb-0"><?= $payment_type ?></h1>
    </div>

    
    <div class="row mb-4">
        
        <div class="col-md-8">
            <div class="border-bottom pb-3 mb-3">
                <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                    <span class="text-secondary medium">Номер вх. платежа  </span>
                    <strong class="fs-4"><?= htmlspecialchars($platezh['id']) ?></strong>
                </div>
                <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                    <span class="text-secondary medium me-5">Дата платежа</span>
                    <strong class="fs-4"><?= htmlspecialchars($formatted_date) ?></strong>
                </div>
                <div class="d-flex align-items-center gap-4 py-2 border-bottom">
                    <span class="text-secondary medium">Номер документа</span>
                    <strong class="fs-4"><?= htmlspecialchars($platezh['nomer']) ?></strong>
                </div>
                <div class="d-flex align-items-center gap-3 py-2">
                    <span class="text-secondary medium">Платежная система</span>
                    <strong class="fs-4">Банковский перевод</strong>
                </div>
            </div>
        </div>

    
        <div class="col-md-4">
            <div class="bg-success text-white p-5 text-center rounded h-100">
                <div class="fs-4 mb-4">Итого получено</div>
                <div class="fs-1 fw-bolder">
                    <?= number_format(floatval($platezh['summa']), 2, '.', ' ') ?>
                </div>
            </div>
        </div>
    </div>

   
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="pe-2">
                <h5 class="mb-4 fs-3 text-dark">Плательщик</h5>
                <?php if ($display_payer_info): ?>
                    <div class="mb-2">
                        <strong class="fw-2"><?= htmlspecialchars($display_payer_info['naimenovanie']) ?></strong>
                    </div>
                    <div class="text-secondary fs-3 lh-lg">
                        <div>Адрес:</div>
                        <?php if (!empty($display_payer_info['address_fact'])): ?>
                            <div class="mb-3"><?= htmlspecialchars($display_payer_info['address_fact']) ?></div>
                        <?php else: ?>
                            <div class="mb-3">—</div>
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
                <h5 class="mb-4 fs-3 text-dark">Банковские реквизиты плательщика</h5>
                <?php if ($bank_details): ?>
                    <div class="text-secondary fs-3 lh-lg">
                        <div>
                            <strong>Наименование банка:</strong>
                            <div><?= htmlspecialchars($bank_details['naimenovanie_banka'] ?? '—') ?></div>
                        </div>
                        <div class="mt-2">
                            <strong>БИК:</strong>
                            <div><?= htmlspecialchars($bank_details['BIK_banka'] ?? '—') ?></div>
                        </div>
                        <div class="mt-2">
                            <strong>Рассчетный счет:</strong>
                            <div><?= htmlspecialchars($bank_details['nomer_korrespondentskogo_scheta'] ?? '—') ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    
    <div class="mt-5">
        <h4 class="mb-3 fs-3 text-dark">Получено за</h4>
        
        <div class="card">
            <div class="table-responsive">
                <table class="w-100 border border-collapse fs-3">
                    <thead>
                        <tr class="bg-secondary text-dark border border-dark">
                            <th class="text-light fw-bolder border border-dark p-2 text-center">Номер счета</th>
                            <th class="text-light fw-bolder border border-dark p-2 text-center">Дата</th>
                            <th class="text-light fw-bolder border border-dark p-2 text-center">Сумма</th>
                            <th class="text-light fw-bolder border border-dark p-2 text-center">Оплачено</th>
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
                                <tr class="border border-dark">
                                    <td class="border border-dark text-center"><a href="../schet_na_oplatu/prosmotr.php?id=<?= htmlspecialchars($item['id_dokumenta']) ?>"><?= htmlspecialchars($item['id_dokumenta']) ?></a></td>
                                    <td class="border border-dark text-center"><?= htmlspecialchars($formatted_item_date) ?></td>
                                    <td class="border border-dark p-2 text-center"><?= number_format(floatval($item['summa']), 2, '.', ' ') ?></td>
                                    <td class="border border-dark p-2 text-center"><strong><?= number_format($item_total, 2, '.', ' ') ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="border border-dark">
                                <td colspan="4" class="border border-dark p-2 text-center text-light">Нет данных платежа</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        </div>
    </div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
</form>

<?php include '../footer.php'; ?>
