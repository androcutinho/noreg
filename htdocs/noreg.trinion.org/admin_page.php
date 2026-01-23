<?php

session_start();

$page_title = 'Панель администратора - Продукты';

$mysqli = require 'database.php';

// Fetch all products
$sql = "SELECT 
    pt.id,
    pt.data_dokumenta,
    ps.naimenovanie as vendor,
    u.user_name as responsible,
    SUM(sd.cena_postupleniya * sd.kolichestvo_postupleniya) as total_price
FROM postupleniya_tovarov pt
LEFT JOIN postavshchiki ps ON pt.id_postavshchika = ps.id
LEFT JOIN users u ON pt.id_otvetstvennyj = u.user_id
LEFT JOIN stroki_dokumentov sd ON pt.id = sd.id_dokumenta
GROUP BY pt.id
ORDER BY pt.id DESC";
$result = $mysqli->query($sql);
$products = array();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

include 'header.php';
?>
      <div class="page-body">
        <div class="container-xl">
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Поступления товаров</h3>
                  <p class="text-secondary m-0">Всего документов: <?= count($products) ?> штук.</p>
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
                        <kbd>ctrl + K</kbd>
                      </span>
                    </div>
                    <a href="#" class="btn btn-icon" aria-label="Button">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                        <path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                        <path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                        <path d="M19 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                      </svg>
                    </a>
                    <a href="add_product.php" class="btn btn-primary">Создать</a>
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
            <?php if (!empty($products)): ?>
            <div class="card-footer">
              <div class="row g-2 justify-content-center justify-content-sm-between">
                <div class="col-auto d-flex align-items-center">
                  <p class="m-0 text-secondary">Показано 1 по 4 из 1 записей</p>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

<?php include 'footer.php'; ?>