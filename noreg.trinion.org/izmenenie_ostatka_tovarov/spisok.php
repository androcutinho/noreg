<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit();
}

$page_title = 'Изменение остатка товаров';

$mysqli = require '../config/database.php';
require '../queries/zakaz_pokupatelya_query.php';

$tekushchaya_stranitsa = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_na_stranitse = 8;


$kolichestvo_dok = getKolichestvoDok($mysqli);
$obshchee_stranits = ceil($kolichestvo_dok / $items_na_stranitse);

if ($tekushchaya_stranitsa < 1) $tekushchaya_stranitsa = 1;
if ($tekushchaya_stranitsa > $obshchee_stranits && $obshchee_stranits > 0) $tekushchaya_stranitsa = $obshchee_stranits;

$offset = ($tekushchaya_stranitsa - 1) * $items_na_stranitse;


$dok_octatka = getVceDok($mysqli, $items_na_stranitse, $offset);

include '../header.php';
?>
      <div class="page-body">
        <div class="container-fluid">
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Изменение остатка товаров</h3>
                  <p class="text-secondary m-0">Всего: <?= $kolichestvo_dok ?> штук.</p>
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
                    <a href="redaktirovanie.php" class="btn btn-primary">Создать</a>
                  </div>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>№</th>
                    <th>Дата</th>
                    <th>Поставщик</th>
                    <th>Организация</th>
                    <th>Ответственный</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($zakazy)): ?>
                    <?php foreach ($zakazy as $zakaz): ?>
                      <tr>
                        <td><a href="prosmotr.php?zakaz_id=<?= htmlspecialchars($zakaz['id']) ?>" class="text-primary"><?= htmlspecialchars($zakaz['nomer']) ?></a></td>
                        <td class="text-secondary"><?= htmlspecialchars($zakaz['data_dokumenta']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($zakaz['naimenovanie_postavschika'] ?? 'N/A') ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($zakaz['naimenovanie_organizacii'] ?? 'N/A') ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($zakaz['naimenovanie_otvetstvennogo'] ?? 'N/A') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center text-secondary p-4">
                        Заказы еще не добавлены. <a href="redaktirovanie.php">Добавьте первый заказ</a>
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
                  </p>
                </div>
                <?php if ($obshchee_stranits > 1): ?>
                <div class="col-auto">
                  <ul class="pagination m-0 ms-auto">
                    <li class="page-item <?= ($tekushchaya_stranitsa == 1) ? 'disabled' : '' ?>">
                      <a class="page-link" href="?page=<?= max(1, $tekushchaya_stranitsa - 1) ?>" <?= ($tekushchaya_stranitsa == 1) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
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
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($tekushchaya_stranitsa == $obshchee_stranits) ? 'disabled' : '' ?>">
                      <a class="page-link" href="?page=<?= min($obshchee_stranits, $tekushchaya_stranitsa + 1) ?>" <?= ($tekushchaya_stranitsa == $obshchee_stranits) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
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