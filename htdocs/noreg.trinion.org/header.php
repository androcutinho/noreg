<?php

session_start();

// Only require database if not already loaded (avoid multiple connections)
if (!isset($mysqli)) {
    $mysqli = require 'config/database.php';
}

// Fetch logged in user info - check if user_id exists in session
$user_name = 'User';
if (isset($_SESSION['user_id'])) {
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
}

?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Панель администратора' ?></title>
        <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">

        <style>
            body {
                max-width: 100%;
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
                    <span class="avatar avatar-0">
                      <!-- Download SVG icon from http://tabler.io/icons/icon/user -->
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon avatar-icon icon-2">
                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"></path>
                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path>
                      </svg>
                    </span>
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
                      <div class="dropdown-menu" data-bs-popper="static" style="width: 175px;">
                        <a class="dropdown-item" href="./">Поступление товаров</a>
                        <a class="dropdown-item" href="./">Перемещение товаров</a>
                      </div>
                    </li>
                    <li class="nav-item dropdown">
                      <a class="nav-link dropdown-toggle" href="#navbar-form" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-id"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v10a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3l0 -10" /><path d="M7 10a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M15 8l2 0" /><path d="M15 12l2 0" /><path d="M7 16l10 0" /></svg></span>
                        <span class="nav-link-title"> Справочники </span>
                      </a>
                      <div class="dropdown-menu" data-bs-popper="static">
                        <a class="dropdown-item" href="https://noreg.trinion.org/spisok_tovarov.php"> Список товары </a>
                      </div>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" href="./">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-checklist"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9.615 20h-2.615a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v8" /><path d="M14 19l2 2l4 -4" /><path d="M9 8h4" /><path d="M9 12h2" /></svg></span>
                        <span class="nav-link-title"> Задачи </span>
                      </a>
                    </li> 
                    <li class="nav-item dropdown">
                      <a class="nav-link dropdown-toggle" href="#navbar-form" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/checkbox -->
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                <path d="M4 20h16"></path>
                                <path d="M4 12h16"></path>
                                <path d="M4 4h16"></path>
                              </svg></span>
                        <span class="nav-link-title"> Cкрипты </span>
                      </a>
                      <div class="dropdown-menu" data-bs-popper="static" style="width: 350px;">
                        <div style="padding: 12px 16px;">
                          <div class="input-group mb-2">
                            <input type="text" id="vsd-uuid-input" class="form-control" placeholder=" Загрузить серию из Ветис">
                            <button class="btn" type="button" onclick="loadVSDSeries()">Загрузить</button>
                          </div>
                        </div>
                      </div>
                    </li>
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

            // Function to load VSD series
            function loadVSDSeries() {
                const uuidInput = document.getElementById('vsd-uuid-input');
                const uuid = uuidInput.value.trim();
                
                if (uuid.length === 0) {
                    alert('Пожалуйста, введите UUID');
                    return;
                }
                
                // Redirect to the VSD loader page with the UUID
                window.location.href = 'https://noreg.trinion.org/postupleniye_tovara_vetis.php?uuid=' + encodeURIComponent(uuid);
            }

            // Allow Enter key to trigger the search
            document.addEventListener('DOMContentLoaded', function() {
                const uuidInput = document.getElementById('vsd-uuid-input');
                if (uuidInput) {
                    uuidInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            loadVSDSeries();
                        }
                    });
                }
            });
        </script>
