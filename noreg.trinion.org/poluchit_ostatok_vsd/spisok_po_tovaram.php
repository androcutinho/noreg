<?php


session_start();


if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$page_title = 'Остаток из ВЕТИС';

$mysqli = require '../config/database.php';
require '../queries/spisok_ostatki_vsd.php';


$predpriyatiya = [];
$predpriyatie_query = "SELECT naimenovaniye FROM vetis_predpriyatiya ORDER BY id";
$predpriyatie_result = $mysqli->query($predpriyatie_query);
if ($predpriyatie_result) {
    while ($row = $predpriyatie_result->fetch_assoc()) {
        $predpriyatiya[] = $row['naimenovaniye'];
    }
}

$result = getStockEntriesByProduct($mysqli);
$stock_data = $result['success'] ? $result['data'] : [];


$pivot_data = [];
$tovary = [];

foreach ($stock_data as $entry) {
    $tovar = $entry['naimenovanie_tovara'];
    $predpriyatie = $entry['predpriyataya_naimenovanie'];
    $amount = $entry['summa_ostatok'];
    
    if (!isset($pivot_data[$tovar])) {
        $pivot_data[$tovar] = [];
        $tovary[] = $tovar;
    }
    
    $pivot_data[$tovar][$predpriyatie] = $amount;
}

sort($tovary);

include '../header.php';
?>
      
        <div class="container-fluid mt-5">
          <div class="text-end mb-2">
            <button id="sync-vsd-btn" class="btn btn-primary">
                <span id="sync-btn-text">Обновить</span>
                <span id="sync-btn-spinner" class="d-none ms-2">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                </span>
            </button>
        </div>
          <div id="sync-message-container"></div>
          
          <div class="card">
            <div class="card-header">
              <div class="d-flex align-items-center gap-3 w-100 justify-content-between">
                <div class="d-flex align-items-center gap-4">
                  <div>
                    <h3 class="card-title mb-0">Остаток продукции из ВЕТИС</h3>
                    <p class="text-secondary m-0">Всего записей: <span id="total-records"><?= count($tovary) ?></span> штук.</p>
                  </div>
                  <div class="d-flex">
                    <a href="https://noreg.trinion.org/poluchit_ostatok_vsd/spisok_po_vsd.php" class="btn">
                      По ВСД
                    </a>
                    <a href="https://noreg.trinion.org/poluchit_ostatok_vsd/spisok_po_tovaram.php" class="btn btn-primary">
                      По товарам
                    </a>
                  </div>
                </div>
                <div class="input-group input-group-flat w-auto ms-auto">
                  <span class="input-group-text ">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                      <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path>
                      <path d="M21 21l-6 -6"></path>
                    </svg>
                  </span>
                  <input id="document-table-search" type="text" class="form-control" autocomplete="off" placeholder="Поиск...">
                  <span class="input-group-text">
                  </span>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th class="min-w-500">Товар</th>
                    <?php foreach ($predpriyatiya as $predpriyatie): ?>
                      <th><?php echo htmlspecialchars($predpriyatie); ?></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody id="stock-entries-tbody">
                  <?php if (!empty($tovary)): ?>
                    <?php foreach ($tovary as $tovar): ?>
                      <tr>
                        <td class="text-secondary text-break fw-medium">
                          <?= htmlspecialchars($tovar) ?>
                        </td>
                        <?php foreach ($predpriyatiya as $predpriyatie): ?>
                          <td class="text-secondary text-center">
                            <?= htmlspecialchars($pivot_data[$tovar][$predpriyatie] ?? 0) ?>
                          </td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="<?= count($predpriyatiya) + 1 ?>" class="text-center text-secondary p-4">
                        Записи не найдены.
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          </div>
        </div>
    

<?php include '../footer.php'; ?>

<script>
const syncBtn = document.getElementById('sync-vsd-btn');
const syncBtnText = document.getElementById('sync-btn-text');
const syncBtnSpinner = document.getElementById('sync-btn-spinner');
const syncMessageContainer = document.getElementById('sync-message-container');
const tbodyElement = document.getElementById('stock-entries-tbody');
const searchInput = document.getElementById('document-table-search');


syncBtn.addEventListener('click', function() {
    syncBtn.disabled = true;
    syncBtnText.textContent = 'Загрузка...';
    syncBtnSpinner.classList.remove('d-none');
    syncMessageContainer.innerHTML = '';


    fetch('../api/sync_stock_entries.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            syncMessageContainer.innerHTML = `
                <div class="alert alert-success alert-dismissible" role="alert">
                    <div class="d-flex">
                        <div>${result.message || 'Данные успешно синхронизированы'}</div>
                    </div>
                    <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                </div>
            `;
           
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            syncMessageContainer.innerHTML = `
                <div class="alert alert-danger alert-dismissible" role="alert">
                    <div class="d-flex">
                        <div>Ошибка: ${result.error || 'Неизвестная ошибка'}</div>
                    </div>
                    <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                </div>
            `;
            syncBtn.disabled = false;
            syncBtnText.textContent = 'Обновить';
            syncBtnSpinner.classList.add('d-none');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        syncMessageContainer.innerHTML = `
            <div class="alert alert-danger alert-dismissible" role="alert">
                <div class="d-flex">
                    <div>Ошибка сервера: ${error.message}</div>
                </div>
                <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
            </div>
        `;
        syncBtn.disabled = false;
        syncBtnText.textContent = 'Обновить';
        syncBtnSpinner.classList.add('d-none');
    });
});


if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = tbodyElement.querySelectorAll('tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
}
</script>
