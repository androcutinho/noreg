<?php

session_start();

$mysqli = require 'config/database.php';
require 'queries/admin_queries.php';

$is_invalid = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    
    if (empty($_POST["email"]) || empty($_POST["password"])) {
        $is_invalid = true;
    } else {
        
        
        $sql = "SELECT user_id, user_password_hash FROM users WHERE email = ?";
        
        $stmt = $mysqli->prepare($sql);
        
        if (!$stmt) {
            die("Ошибка в запросе: " . $mysqli->error);
        }
        
        $stmt->bind_param("s", $_POST["email"]);
        
        if (!$stmt->execute()) {
            die("Ошибка при выполнении запроса: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($_POST["password"], $user["user_password_hash"])) {
            
            session_regenerate_id();
            
            $_SESSION["user_id"] = $user["user_id"];
            
            header("Location: postuplenie/index.php");
            exit;
        }
        
        $is_invalid = true;
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Вход</title>
    <meta charset="UTF-8">
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
</head>
<body>
    

    <div class="page page-center">
      <div class="container container-tight py-4">
        <div class="card card-md">
          <div class="card-body">
            <h2 class="h2 text-center mb-4">Войдите в свой аккаунт</h2>
            <form action="log_in.php" method="post" autocomplete="off" novalidate="">
              <div class="mb-3">
                <label class="form-label">Адрес электронной почты</label>
                <input type="email" class="form-control"  name="email" id="email" placeholder="ваша@почта.com" autocomplete="off" 
               value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">
              </div>
              <div class="mb-2">
                <label class="form-label">
                  Пароль
                </label>
                <div class="input-group input-group-flat">
                  <input type="password" class="form-control" placeholder="Ваш пароль" autocomplete="off"  name="password" id="password">
                  <span class="input-group-text">
                    <a href="#" class="link-secondary toggle-password" data-bs-toggle="tooltip" aria-label="Показать пароль" data-bs-original-title="Показать пароль"><!-- Download SVG icon from http://tabler.io/icons/icon/eye -->
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"></path>
                        <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"></path></svg></a>
                  </span>
                </div>
              </div>
              <div class="form-footer">
                <button type="submit" class="btn btn-primary w-100">Войти</button>
              </div>
            </form>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col">
                <?php if ($is_invalid): ?>
                  <div class="alert alert-danger" role="alert">
                    <em>Неверный вход</em>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <script>
      document.querySelector('.toggle-password').addEventListener('click', function(e) {
        e.preventDefault();
        const passwordInput = document.getElementById('password');
        const currentType = passwordInput.getAttribute('type');
        passwordInput.setAttribute('type', currentType === 'password' ? 'text' : 'password');
      });
    </script>
</body>
</html>