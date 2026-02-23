<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit();
}

$page_title = 'Панель администратора';

$mysqli = require '../config/database.php';
require '../queries/admin_queries.php';

$vybrannyj_id_sklada = isset($_GET['id_sklada']) ? intval($_GET['id_sklada']) : null;
$tekushchaya_stranitsa = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_na_stranitse = 8;

$sklady = getBceSklady($mysqli);
$obshchee_tovarov = getKolichestvoTovarov($mysqli, $vybrannyj_id_sklada);
$obshchee_stranits = ceil($obshchee_tovarov / $items_na_stranitse);


if ($tekushchaya_stranitsa < 1) $tekushchaya_stranitsa = 1;
if ($tekushchaya_stranitsa > $obshchee_stranits && $obshchee_stranits > 0) $tekushchaya_stranitsa = $obshchee_stranits;


$offset = ($tekushchaya_stranitsa - 1) * $items_na_stranitse;


$tovary = getBceTovary($mysqli, $vybrannyj_id_sklada, $items_na_stranitse, $offset);

include '../header.php';
?>
      <div class="page-body">
        <div class="card-body">
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Поступления товаров</h3>
                  <p class="text-secondary m-0">Всего документов: <?= $obshchee_tovarov ?> штук.</p>
                </div>
                <div class="col-md-auto col-sm-12">
                  <div class="ms-auto d-flex flex-wrap btn-list">
                    <div class="input-group input-group-flat w-auto">
                      <span class="input-group-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                          <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path>
                          <path d="M21 21l-6 -6"></path>
                        </svg>
                      </span>
                      <input id="advanced-table-search" type="text" class="form-control" autocomplete="off" placeholder="Поиск...">
                      <span class="input-group-text">
                      </span>
                    </div>
                      <div class="dropdown">
                              <a href="#" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php 
                                if ($vybrannyj_id_sklada) {
                                    $selected = array_filter($sklady, fn($w) => $w['id'] == $vybrannyj_id_sklada);
                                    $naimenovanie_sklada = !empty($selected) ? reset($selected)['naimenovanie'] : 'Склад';
                                    echo htmlspecialchars($naimenovanie_sklada);
                                } else {
                                    echo 'Склады';
                                }
                                ?>
                              </a>
                              <div class="dropdown-menu" style="">
                                <a class="dropdown-item" href="?">Склады</a>
                                <?php foreach ($sklady as $sklad): ?>
                                  <a class="dropdown-item" href="?id_sklada=<?= htmlspecialchars($sklad['id']) ?>"><?= htmlspecialchars($sklad['naimenovanie']) ?></a>
                                <?php endforeach; ?>
                              </div>
                            </div>
                    <a href="redaktirovanie.php" class="btn btn-primary">Создать</a>
                  </div>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>Номер</th>
                    <th>Дата</th>
                    <th>Поставщик</th>
                    <th>Ответственный</th>
                    <th>Цена</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($tovary)): ?>
                    <?php foreach ($tovary as $tovar): ?>
                      <tr>
                        <td><a href="prosmotr.php?id_tovara=<?= htmlspecialchars($tovar['id']) ?>" class="text-primary"><?= htmlspecialchars($tovar['id']) ?></a></td>
                        <td class="text-secondary"><?= htmlspecialchars($tovar['data_dokumenta']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($tovar['postavschik'] ?? 'N/A') ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($tovar['otvetstvennyj'] ?? 'N/A') ?></td>
                        <td class="text-secondary"><?= number_format($tovar['total_price'] ?? 0, 2, ',', ' ') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center text-secondary p-4">
                        Документы еще не добавлены. <a href="redaktirovanie.php">Добавьте первый документ</a>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if ($obshchee_stranits > 0): ?>
            <div class="card-footer">
              <div class="row g-2 justify-content-center justify-content-sm-between align-items-center">
                <div class="col-auto d-flex align-items-center">
                  <p class="m-0 text-secondary">
                    Показано <?= max(1, $offset + 1) ?> по <?= min($offset + $items_na_stranitse, $obshchee_tovarov) ?> из <?= $obshchee_tovarov ?> записей
                  </p>
                </div>
                <?php if ($obshchee_stranits > 1): ?>
                <div class="col-auto">
                  <ul class="pagination m-0 ms-auto">
                    <?php 
                  
                    $url_params = ($vybrannyj_id_sklada) ? "?id_sklada=" . htmlspecialchars($vybrannyj_id_sklada) . "&" : "?";
                    ?>
                    <li class="page-item <?= ($tekushchaya_stranitsa == 1) ? 'disabled' : '' ?>">
                      <a class="page-link" href="<?= $url_params ?>page=<?= max(1, $tekushchaya_stranitsa - 1) ?>" <?= ($tekushchaya_stranitsa == 1) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                          <path d="M15 6l-6 6l6 6"></path>
                        </svg>
                      </a>
                    </li>
                    <?php
                    
                    $startovaya_stranica = max(1, $tekushchaya_stranitsa - 2);
                    $konechnaya_stranica = min($obshchee_stranits, $tekushchaya_stranitsa + 2);
                    
                    for ($i = $startovaya_stranica; $i <= $konechnaya_stranica; $i++):
                    ?>
                      <li class="page-item <?= ($i == $tekushchaya_stranitsa) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $url_params ?>page=<?= $i ?>"><?= $i ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($tekushchaya_stranitsa == $obshchee_stranits) ? 'disabled' : '' ?>">
                      <a class="page-link" href="<?= $url_params ?>page=<?= min($obshchee_stranits, $tekushchaya_stranitsa + 1) ?>" <?= ($tekushchaya_stranitsa == $obshchee_stranits) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                          <path d="M9 6l6 6l-6 6"></path>
                        </svg>
                      </a>
                    </li>
                  </ul>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

<script>
document.getElementById('advanced-table-search').addEventListener('keyup', function() {
    const searchInput = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('.table tbody tr');
    
    tableRows.forEach(row => {
        
        if (row.querySelector('td[colspan]')) {
            return;
        }
        
        const cells = row.querySelectorAll('td');
        let found = false;
        
      
        cells.forEach(cell => {
            if (cell.textContent.toLowerCase().includes(searchInput)) {
                found = true;
            }
        });
        
        
        row.style.display = found ? '' : 'none';
    });
});
</script>

<?php include '../footer.php'; ?>
