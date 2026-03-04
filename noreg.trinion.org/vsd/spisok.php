<?php


session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$page_title = 'Список ВСД';

$mysqli = require '../config/database.php';
require '../queries/vetis_vsd_queries.php';


$enterprises_sql = "SELECT DISTINCT enterpriseGuid, naimenovaniye FROM vetis_predpriyatiya WHERE enterpriseGuid IS NOT NULL AND enterpriseGuid != '' ORDER BY naimenovaniye";
$enterprises_result = $mysqli->query($enterprises_sql);
$enterprises = $enterprises_result ? $enterprises_result->fetch_all(MYSQLI_ASSOC) : [];


$vybrannyj_enterprise_guid = isset($_GET['guid']) ? htmlspecialchars($_GET['guid']) : null;


$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 8;

$total_documents = getDocumentsCount($mysqli, $vybrannyj_enterprise_guid);
$total_pages = ceil($total_documents / $items_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $items_per_page;

$documents = fetchAllDocuments($mysqli, $vybrannyj_enterprise_guid, $items_per_page, $offset);

include '../header.php';
?>
        <div class="container-fluid mt-5">
          <div class="text-end mb-1">
            <button id="sync-vsd-btn" class="btn btn-primary">
                <span id="sync-btn-text">Загрузить ВСД</span>
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
                  <h3 class="card-title mb-0">Список документов ВСД</h3>
                  <p class="text-secondary m-0">Всего документов: <?= $total_documents ?> штук.</p>
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
                      <div class="dropdown">
                              <a href="#" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php 
                                if ($vybrannyj_enterprise_guid) {
                                    $selected = array_filter($enterprises, fn($e) => $e['enterpriseGuid'] == $vybrannyj_enterprise_guid);
                                    $naimenovanie_enterprise = !empty($selected) ? reset($selected)['naimenovaniye'] : 'Предприятие';
                                    echo htmlspecialchars($naimenovanie_enterprise);
                                } else {
                                    echo 'Предприятия';
                                }
                                ?>
                              </a>
                              <div class="dropdown-menu" style="">
                                <a class="dropdown-item" href="?">Все предприятия</a>
                                <?php foreach ($enterprises as $enterprise): ?>
                                  <a class="dropdown-item" href="?guid=<?= htmlspecialchars($enterprise['enterpriseGuid']) ?>"><?= htmlspecialchars($enterprise['naimenovaniye']) ?></a>
                                <?php endforeach; ?>
                              </div>
                            </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>UUID</th>
                    <th>Дата оформления</th>
                    <th>Тип документа</th>
                    <th>Статус</th>
                    <th>Дата обновления</th>
                    <th>Дата изготовления</th>
                    <th>Срок годности</th>
                    <th>Отправитель</th>
                    <th>Получатель</th>
                    <th>Действие</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($documents)): ?>
                    <?php foreach ($documents as $doc): ?>
                      <tr>
                        <td class="text-secondary fs-5 text-break mw-150px">
                          <?= htmlspecialchars($doc['uuid']) ?>
                        </td>
                        <td class="text-secondary"><?= htmlspecialchars($doc['issueDate']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars(getDocumentTypeLabel($doc['vetDType'])) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars(getDocumentStatusLabel($doc['vetDStatus'])) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($doc['lastUpdateDate']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($doc['dateOfProduction']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($doc['expiryDate']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($doc['enterprise']) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($doc['consignee']) ?></td>
                        <td>
                          <?php if (hasProductSeries($mysqli, $doc['id_tovary_i_uslugi'])): ?>
                            <a href="https://noreg.trinion.org/serii/spisok.php?product_id=<?= $doc['id_tovary_i_uslugi'] ?>" class="btn btn-primary">
                              Открыть серию
                            </a>
                          <?php else: ?>
                            <a href="https://noreg.trinion.org/serii/redaktirovanie.php?product_id=<?= $doc['id_tovary_i_uslugi'] ?>&prod_date=<?= urlencode($doc['dateOfProduction']) ?>&exp_date=<?= urlencode($doc['expiryDate']) ?>" class="btn btn-primary">
                              Создать
                            </a>
                          <?php endif; ?>
                          <?php if (!$doc['zakryt']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta(<?= $doc['id'] ?>, 'zakryt', true);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M10.507 10.498l-1.507 1.502v3h3l1.493 -1.498m2 -2.01l4.89 -4.907a2.1 2.1 0 0 0 -2.97 -2.97l-4.913 4.896"></path>
                                <path d="M16 5l3 3"></path>
                                <path d="M3 3l18 18"></path>
                              </svg>
                            Закрыть
                        </button>
                        <?php endif; ?>
                        <?php if ($doc['zakryt']): ?>
                        <button type="button" class="btn btn-primary" onclick="ObnovitPoleDokumenta(<?= $doc['id'] ?>, 'zakryt', false);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M14 10m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                <path d="M21 12a9 9 0 1 1 -18 0a9 9 0 0 1 18 0z"></path>
                                <path d="M12.5 11.5l-4 4l1.5 1.5"></path>
                                <path d="M12 15l-1.5 -1.5"></path>
                              </svg>
                            Открыть
                        </button>
                        <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="9" class="text-center text-secondary p-4">
                        Документы не найдены.
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
                    $url_params = "?" . ($vybrannyj_enterprise_guid ? "guid=" . htmlspecialchars($vybrannyj_enterprise_guid) . "&" : "");
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
    

<?php include '../footer.php'; ?>
<script>
function ObnovitPoleDokumenta(documentId, fieldName, value) {
    const tableName = 'vetis_vsd';
    
    fetch('../api/toggle_field.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            table_name: tableName,
            document_id: documentId,
            field_name: fieldName,
            value: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) location.reload();
    })
    .catch(error => console.error('Error:', error));
}
  </script>
<script src="../js/sync_vsd.js"></script>
