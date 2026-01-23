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

?>

<!DOCTYPE html>
<html>
    <head>
        
        <title>Панель администратора - Продукты</title>
        <meta charset="UTF-8">
        <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">

        <style>
            body {
                max-width: 1200px;
                margin: 0 auto;
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
            }
            .header h1 {
                margin: 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 80px;
                margin-left: auto;
                margin-right: auto;
                max-width: 1200px;
            }
            table th, table td {
                border: 1px solid #ccc;
                padding: 12px;
                text-align: left;
            }
            table th {
                background-color: #f5f5f5;
                font-weight: bold;
                color: #0066cc;
            }
            table tr:hover {
                background-color: #f9f9f9;
            }
            .no-products {
                text-align: center;
                padding: 20px;
                color: #666;
                margin-top: 80px;
                max-width: 1200px;
                margin-left: auto;
                margin-right: auto;
            }
            .action-buttons {
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                margin-right: 10px;
                background-color: #0066cc;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                border: none;
                cursor: pointer;
            }
            .btn:hover {
                background-color: #0052a3;
            }
            .btn-secondary {
                background-color: #6c757d;
            }
            .btn-secondary:hover {
                background-color: #5a6268;
            }
            .top-navbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: #f8f9fa;
                padding: 12px 20px;
                margin-bottom: 30px;
                border-bottom: 1px solid #dee2e6;
            }
            .user-info {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 14px;
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
            .floating-button {
                position: fixed;
                bottom: 30px;
                right: 30px;
                z-index: 999;
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
                      <a class="nav-link" href="./">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                            <path d="M5 12l-2 0l9 -9l9 9l-2 0"></path>
                            <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"></path>
                            <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6"></path></svg></span>
                        <span class="nav-link-title"> Home </span>
                      </a>
                    </li>
                  </ul>
                  <!-- END NAVBAR MENU -->
                </div>
                <div class="col col-md-auto">
                  <ul class="navbar-nav">
                    <li class="nav-item">
                      <a class="nav-link" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSettings">
                        <span class="badge badge-sm bg-red text-red-fg">New</span>
                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                          <!-- Download SVG icon from http://tabler.io/icons/icon/settings -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                            <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"></path>
                            <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"></path>
                          </svg>
                        </span>
                        <span class="nav-link-title"> Theme Settings </span>
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
<?php if (!empty($products)): ?>
            <table>
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
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><a href="view_product_details.php?product_id=<?= htmlspecialchars($product['id']) ?>" style="color: #0066cc; text-decoration: none;"><?= htmlspecialchars($product['id']) ?></a></td>
                            <td><?= htmlspecialchars($product['data_dokumenta']) ?></td>
                            <td><?= htmlspecialchars($product['vendor']) ?></td>
                            <td><?= htmlspecialchars($product['responsible']) ?></td>
                            <td><?= htmlspecialchars($product['total_price']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-products">
                <p>Продукты еще не добавлены. <a href="add_product.php">Добавьте первый продукт</a></p>
            </div>
        <?php endif; ?>
        

            <div class="floating-button">
                <a href="add_product.php" class="btn btn-primary">+ Добавить продукт</a>
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

    </body>
</html>