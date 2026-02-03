<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit();
}

$page_title = 'Серии товара';

$mysqli = require 'config/database.php';
require 'queries/spisok_serii_queries.php';


$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;

if (!$product_id) {
    die('Товар не найден');
}

$series = fetchSeriesByProductId($mysqli, $product_id);

// Get product name from series, or fetch it separately if no series exist
if (!empty($series)) {
    $product_name = $series[0]['product_name'] ?? 'Неизвестный товар';
} else {
    // Fetch product name separately when no series exist
    $stmt = $mysqli->prepare("SELECT naimenovanie FROM tovary_i_uslugi WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product_data = $result->fetch_assoc();
    $product_name = $product_data['naimenovanie'] ?? 'Неизвестный товар';
}

include 'header.php';
?>

<div class="page-body">
    <div class="container-fluid">
        <div style="text-align: right; margin-bottom: 10px;">
            <a href="dannyye_serii.php?product_id=<?= htmlspecialchars($product_id) ?>" class="btn btn-primary">
                Добавить
            </a>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="row w-full">
                    <div class="col">
                        <h3 class="card-title mb-0">Серии товара: <?= htmlspecialchars($product_name) ?></h3>
                        <p class="text-secondary m-0">Всего серий: <?= count($series) ?></p>
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
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert" style="margin: 20px;">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Серии</th>
                            <th>Дата выпуска</th>
                            <th>Срок годности</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($series)): ?>
                            <?php foreach ($series as $seria): ?>
                                <tr>
                                    <td class="text-secondary"><?= htmlspecialchars($seria['nomer']) ?></td>
                                    <td class="text-secondary"><?= htmlspecialchars($seria['data_izgotovleniya'] ?? 'N/A') ?></td>
                                    <td class="text-secondary"><?= htmlspecialchars($seria['srok_godnosti'] ?? 'N/A') ?></td>
                                    <td>
                                        <a href="dannyye_serii.php?seria_id=<?= htmlspecialchars($seria['id']) ?>" class="btn btn-sm btn-primary">Редактировать</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center p-4 text-secondary">
                                    Серии еще не добавлены
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer">
                <a href="javascript:history.back()" class="btn">Назад</a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

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