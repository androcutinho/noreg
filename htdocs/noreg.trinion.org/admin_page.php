<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit();
}

$page_title = 'Панель администратора';

$mysqli = require 'config/database.php';
require 'queries/admin_queries.php';

$selected_warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : null;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 8;

$warehouses = fetchAllWarehouses($mysqli);

// Get total count of products
$total_products = getProductsCount($mysqli, $selected_warehouse_id);
$total_pages = ceil($total_products / $items_per_page);


if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;


$offset = ($current_page - 1) * $items_per_page;


$products = fetchAllProducts($mysqli, $selected_warehouse_id, $items_per_page, $offset);

include 'header.php';
?>
      <div class="page-body">
        <div class="container-xl">
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Поступления товаров</h3>
                  <p class="text-secondary m-0">Всего документов: <?= $total_products ?> штук.</p>
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
                                if ($selected_warehouse_id) {
                                    $selected = array_filter($warehouses, fn($w) => $w['id'] == $selected_warehouse_id);
                                    $warehouse_name = !empty($selected) ? reset($selected)['naimenovanie'] : 'Склад';
                                    echo htmlspecialchars($warehouse_name);
                                } else {
                                    echo 'Склады';
                                }
                                ?>
                              </a>
                              <div class="dropdown-menu" style="">
                                <a class="dropdown-item" href="?">Склады</a>
                                <?php foreach ($warehouses as $warehouse): ?>
                                  <a class="dropdown-item" href="?warehouse_id=<?= htmlspecialchars($warehouse['id']) ?>"><?= htmlspecialchars($warehouse['naimenovanie']) ?></a>
                                <?php endforeach; ?>
                              </div>
                            </div>
                    <a href="add_postupleniye_tovara.php" class="btn btn-primary">Создать</a>
                  </div>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>НОМЕР</th>
                    <th>ДАТА</th>
                    <th>ПОСТАВЩИК</th>
                    <th>ОТВЕТСТВЕННЫЙ</th>
                    <th>ЦЕНА</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                      <tr>
                        <td><a href="view_product_details.php?product_id=<?= htmlspecialchars($product['id']) ?>" class="text-primary"><?= htmlspecialchars($product['id']) ?></a></td>
                        <td class="text-secondary"><?= htmlspecialchars($product['data_dokumenta']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($product['vendor'] ?? 'N/A') ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($product['responsible'] ?? 'N/A') ?></td>
                        <td class="text-secondary"><?= number_format($product['total_price'] ?? 0, 2, ',', ' ') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center text-secondary p-4">
                        Документы еще не добавлены. <a href="add_product.php">Добавьте первый документ</a>
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
                    Показано <?= max(1, $offset + 1) ?> по <?= min($offset + $items_per_page, $total_products) ?> из <?= $total_products ?> записей
                  </p>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="col-auto">
                  <ul class="pagination m-0 ms-auto">
                    <?php 
                    // Build base URL parameters
                    $url_params = ($selected_warehouse_id) ? "?warehouse_id=" . htmlspecialchars($selected_warehouse_id) . "&" : "?";
                    ?>
                    <li class="page-item <?= ($current_page == 1) ? 'disabled' : '' ?>">
                      <a class="page-link" href="<?= $url_params ?>page=<?= max(1, $current_page - 1) ?>" <?= ($current_page == 1) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                          <path d="M15 6l-6 6l6 6"></path>
                        </svg>
                      </a>
                    </li>
                    <?php
                    // Calculate page range to display
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

<?php include 'footer.php'; ?>