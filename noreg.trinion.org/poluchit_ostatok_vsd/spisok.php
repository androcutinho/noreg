<?php


session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$page_title = 'Остаток из ВЕТИС';

$documents = [];

include '../header.php';
?>
      <div class="page-body">
        <div class="container-fluid">
          <div style="text-align: right; margin-bottom: 10px;">
            <button id="sync-vsd-btn" class="btn btn-primary">
                <span id="sync-btn-text">Обновить</span>
                <span id="sync-btn-spinner" style="display: none; margin-left: 8px;">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                </span>
            </button>
        </div>
          <div id="sync-message-container"></div>
          
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                  <h3 class="card-title mb-0">Остаток продукции из ВЕТИС</h3>
                  <p class="text-secondary m-0">Всего записей: <span id="total-records">0</span> штук.</p>
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
                      <input id="document-table-search" type="text" class="form-control" autocomplete="off" placeholder="Поиск...">
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
                    <th>Предприятие</th>
                    <th>Товар</th>
                    <th>ВСД</th>
                    <th>Остаток</th>
                    <th>Ед.</th>
                  </tr>
                </thead>
                <tbody id="stock-entries-tbody">
                  <tr>
                    <td colspan="5" class="text-center text-secondary p-4">
                      Загрузка данных...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
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
const totalRecordsSpan = document.getElementById('total-records');

function displayMessage(message, isError = false) {
    syncMessageContainer.innerHTML = `
        <div class="alert alert-${isError ? 'danger' : 'success'} alert-dismissible" role="alert">
            <div class="d-flex">
                <div>
                    ${message}
                </div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    `;
}

function displayTableData(data) {
    if (!data || data.length === 0) {
        tbodyElement.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-secondary p-4">
                    Записи не найдены.
                </td>
            </tr>
        `;
        totalRecordsSpan.textContent = '0';
        return;
    }

    tbodyElement.innerHTML = data.map(entry => `
        <tr>
            <td class="text-secondary" style="word-break: break-word;">
                ${entry.enterprise_name ? entry.enterprise_name : 'Не указано'}
            </td>
            <td class="text-secondary" style="word-break: break-word;">
                ${entry.product_name ? entry.product_name : 'Не указано'}
            </td>
            <td class="text-secondary">
            ${entry.vsd_uuid ? entry.vsd_uuid : 'Не указано'}
            </td>
            <td class="text-secondary">
                ${entry.remaining_amount ? entry.remaining_amount : '0'}
            </td>
            <td class="text-secondary">
                ${entry.unit ? entry.unit : ''}
            </td>
        </tr>
    `).join('');

    totalRecordsSpan.textContent = data.length;
}

function loadStockEntries() {
    syncBtn.disabled = true;
    syncBtnText.textContent = 'Загрузка...';
    syncBtnSpinner.style.display = 'inline-block';
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
            displayMessage(result.message, false);
            displayTableData(result.data);
        } else {
            displayMessage('Ошибка: ' + (result.error || 'Неизвестная ошибка'), true);
            tbodyElement.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-secondary p-4">
                        Ошибка загрузки данных.
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        displayMessage('Ошибка сервера: ' + error.message, true);
        tbodyElement.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-secondary p-4">
                    Ошибка подключения.
                </td>
            </tr>
        `;
    })
    .finally(() => {
        syncBtn.disabled = false;
        syncBtnText.textContent = 'Обновить';
        syncBtnSpinner.style.display = 'none';
    });
}

// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadStockEntries();
});

// Load data when button is clicked
syncBtn.addEventListener('click', function() {
    loadStockEntries();
});

// Search functionality
const searchInput = document.getElementById('document-table-search');
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

