<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$mysqli = require 'database.php';

// Fetch logged in user info
$sql = "SELECT user_name FROM users WHERE user_id = ?";
$stmt = $mysqli->stmt_init();

if (!$stmt->prepare($sql)) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$logged_in_user = $result->fetch_assoc();

$user_name = $logged_in_user['user_name'] ?? 'User';

// Check if product_id is provided
if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    die("ID документа не предоставлен.");
}

$document_id = intval($_GET['product_id']);

// Fetch document header
$sql = "SELECT 
    pt.id,
    pt.data_dokumenta,
    org.naimenovanie as organization,
    ps.naimenovanie as vendor,
    u.user_name as responsible
FROM postupleniya_tovarov pt
LEFT JOIN organizacii org ON pt.id_organizacii = org.id
LEFT JOIN postavshchiki ps ON pt.id_postavshchika = ps.id
LEFT JOIN users u ON pt.id_otvetstvennyj = u.user_id
WHERE pt.id = ?";

$stmt = $mysqli->stmt_init();
if (!$stmt->prepare($sql)) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();

if (!$document) {
    die("Документ не найден.");
}

// Fetch line items
$sql = "SELECT 
    sd.id,
    ti.naimenovanie as product_name,
    sd.kolichestvo_postupleniya as quantity,
    sd.cena_postupleniya as unit_price,
    sn.stavka_nds as vat_rate,
    (sd.cena_postupleniya * sd.kolichestvo_postupleniya) as total_amount
FROM stroki_dokumentov sd
LEFT JOIN tovary_i_uslugi ti ON sd.id_tovary_i_uslugi = ti.id
LEFT JOIN stavki_nds sn ON sd.id_stavka_nds = sn.id
WHERE sd.id_dokumenta = ?
ORDER BY sd.id ASC";

$stmt = $mysqli->stmt_init();
if (!$stmt->prepare($sql)) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();
$line_items = array();
$subtotal = 0;
$vat_total = 0;

while ($row = $result->fetch_assoc()) {
    $line_items[] = $row;
    $subtotal += $row['total_amount'];
}

// Calculate VAT if items exist
if (!empty($line_items)) {
    $first_item = $line_items[0];
    $vat_rate = floatval($first_item['vat_rate']);
    $vat_total = ($subtotal * $vat_rate) / 100;
}

