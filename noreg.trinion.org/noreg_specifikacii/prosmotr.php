<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: spisok.php');
    exit;
}

$mysqli = require '../config/database.php';

require '../queries/noreg_specifikacii_k_dogovoru_queries.php';
require '../queries/date_helpers.php';

$order_type_text = (isset($spec_info['dlya_zakaza_postavshiku']) && $spec_info['dlya_zakaza_postavshiku']) ? 'поставщику' : 'покупателя';

if (!$spec_info) {
    $page_title = 'Ошибка';
    include '../header.php';
    echo '<div class="alert alert-danger">Спецификация не найдена.</div>';
    include '../footer.php';
    exit;
}

$page_title = 'Спецификации к заказу ' . htmlspecialchars($spec_info['nomer_dogovora']);
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
            <?php if (!$spec_info['utverzhden']): ?>
            <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta('utverzhden', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 6l7 7l-4 4"></path>
                                <path d="M5.828 18.172a2.828 2.828 0 0 0 4 0l10.586 -10.586a2 2 0 0 0 0 -2.829l-1.171 -1.171a2 2 0 0 0 -2.829 0l-10.586 10.586a2.828 2.828 0 0 0 0 4z"></path>
                                <path d="M4 20l1.768 -1.768"></path>
                              </svg>
                            Утвердить
                        </button>
            <?php endif; ?>
                        <?php if ($spec_info['utverzhden']): ?>
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
                        <?php if (!$spec_info['zakryt']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta('zakryt', true);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M10.507 10.498l-1.507 1.502v3h3l1.493 -1.498m2 -2.01l4.89 -4.907a2.1 2.1 0 0 0 -2.97 -2.97l-4.913 4.896"></path>
                                <path d="M16 5l3 3"></path>
                                <path d="M3 3l18 18"></path>
                              </svg>
                            Закрить
                        </button>
                        <?php endif; ?>
                        <?php if ($spec_info['zakryt']): ?>
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
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены?')) window.location.href='redaktirovanie.php?id=<?= htmlspecialchars($_GET['id']) ?>&action=delete';">
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
             <div style="font-family: 'Times New Roman', serif; line-height: 1.6; color: black; font-weight: bold; text-align: center; white-space: pre-wrap;">
                <?php
                
                $preamble = "Спецификация №" . htmlspecialchars($spec_info['nomer_specifikacii']) . "\n\n";
                $preamble .= "к заказу " . $order_type_text . " № " . htmlspecialchars($spec_info['nomer_dogovora']) . " от " . formatDateRussian($spec_info['data_dogovora'], $months_ru_simple) . "\n\n";
                
                echo $preamble;
                ?>
            </div>
            <div style="position: absolute; right: 0px; top: 80px;">
                        <?php if ($spec_info['utverzhden']): ?>
                            <div class="ribbon bg-primary">Утвержден</div>
                        <?php else: ?>
                            <div class="ribbon bg-secondary">Черновик</div>
                        <?php endif; ?>
                    </div>
            <div style="font-family: 'Times New Roman', serif; display: flex; justify-content: space-between; margin-bottom: 20px; margin-top: 20px;">
                <div style="font-weight: bold;">
                    <?= htmlspecialchars($spec_info['gorod']) ?>
                </div>
                <div style="font-weight: bold;">
                    <?= formatDateFormalRussian($spec_info['data_dogovora']) ?>
                </div>
            </div>
            <div style="font-family: 'Times New Roman', serif; line-height: 1.6; white-space: pre-wrap; color: black; text-align: justify;">
                <?php
                
                $preamble = "";
                $preamble .= htmlspecialchars($spec_info['org_polnoe_naimenovanie']) . ", " . htmlspecialchars($spec_info['org_sokrashchyonnoe_naimenovanie']) . ", ОГРН " . htmlspecialchars($spec_info['org_ogrn']) . ", ИНН " . htmlspecialchars($spec_info['org_inn']) . ", КПП " . htmlspecialchars($spec_info['org_kpp']) . ", адрес: " . "";
                $preamble .= "" . htmlspecialchars($spec_info['org_adress']) . ", именуемое в дальнейшем «Поставщик», в лице " . htmlspecialchars($spec_info['org_v_lice_dlya_documentov']) . ", действующего на основании Устава, с одной стороны, и " . htmlspecialchars($spec_info['kon_polnoe_naimenovanie']) . ", " . htmlspecialchars($spec_info['kon_sokrashchyonnoe_naimenovanie']) . "ОГРН " . htmlspecialchars($spec_info['kon_ogrn']) . ", ИНН " . htmlspecialchars($spec_info['kon_inn']) . ", ";
                $preamble .=  ", КПП " . htmlspecialchars($spec_info['kon_kpp']) . ", адрес: " . htmlspecialchars($spec_info['kon_adress']) . ", именуемое в дальнейшем «Покупатель», в лице " . htmlspecialchars($spec_info['kon_v_lice_dlya_documentov']) . " действующего на основании Устава, с другой стороны, вместе именуемые в дальнейшем «Стороны», действуя в соответствии со статьями 1 и 2 договора поставки № " . htmlspecialchars($spec_info['nomer_dogovora']) . ", заключённого между Сторонами " . formatDateRussian($spec_info['data_dogovora'], $months_ru_simple) ." (далее - Договор), согласовали настоящую спецификацию в том, что Поставщик обязуется передать, а Покупатель принять и оплатить Товар на следующих условиях:";
                
                echo $preamble;
                ?>
            </div>
            <table class="table table-bordered table-responsive" style="margin-top: 30px;">
                <thead>
                    <tr>
                        <th style="width: 3%">N°</th>
                        <th class="text-center" style="width: 32%">Наименование</th>
                        <th style="width: 15%">Планируемая дата поставки</th>
                        <th class="text-center" style="width: 10%">Количество</th>
                        <th class="text-center" style="width: 5%">Ед.</th>
                        <th class="text-center" style="width: 15%">Цена</th>
                        <th class="text-center" style="width: 15%">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $obshchaya_summa = 0;
                $n = 1;
                
                foreach ($items as $mesyats => $gruppa) {
                    $obshchee_grupp = 0;
                    $rowspan = count($gruppa);
                    $pervyj = true;
                    foreach ($gruppa as $item) {
                        $obshchee_grupp += $item['kolichestvo'];
                        echo '<tr>';
                        echo '<td>' . $n++ . '</td>';
                        echo '<td>' . htmlspecialchars($item['naimenovanie_tovara']) . '</td>';
                        
                        if ($pervyj) {
                            $chasti_mesyatsa = explode(' ', $mesyats);
                            $mesyats_ru = isset($mesyats_ru_zaglavnyj[$chasti_mesyatsa[0]]) ? $mesyats_ru_zaglavnyj[$chasti_mesyatsa[0]] . ' ' . $chasti_mesyatsa[1] . ' года' : $mesyats;
                            echo '<td rowspan="' . $rowspan . '" class="align-middle text-center fw-bold">' . htmlspecialchars($mesyats_ru) . '</td>';
                            $pervyj = false;
                        }
                        echo '<td class="text-center">' . htmlspecialchars(number_format($item['kolichestvo'], 2, ',', ' ')) . '</td>';
                        echo '<td class="text-center">' . htmlspecialchars($item['naimenovanie_serii']) . '</td>';
                        $cena = number_format($item['cena'], 2, ',', ' ');
                        $stavka_nds = isset($item['stavka']) ? $item['stavka'] : 0;
                        $summa_nds = $item['cena'] * $stavka_nds / (100 + $stavka_nds); 
                        $summa_nds_fmt = number_format($summa_nds, 2, ',', ' ');
                        echo '<td class="text-center">' .
                            htmlspecialchars($cena) .
                            '<br><small class="text-muted">НДС ' . htmlspecialchars($stavka_nds) . ' % - ' . htmlspecialchars($summa_nds_fmt) . '</small>' .
                        '</td>';
                        $kolichestvo = number_format($item['kolichestvo'], 2, ',', ' ');
                        $stavka_nds = isset($item['stavka']) ? $item['stavka'] : 0;
                        $vat_value = isset($item['summa_nds']) ? $item['summa_nds'] : 0;
                        $vat_value_fmt = number_format($vat_value, 2, ',', ' ');
                        echo '<td class="text-center">' .
                            htmlspecialchars($kolichestvo) .
                            '<br><small class="text-muted">НДС ' . htmlspecialchars($stavka_nds) . ' % - ' . htmlspecialchars($vat_value_fmt) . '</small>' .
                        '</td>';
                        echo '</tr>';
                    }
                    $kolichestvo_gruppa = 0;
                    foreach ($gruppa as $item) {
                        $kolichestvo_gruppa += $item['kolichestvo'];
                    }
                    
                    $gruppa_summa_nds = 0;
                    foreach ($gruppa as $item) {
                        $gruppa_summa_nds += isset($item['summa_nds']) ? $item['summa_nds'] : 0;
                    }
                    $stavki_grupp = $obshchee_grupp > 0 ? ($gruppa_summa_nds / $obshchee_grupp * 100) : 0;
                    echo '<tr class="fw-bold">'
                        . '<td></td>'
                        . '<td>Всего</td>'
                        . '<td></td>'
                        . '<td class="text-center">' . number_format($kolichestvo_gruppa, 2, ',', ' ') . '</td>'
                        . str_repeat('<td></td>', 2)
                        . '<td class="text-center">' . number_format($obshchee_grupp, 2, ',', ' ') . '<br>' .
                        '<small class="text-muted">НДС ' . number_format($stavki_grupp, 2, ',', ' ') . ' % - ' . number_format($gruppa_summa_nds, 2, ',', ' ') . '</small></td></tr>';
                    $obshchaya_summa += $obshchee_grupp;
                }
               
                $sum_per = 0;
                $obshchee_kolichestvo = 0;
                foreach ($items as $mesyats => $gruppa) {
                    foreach ($gruppa as $item) {
                        $sum_per += isset($item['summa_nds']) ? $item['summa_nds'] : 0;
                        $obshchee_kolichestvo += $item['kolichestvo'];
                    }
                }
                $stavka = $obshchaya_summa > 0 ? ($sum_per / $obshchaya_summa * 100) : 0;
                echo '<tr class="fw-bold">'
                    . '<td class="text-center" colspan="3">ИТОГО:</td>'
                    . '<td class="text-center">' . number_format($obshchee_kolichestvo, 2, ',', ' ') . '</td>'
                    . str_repeat('<td></td>', 2)
                    . '<td class="text-center">' . number_format($obshchaya_summa, 2, ',', ' ') . '<br>' .
                    '<small class="text-muted">НДС ' . number_format($stavka, 2, ',', ' ') . ' % - ' . number_format($sum_per, 2, ',', ' ') . '</small></td></tr>';
                ?>
                </tbody>
            </table>
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <td style="width: 20%; font-weight: bold;">Условия отгрузки:</td>
                        <td><div style=" word-wrap: break-word;"><?= $spec_info['usloviya_otgruzki'] ?? '' ?></div></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Условия оплаты:</td>
                            <td><div style=" word-wrap: break-word;"><?= $spec_info['usloviya_oplaty'] ?? '' ?></div></td>
                        </tr>
                    <tr>
                        <td style="font-weight: bold;">Иные условия:</td>
                        <td><div style=" word-wrap: break-word;"><?= $spec_info['inye_usloviya'] ?? '' ?></div></td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top: 40px; font-family: 'Times New Roman', serif;">
                <div style="display: flex; justify-content: space-between;">
                    <div style="width: 45%; text-align: center;">
                        <div style="border-bottom: 1px solid #000; min-height: 80px; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 10px;">
                            <?php if ($spec_info && $spec_info['sotrudnik_dolgnost']): ?>
                                <div>
                                    <?= htmlspecialchars($spec_info['sotrudnik_dolgnost']) ?> <?= htmlspecialchars($spec_info['org_sokrashchyonnoe_naimenovanie'] ?? '') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 5px;">
                            <?php if ($spec_info && $spec_info['sotrudnik_familiya']): ?>
                                <?= htmlspecialchars($spec_info['sotrudnik_familiya'] ?? '') ?> <?= htmlspecialchars($spec_info['sotrudnik_imya'] ?? '') ?> <?= htmlspecialchars($spec_info['sotrudnik_otchestvo'] ?? '') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="width: 45%; text-align: center;">
                        <div style="border-bottom: 1px solid #000; min-height: 80px; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 10px;">
                            <?php if ($spec_info && $spec_info['podpisant_postavshchika_dolzhnost']): ?>
                                <div>
                                    <?= htmlspecialchars($spec_info['podpisant_postavshchika_dolzhnost']) ?> <?= htmlspecialchars($spec_info['kon_sokrashchyonnoe_naimenovanie'] ?? '') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 5px;">
                            <?php if ($spec_info && $spec_info['podpisant_postavshchika_fio']): ?>
                                <?= htmlspecialchars($spec_info['podpisant_postavshchika_fio'] ?? '') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function redaktirovatDokument() {
    const isClosed = <?= json_encode((bool)$spec_info['zakryt']) ?>;
    if (isClosed) {
        alert('Этот документ закрыт и не может быть отредактирован.');
        return;
    }
    window.location.href = 'redaktirovanie.php?id=<?= json_encode($spec_id) ?>';
}

function ObnovitPoleDokumenta(fieldName, value) {
    const documentId = <?= json_encode($spec_id) ?>;
    const tableName = 'noreg_specifikacii_k_zakazam ';
    
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

<?php include '../footer.php'; ?>
