<?php

session_start();


if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: /../log_in.php');
    exit();
}

$page_title = 'Остатки Товаров';

$mysqli = require '../config/database.php';
require '../queries/ostatki_tovarov_queries.php';


$sklad_filter = isset($_GET['sklad']) ? htmlspecialchars($_GET['sklad']) : null;
$tovar_filter = isset($_GET['tovar']) ? htmlspecialchars($_GET['tovar']) : null;


$summa_tovary = getSummaTovarov($mysqli);
$tovary = getVceTovary($mysqli, $sklad_filter, $tovar_filter);
$sklady = getUniqueSklady($mysqli);
$spisok_tovary = getUniqueTovary($mysqli);

include '../header.php';
?>
       <div class="container-fluid mt-5"> 
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Остатки Товаров</h3>
                  <p class="text-secondary m-0">Всего товаров: <?= $summa_tovary ?> штук.</p>
                </div>
                <div class="col-md-auto col-sm-12">
                  <div class="ms-auto d-flex flex-wrap btn-list">
                          <div class="dropdown">
                              <a href="#" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php 
                                if ($sklad_filter) {
                                    echo htmlspecialchars($sklad_filter);
                                } else {
                                    echo 'Все склады';
                                }
                                ?>
                              </a>
                              <div class="dropdown-menu">
                                <a class="dropdown-item" href="?<?= $tovar_filter ? 'tovar=' . urlencode($tovar_filter) : '' ?>">Все склады</a>
                                <?php foreach ($sklady as $sklad): ?>
                                  <a class="dropdown-item" href="?sklad=<?= urlencode($sklad) ?><?= $tovar_filter ? '&tovar=' . urlencode($tovar_filter) : '' ?>"><?= htmlspecialchars($sklad) ?></a>
                                <?php endforeach; ?>
                              </div>
                         </div>
                         <div class="dropdown">
                              <a href="#" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php 
                                if ($tovar_filter) {
                                    echo htmlspecialchars($tovar_filter);
                                } else {
                                    echo 'Все товары';
                                }
                                ?>
                              </a>
                              <div class="dropdown-menu">
                                <a class="dropdown-item" href="?<?= $sklad_filter ? 'sklad=' . urlencode($sklad_filter) : '' ?>">Все товары</a>
                                <?php foreach ($spisok_tovary as $tovar_item): ?>
                                  <a class="dropdown-item" href="?tovar=<?= urlencode($tovar_item) ?><?= $sklad_filter ? '&sklad=' . urlencode($sklad_filter) : '' ?>"><?= htmlspecialchars($tovar_item) ?></a>
                                <?php endforeach; ?>
                              </div>
                         </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="table-responsive">
                    <table class="w-100 border fs-4">
                        <thead>
                            <tr class="border border-dark">
                                <th class="border border-dark p-2 text-center fw-bold">№</th>
                                <th class="border border-dark p-2 text-center fw-bold">Товары</th>
                                <th class="border border-dark p-2 text-center fw-bold">Серия</th>
                                <th class="border border-dark p-2 text-center fw-bold">Остаток</th>
                                <th class="border border-dark p-2 text-center fw-bold">Склад</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($tovary)): ?>
                                <?php $row_num = 1; ?>
                                <?php foreach ($tovary as $tovar): ?>
                                    <tr class= "border border-dark">
                                        <td class="border border-dark p-2 text-center"><?= $row_num ?></td>
                                        <td class="border border-dark ps-3"><?= htmlspecialchars($tovar['naimenovanie_tovara'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($tovar['nomer'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($tovar['ostatok'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($tovar['sklad'] ?? '') ?></td>
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
        </div>
      

<?php include '../footer.php'; ?>