$total_due = $subtotal + $vat_total;

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Деталы документа поступлення</title>
        <meta charset="UTF-8">
        <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
        <style>
            body {
                padding: 20px;
                max-width: 90%;
                margin: 0 auto;
            }
            .top-navbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: #f8f9fa;
                padding: 12px 20px;
                margin-bottom: 30px;
                border-bottom: 1px solid #dee2e6;
                padding-right: calc(20px + 60px);
            }
            .user-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background-color: #28a745;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 18px;
            }
            .user-menu-container {
                position: relative;
            }
            .user-menu-trigger {
                display: flex;
                align-items: center;
                gap: 12px;
                cursor: pointer;
                padding: 8px 12px;
                border-radius: 4px;
                transition: background-color 0.2s;
            }
            .user-menu-trigger:hover {
                background-color: #e9ecef;
            }
            .dropdown-menu {
                display: none;
                position: absolute;
                top: 100%;
                right: 0;
                background-color: white;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                min-width: 150px;
                z-index: 1000;
                margin-top: 8px;
            }
            .dropdown-menu.show {
                display: block;
            }
            .dropdown-menu a {
                display: block;
                padding: 10px 16px;
                color: #333;
                text-decoration: none;
                border-bottom: 1px solid #f0f0f0;
                transition: background-color 0.2s;
            }
            .dropdown-menu a:last-child {
                border-bottom: none;
            }
            .dropdown-menu a:hover {
                background-color: #f8f9fa;
            }
        </style>
    </head>
    <body>
        <div class="top-navbar">
            <h2 style="margin: 0; color: #333;"></h2>
            <div class="user-menu-container">
                <div class="user-menu-trigger" onclick="toggleUserMenu()">
                    <div class="user-avatar"><?= htmlspecialchars(substr($user_name, 0, 1)) ?></div>
                    <span><?= htmlspecialchars($user_name) ?></span>
                </div>
                <div class="dropdown-menu" id="userDropdownMenu">
                    <a href="users.php">Пользователи</a>
                    <a href="log_out.php">Выход</a>
                </div>
            </div>
        </div>
        <script src="./dist/js/tabler-theme.min.js?1752393271"></script>
    <!-- END GLOBAL THEME SCRIPT -->
    <div class="page">
      <!-- BEGIN NAVBAR  -->
       <header class="navbar-expand-md">
        <div class="collapse navbar-collapse" id="navbar-menu">
          <div class="navbar">
            <div class="container-xl">
              <div class="row flex-column flex-md-row flex-fill align-items-center">
                <div class="col">
                  <!-- BEGIN NAVBAR MENU -->
                  <ul class="navbar-nav">
                    <li class="nav-item">
                      <a class="nav-link" href="https://noreg.trinion.org/admin_page.php">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                            <path d="M5 12l-2 0l9 -9l9 9l-2 0"></path>
                            <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"></path>
                            <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6"></path></svg></span>
                        <span class="nav-link-title"> Главная </span>
                      </a>
                    </li>
                    <li class="nav-item dropdown">
                      <a class="nav-link dropdown-toggle show" href="#navbar-form" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="true">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/checkbox -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2"></path></svg></span>
                        <span class="nav-link-title"> Документы </span>
                      </a>
                      <div class="dropdown-menu" data-bs-popper="static">
                        <a class="dropdown-item" href="./form-elements.html"> Поступление товаров </a>
                        <a class="dropdown-item" href="./form-layout.html">
                          Перемещение товаров
                        </a>
                      </div>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" href="./">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-id"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v10a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3l0 -10" /><path d="M7 10a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M15 8l2 0" /><path d="M15 12l2 0" /><path d="M7 16l10 0" /></svg></span>
                        <span class="nav-link-title"> Справочники </span>
                      </a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" href="./">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-checklist"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9.615 20h-2.615a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v8" /><path d="M14 19l2 2l4 -4" /><path d="M9 8h4" /><path d="M9 12h2" /></svg></span>
                        <span class="nav-link-title"> Задачи </span>
                      </a>
                    </li> 
                  </ul>
      <!-- END NAVBAR  -->
        </div>
                <div class="col col-md-auto">
                  <ul class="navbar-nav">
                    <li class="nav-item">
                      <a class="nav-link" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSettings">
                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                          <!-- Download SVG icon from http://tabler.io/icons/icon/settings -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                            <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"></path>
                            <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"></path>
                          </svg>
                        </span>
                        <span class="nav-link-title"> Настройки </span>
                      </a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>
      <!-- END NAVBAR  -->
    </head>
    <body>
                     <div class="row mb-3 d-print-none" style="margin-top: 30px;">
                    <div class="col-auto ms-auto">
                        <button type="button" class="btn btn-primary" onclick="javascript:window.print();">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2"></path>
                                <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4"></path>
                                <path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z"></path>
                            </svg>
                            Печать
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='edit_product.php?product_id=<?= $document_id ?>';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path>
                                <path d="M16 5l3 3"></path>
                            </svg>
                            Редактировать
                        </button>
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Вы уверены?')) window.location.href='delete_product.php?product_id=<?= $document_id ?>';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M4 7l16 0"></path>
                                <path d="M10 11l0 6"></path>
                                <path d="M14 11l0 6"></path>
                                <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path>
                                <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path>
                            </svg>
                            Удалить
                        </button>
                    </div>
                </div>
        <div class="card card-lg">
            <div class="card-body">
                <div class="row">
                    <div class="col-4">
                        <p class="h3">Организация</p>
                        <address>
                            <?= htmlspecialchars($document['organization'] ?? 'N/A') ?><br>
                        </address>
                    </div>
                    <div class="col-4 text-center">
                        <p class="h3">Дата</p>
                        <address>
                            <?= htmlspecialchars($document['data_dokumenta']) ?><br>
                        </address>
                    </div>
                    <div class="col-4 text-end">
                        <p class="h3">Поставщик</p>
                        <address>
                            <?= htmlspecialchars($document['vendor'] ?? 'N/A') ?><br>
                        </address>
                    </div>
                    <div class="col-12 my-5">
                        <h1>Документ поступлення №<?= htmlspecialchars($document['id']) ?></h1>
                    </div>
                </div>
                <table class="table table-transparent table-responsive">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 5%"></th>
                            <th style="width: 60%">Товар</th>
                            <th class="text-center" style="width: 5%">Кол-во</th>
                            <th class="text-end" style="width: 15%">Цена</th>
                            <th class="text-end" style="width: 15%">Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $item_num = 1;
                        foreach ($line_items as $item): 
                        ?>
                            <tr>
                                <td class="text-center"><?= $item_num ?></td>
                                <td>
                                    <p class="strong mb-1"><?= htmlspecialchars($item['product_name']) ?></p>
                                </td>
                                <td class="text-center"><?= htmlspecialchars($item['quantity']) ?></td>
                                <td class="text-end"><?= number_format($item['unit_price'], 2, ',', ' ') ?></td>
                                <td class="text-end"><?= number_format($item['total_amount'], 2, ',', ' ') ?></td>
                            </tr>
                        <?php 
                        $item_num++;
                        endforeach; 
                        ?>
                        <tr style="height: 50px;"><td colspan="5"></td></tr>
                        <tr style="height: 50px;"><td colspan="5"></td></tr>
                        <tr>
                            <td colspan="4" class="strong text-end">Промежуточный итог</td>
                            <td class="text-end"><?= number_format($subtotal, 2, ',', ' ') ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="strong text-end">Ставка НДС</td>
                            <td class="text-end"><?= htmlspecialchars(!empty($line_items) ? $line_items[0]['vat_rate'] : 0) ?>%</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="strong text-end">НДС к оплате</td>
                            <td class="text-end"><?= number_format($vat_total, 2, ',', ' ') ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="font-weight-bold text-uppercase text-end">Итого к оплате</td>
                            <td class="font-weight-bold text-end"><?= number_format($total_due, 2, ',', ' ') ?></td>
                        </tr>
                    </tbody>
                </table>
                <p class="text-secondary text-center mt-5">Благодарим вас за сотрудничество. Мы надеемся на продолжение работы с вами!</p>
            </div>
        </div>
         <script>
            function toggleUserMenu() {
                const menu = document.getElementById('userDropdownMenu');
                menu.classList.toggle('show');
            }

            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                const menu = document.getElementById('userDropdownMenu');
                const trigger = document.querySelector('.user-menu-trigger');
                
                if (!trigger.contains(event.target) && !menu.contains(event.target)) {
                    menu.classList.remove('show');
                }
            });
        </script>

        <!--  BEGIN FOOTER  -->
        <footer class="footer footer-transparent d-print-none">
          <div class="container-xl">
            <div class="row text-center align-items-center flex-row-reverse">
              <div class="col-lg-auto ms-lg-auto">
                <ul class="list-inline list-inline-dots mb-0">
                  <li class="list-inline-item"><a href="https://docs.tabler.io" target="_blank" class="link-secondary" rel="noopener">Инструкции</a></li>
                  <li class="list-inline-item"><a href="./license.html" class="link-secondary">Поддержка</a></li>
                  <li class="list-inline-item">
                    <a href="https://noreg.trinion.org/admin_page.php" target="_blank" class="link-secondary" rel="noopener">
                      <!-- Download SVG icon from http://tabler.io/icons/icon/heart -->
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon text-pink icon-inline icon-4">
                        <path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572"></path>
                      </svg>
                      Сделано в России.
                    </a>
                  </li>
                </ul>
              </div>
              <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                <ul class="list-inline list-inline-dots mb-0">
                  <li class="list-inline-item">
                    Тринион. 
                    <a href="." class="link-secondary"></a>
                  </li>
                  <li class="list-inline-item">
                    <a href="./changelog.html" class="link-secondary" rel="noopener"> версия: 1.0.0 </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </footer>
        <!--  END FOOTER  -->
    </body>
</html>

