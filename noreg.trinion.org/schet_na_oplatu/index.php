<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit();
}

// Get the type parameter (default to 'pokupatel')
$type = isset($_GET['type']) && $_GET['type'] === 'postavschik' ? 'postavschik' : 'pokupatel';
$type_label = ($type === 'postavschik') ? 'поставщика' : 'покупателя';
$page_title = 'Счета на оплату ' . $type_label;

$mysqli = require '../config/database.php';
require '../queries/schet_na_oplatu_query.php';

$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 8;

// Get total count of orders
$total_orders = getSchetovCount($mysqli, $type);
$total_pages = ceil($total_orders / $items_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $items_per_page;

// Get orders for current page
$schetov = getAllschetov($mysqli, $items_per_page, $offset, $type);

include '../header.php';
?>
      <div class="page-body">
        <div class="container-fluid">
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Счета на оплату <?= htmlspecialchars($type_label) ?></h3>
                  <p class="text-secondary m-0">Всего счетов: <?= $total_orders ?> штук.</p>
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
                    <a href="form.php" class="btn btn-primary">Создать</a>
                  </div>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>№ заказа</th>
                    <th>Дата</th>
                    <th>Поставщик</th>
                    <th>Организация</th>
                    <th>Ответственный</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($schetov)): ?>
                    <?php foreach ($schetov as $order): ?>
                      <tr>
                        <td><a href="schet.php?id=<?= htmlspecialchars($order['id']) ?>" class="text-primary"><?= htmlspecialchars($order['nomer']) ?></a></td>
                        <td class="text-secondary"><?= htmlspecialchars($order['data_dokumenta']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($order['vendor_name'] ?? 'N/A') ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($order['organization_name'] ?? 'N/A') ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($order['responsible_name'] ?? 'N/A') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center text-secondary p-4">
                        Заказы еще не добавлены. <a href="form.php">Добавьте первый заказ</a>
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
                  </p>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="col-auto">
                  <ul class="pagination m-0 ms-auto">
                    <li class="page-item <?= ($current_page == 1) ? 'disabled' : '' ?>">
                      <a class="page-link" href="?type=<?= htmlspecialchars($type) ?>&page=<?= max(1, $current_page - 1) ?>" <?= ($current_page == 1) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
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
                        <a class="page-link" href="?type=<?= htmlspecialchars($type) ?>&page=<?= $i ?>"><?= $i ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($current_page == $total_pages) ? 'disabled' : '' ?>">
                      <a class="page-link" href="?type=<?= htmlspecialchars($type) ?>&page=<?= min($total_pages, $current_page + 1) ?>" <?= ($current_page == $total_pages) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
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