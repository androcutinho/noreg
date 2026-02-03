<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit();
}

$page_title = 'Список поставщиков';

$mysqli = require 'config/database.php';
require 'queries/spisok_postavshchikov_queries.php';

$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 8;

$total_postavshchiki = getPostavshchikiCount($mysqli, '');
$total_pages = ceil($total_postavshchiki / $items_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $items_per_page;

$postavshchiki = fetchAllPostavshchiki($mysqli, '', $items_per_page, $offset);

include 'header.php';
?>
      <div class="page-body">
        <div class="container-fluid">
          <div style="text-align: right; margin-bottom: 10px;">
            <a href="postavshchik.php" class="btn btn-primary">
                Добавить
            </a>
        </div>
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Список поставщиков</h3>
                  <p class="text-secondary m-0">Всего поставщиков: <?= $total_postavshchiki ?> штук.</p>
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
                      <input id="postavshchiki-table-search" type="text" class="form-control" autocomplete="off" placeholder="Поиск...">
                      <span class="input-group-text">
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>ИД</th>
                    <th>Название</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($postavshchiki)): ?>
                    <?php foreach ($postavshchiki as $postavshchik): ?>
                      <tr>
                        <td><?= htmlspecialchars($postavshchik['id']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($postavshchik['naimenovanie']) ?></td>
                        <td class="text-secondary"><a href="postavshchik.php?postavshchik_id=<?= $postavshchik['id'] ?>" class="btn btn-sm btn-primary">Редактировать</a></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="3" class="text-center text-secondary p-4">
                        Поставщики еще не добавлены.
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if ($total_pages > 0): ?>
            <div class="card-footer">
              <div class="row g-2 justify-content-center justify-content-sm-between align-items-center">
                <div class="col-auto d-flex align-items-center">
                  <p class="m-0 text-secondary">
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="col-auto">
                  <ul class="pagination m-0 ms-auto">
                    <?php 
                    $url_params = "?";
                    ?>
                    <li class="page-item <?= ($current_page == 1) ? 'disabled' : '' ?>">
                      <a class="page-link" href="<?= $url_params ?>page=<?= max(1, $current_page - 1) ?>" <?= ($current_page == 1) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                          <path d="M15 6l-6 6l6 6"></path>
                        </svg>
                      </a>
                    </li>
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                      <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $url_params ?>page=<?= $i ?>"><?= $i ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($current_page == $total_pages) ? 'disabled' : '' ?>">
                      <a class="page-link" href="<?= $url_params ?>page=<?= min($total_pages, $current_page + 1) ?>" <?= ($current_page == $total_pages) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
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

<?php include 'footer.php'; ?>

<script>
document.getElementById('postavshchiki-table-search').addEventListener('keyup', function() {
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
