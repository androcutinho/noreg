<?php

session_start();


if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: /../log_in.php');
    exit();
}

$page_title = 'Документы по контрагенту';

$mysqli = require '../config/database.php';
require '../queries/dokumenty_po_kontragentu_queries.php';



$kontragent_filter = isset($_GET['kontragent']) ? $_GET['kontragent'] : null;


$spisok_kontragenty = getUniqueKontragenty($mysqli);
$dokumenty = [];
if ($kontragent_filter) {
    $dokumenty = getDokumenty($mysqli, $kontragent_filter);
}

include '../header.php';
?>
       <div class="container-fluid mt-5"> 
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Документы по контрагенту</h3>
                </div>
                <div class="col-md-auto col-sm-12">
                  <div class="ms-auto d-flex flex-wrap btn-list gap-2">
                    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" id="filterForm">
                         <div class="dropdown">
                              <a href="#" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php 
                                if ($kontragent_filter) {
                                    echo htmlspecialchars($kontragent_filter);
                                } else {
                                    echo 'Выберите контрагент';
                                }
                                ?>
                              </a>
                              <div class="dropdown-menu">
                                <a class="dropdown-item" href="?"></a>
                                <?php foreach ($spisok_kontragenty as $kontragent_item): ?>
                                  <a class="dropdown-item" href="?kontragent=<?= urlencode($kontragent_item) ?>"><?= htmlspecialchars($kontragent_item) ?></a>
                                <?php endforeach; ?>
                              </div>
                         </div>
                    </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php if ($kontragent_filter): ?>
            <div class="table-responsive">
                    <table class="w-100 border fs-4">
                        <thead>
                            <tr class="border border-dark">
                                <th class="border border-dark p-2 text-center fw-bold">№</th>
                                <th class="border border-dark p-2 text-center fw-bold">Конграгент</th>
                                <th class="border border-dark p-2 text-center fw-bold">Документ</th>
                                <th class="border border-dark p-2 text-center fw-bold">Сумма </th>
                                <th class="border border-dark p-2 text-center fw-bold">Утвержден </th>
                                <th class="border border-dark p-2 text-center fw-bold">Закрыт</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($dokumenty)): ?>
                                <?php $row_num = 1; ?>
                                <?php foreach ($dokumenty as $dokument): ?>
                                    <tr class= "border border-dark">
                                        <td class="border border-dark p-2 text-center"><?= $row_num ?></td>
                                        <td class="border border-dark ps-3"><?= htmlspecialchars($dokument['kontragent'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= ($dokument['tip_dokumenta'] !== null ? htmlspecialchars($dokument['tip_dokumenta']) : '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= ($dokument['total_summa'] !== null ? htmlspecialchars($dokument['total_summa']) : '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= $dokument['utverzhden'] ? 'Да' : 'Нет' ?></td>
                                        <td class="border border-dark p-2 text-center"><?= $doc['zakryt'] ? 'Да' : 'Нет' ?></td>
                                    </tr>
                                    <?php $row_num++; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class=" border border-dark p-3 text-center">Документы не добавлены</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
          </div>
            <?php endif; ?>
        </div>
      

<?php include '../footer.php'; ?>