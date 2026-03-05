<?php


session_start();


if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$page_title = 'Сравнение остатков ветис и учётной системы';

$mysqli = require '../config/database.php';
require '../queries/sravnenie_ostatkov_vetis_i_uchyotnoj_sistemy.php';

$result = getSravnenieVsekhTovarov($mysqli);
$tovary = $result['success'] ? $result['tovary'] : [];

include '../header.php';
?>
      
        <div class="container-fluid mt-5"> 
             <div class="mb-3 pb-1">
                    <h2 class="fw-bolder">
                        Сравнение остатков ветис и учётной системы
                    </h2>
                </div>
          <div class="card">
           
                    <?php if (!empty($tovary)): ?>
                        <div class="accordion" id="accordion-default">
                            <?php foreach ($tovary as $index => $tovar): ?>
                                <div class="accordion-item">
                                    <div class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $index + 1 ?>" aria-expanded="false">
                                            <?= htmlspecialchars($tovar['id']) ?> - <?= htmlspecialchars($tovar['naimenovanie_tovara']) ?>
                                            <div class="accordion-button-toggle">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                                                    <path d="M6 9l6 6l6 -6"></path>
                                                </svg>
                                            </div>
                                        </button>
                                    </div>
                                    <div id="collapse-<?= $index + 1 ?>" class="accordion-collapse collapse" data-bs-parent="#accordion-default">
                                        <div class="accordion-body">
                                            <?php 
                                                $vetis_data = $tovar['vetis_data'];
                                                $noreg_unf_data = $tovar['noreg_unf_data'];
                                            ?>
                                            <table class="w-100 border fs-4">
                                                <thead>
                                                    <tr class="border border-dark">
                                                        <th class="border border-dark p-2 text-center fw-bold">№</th>
                                                        <th class="border border-dark p-2 text-center fw-bold">Название Ветис</th>
                                                        <th class="border border-dark p-2 text-center fw-bold">Название 1с</th>
                                                        <th class="border border-dark p-2 text-center fw-bold">Остаток Ветис</th>
                                                        <th class="border border-dark p-2 text-center fw-bold">Остаток 1С</th>
                                                        <th class="border border-dark p-2 text-center fw-bold">Разница</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($vetis_data) || !empty($noreg_unf_data)): ?>
                                                        <?php $row_num = 1; ?>
                                                        <?php 
                                                            $total_vetis = 0;
                                                            $total_1s = 0;
                                                            $vetis_count = count($vetis_data);
                                                            $noreg_count = count($noreg_unf_data);
                                                            $max_count = max($vetis_count, $noreg_count);
                                                        ?>
                                                        

                                                        <?php for ($i = 0; $i < $max_count; $i++): ?>
                                                            <tr class="border border-dark">
                                                                <td class="border border-dark p-2 text-center"><?= $row_num ?></td>
                                                                
                                                                
                                                                <?php if ($i < $vetis_count): ?>
                                                                    <td class="border border-dark ps-3"><?= htmlspecialchars($vetis_data[$i]['naimenovanie_tovara_vetis'] ?? '') ?></td>
                                                                    <td class="border border-dark p-2 text-center"><?= htmlspecialchars($noreg_unf_data[$i]['naimenovanie_tovara_1s'] ?? '') ?></td>
                                                                    <td class="border border-dark p-2 text-center"><?= htmlspecialchars($vetis_data[$i]['ostatok_vetis'] ?? '') ?></td>
                                                                    <td class="border border-dark p-2 text-center"><?= htmlspecialchars(($i < $noreg_count ? $noreg_unf_data[$i]['ostatok_1s'] : '') ?? '') ?></td>
                                                                <?php else: ?>
                                                                    
                                                                    <td class="border border-dark"></td>
                                                                    <td class="border border-dark p-2 text-center"><?= htmlspecialchars($noreg_unf_data[$i]['naimenovanie_tovara_1s'] ?? '') ?></td>
                                                                    <td class="border border-dark p-2 text-center"></td>
                                                                    <td class="border border-dark p-2 text-center"><?= htmlspecialchars($noreg_unf_data[$i]['ostatok_1s'] ?? '') ?></td>
                                                                <?php endif; ?>
                                                                
                                                                <td class="border border-dark p-2 text-center"></td>
                                                            </tr>
                                                            <?php 
                                                                
                                                                if ($i < $vetis_count) {
                                                                    $total_vetis += (float)($vetis_data[$i]['ostatok_vetis'] ?? 0);
                                                                }
                                                                if ($i < $noreg_count) {
                                                                    $total_1s += (float)($noreg_unf_data[$i]['ostatok_1s'] ?? 0);
                                                                }
                                                                $row_num++;
                                                            ?>
                                                        <?php endfor; ?>
                                                        
                                                        <tr class="border border-dark fw-bold">
                                                            <td class="border border-dark p-2 text-center"></td>
                                                            <td class="border border-dark p-2 text-center">Итого: </td>
                                                            <td class="border border-dark p-2 text-center"></td>
                                                            <td class="border border-dark p-2 text-center"><?= htmlspecialchars($total_vetis) ?></td>
                                                            <td class="border border-dark p-2 text-center"><?= htmlspecialchars($total_1s) ?></td>
                                                            <td class="border border-dark p-2 text-center"><?= htmlspecialchars($total_vetis-$total_1s) ?></td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="border border-dark p-2 text-center">Нет данных для сравнения</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            Товары не добавлены
                        </div>
                    <?php endif; ?>
                  </div>
            </div>
          
    
<?php include '../footer.php'; ?>

