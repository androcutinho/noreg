<?php

session_start();


if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: /../log_in.php');
    exit();
}

$page_title = 'Движение товара';

$mysqli = require '../config/database.php';
require '../queries/dvizhenie_tovarov_queries.php';



$tovar_filter = isset($_GET['tovar']) ? htmlspecialchars($_GET['tovar']) : null;
$data_s = isset($_GET['data_s']) && !empty($_GET['data_s']) ? $_GET['data_s'] : null;
$data_do = isset($_GET['data_do']) && !empty($_GET['data_do']) ? $_GET['data_do'] : null;

$spisok_tovary = getUniqueTovary($mysqli);
$dokumenty = [];
if ($tovar_filter) {
    $dokumenty = getDokumenty($mysqli, $tovar_filter, $data_s, $data_do);
    calculateItogo($dokumenty, $mysqli);
}

include '../header.php';
?>
       <div class="container-fluid mt-5"> 
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Движение товара</h3>
                </div>
                <div class="col-md-auto col-sm-12">
                  <div class="ms-auto d-flex flex-wrap btn-list gap-2">
                    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" id="filterForm">
                          <div class="d-flex align-items-center gap-2">
                              <label for="data_s" class="form-label mb-0">С:</label>
                              <input type="date" id="data_s" name="data_s" class="form-control" style="width: auto;" value="<?= $data_s ? htmlspecialchars($data_s) : '' ?>">
                          </div>
                          <div class="d-flex align-items-center gap-2">
                              <label for="data_do" class="form-label mb-0">До:</label>
                              <input type="date" id="data_do" name="data_do" class="form-control" style="width: auto;" value="<?= $data_do ? htmlspecialchars($data_do) : '' ?>">
                          </div>
                         <div class="dropdown">
                              <a href="#" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php 
                                if ($tovar_filter) {
                                    echo htmlspecialchars($tovar_filter);
                                } else {
                                    echo 'Выберите товар';
                                }
                                ?>
                              </a>
                              <div class="dropdown-menu">
                                <a class="dropdown-item" href="?<?= $data_s ? 'data_s=' . urlencode($data_s) : '' ?><?= $data_do ? ($data_s ? '&' : '?') . 'data_do=' . urlencode($data_do) : '' ?>"></a>
                                <?php foreach ($spisok_tovary as $tovar_item): ?>
                                  <a class="dropdown-item" href="?tovar=<?= urlencode($tovar_item) ?><?= $data_s ? '&data_s=' . urlencode($data_s) : '' ?><?= $data_do ? '&data_do=' . urlencode($data_do) : '' ?>"><?= htmlspecialchars($tovar_item) ?></a>
                                <?php endforeach; ?>
                              </div>
                         </div>
                    </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php if ($tovar_filter): ?>
            <div class="table-responsive">
                    <table class="w-100 border fs-4">
                        <thead>
                            <tr class="border border-dark">
                                <th class="border border-dark p-2 text-center fw-bold">№</th>
                                <th class="border border-dark p-2 text-center fw-bold">Документ</th>
                                <th class="border border-dark p-2 text-center fw-bold">Количество плюс</th>
                                <th class="border border-dark p-2 text-center fw-bold">Количество минус</th>
                                <th class="border border-dark p-2 text-center fw-bold">Склад</th>
                                <th class="border border-dark p-2 text-center fw-bold">Итого</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($dokumenty)): ?>
                                <?php $row_num = 1; ?>
                                <?php foreach ($dokumenty as $dokument): ?>
                                    <tr class= "border border-dark">
                                        <td class="border border-dark p-2 text-center"><?= $row_num ?></td>
                                        <td class="border border-dark ps-3"><?= htmlspecialchars($dokument['tip_dokumenta'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= ($dokument['plius'] !== null ? htmlspecialchars($dokument['plius']) : '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= ($dokument['minus'] !== null ? htmlspecialchars($dokument['minus']) : '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($dokument['sklad'] ?? '') ?></td>
                                        <td class="border border-dark p-2 text-center"><?= htmlspecialchars($dokument['itogo'] ?? '') ?></td>
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
            <?php endif; ?>
        </div>
      

<?php include '../footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dataSInput = document.getElementById('data_s');
    const dataDoInput = document.getElementById('data_do');
    
    function navigateWithFilters() {

        const params = new URLSearchParams(window.location.search);
        
    
        if (dataSInput.value) {
            params.set('data_s', dataSInput.value);
        } else {
            params.delete('data_s');
        }
        
        if (dataDoInput.value) {
            params.set('data_do', dataDoInput.value);
        } else {
            params.delete('data_do');
        }
        
        window.location.href = '?' + params.toString();
    }
    
    dataSInput.addEventListener('change', navigateWithFilters);
    dataDoInput.addEventListener('change', navigateWithFilters);
});
</script>