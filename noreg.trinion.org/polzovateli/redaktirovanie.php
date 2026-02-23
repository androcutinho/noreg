<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}
$mysqli = require '../config/database.php';
require '../queries/create_user_queries.php';

// Check if user has admin permissions
if (!isUserAdmin($mysqli, $_SESSION['user_id'])) {
    die("Доступ запрещен. Вам нужны права администратора для доступа к этой странице.");
}

$error = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Validation
    if (empty($_POST['user_name'])) {
        $error = 'Имя обязательно';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Требуется действительный адрес электронной почты';
    } elseif (strlen($_POST['password']) < 8) {
        $error = 'Пароль должен быть не менее 8 символов';
    } elseif (!preg_match('/[a-z]/i', $_POST['password'])) {
        $error = 'Пароль должен содержать хотя бы одну букву';
    } elseif (!preg_match('/[0-9]/', $_POST['password'])) {
        $error = 'Пароль должен содержать хотя бы одну цифру';
    } else {
        // Hash password
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Determine admin role (checkbox)
        $user_role = isset($_POST['user_role']) ? 1 : 0;
        
        // Create user
        $result = createUser($mysqli, $_POST['user_name'], $_POST['email'], $_POST['password'], $user_role);
        
        if ($result['success']) {
            header('Location: index.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Создать пользователя</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
    </head>
    <body>
        <?php include '../header.php'; ?>
        <div class="page-body">
        <div class="container-xl">
          <div class="card">
            <div class="card-header">
              <div class="row w-full">
                <div class="col">
                        <div class="card-header">
                            <h3 class="card-title">Создать нового пользователя</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?= htmlspecialchars($error) ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="space-y">
                                <!-- Username -->
                                <div>
                                    <div class="input-icon">
                                        <span class="input-icon-addon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                                <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"></path>
                                                <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path>
                                            </svg>
                                        </span>
                                        <input type="text" id="user_name" name="user_name" class="form-control" placeholder="Имя пользователя" required
                                        value="<?= htmlspecialchars($_POST['user_name'] ?? '') ?>">
                                    </div>
                                </div>

                                <!-- Email -->
                                <div>
                                    <div class="input-icon">
                                        <span class="input-icon-addon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                                <path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z"></path>
                                                <path d="M3 7l9 6l9 -6"></path>
                                            </svg>
                                        </span>
                                        <input type="email" id="email" name="email" class="form-control" placeholder="Адрес электронной почты" required
                                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    </div>
                                </div>

                                <!-- Password -->
                                <div>
                                    <div class="input-icon">
                                        <span class="input-icon-addon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                                <path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z"></path>
                                                <path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"></path>
                                                <path d="M8 11v-4a4 4 0 1 1 8 0v4"></path>
                                            </svg>
                                        </span>
                                        <input type="password" id="password" name="password" class="form-control" placeholder="Пароль" required>
                                    </div>
                                </div>

                                <!-- Confirm Password -->
                                <div>
                                    <div class="input-icon">
                                        <span class="input-icon-addon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                                <path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z"></path>
                                                <path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"></path>
                                                <path d="M8 11v-4a4 4 0 1 1 8 0v4"></path>
                                            </svg>
                                        </span>
                                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Подтвердить пароль" required>
                                    </div>
                                </div>

                                <!-- Admin Checkbox -->
                                <div>
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" id="user_role" name="user_role"
                                        <?= isset($_POST['user_role']) ? 'checked' : '' ?>>
                                        <span class="form-check-label">Права администратора</span>
                                    </label>
                                </div>

                                <!-- Buttons -->
                                <div class="row align-items-center">
                                    <div class="col-auto ms-auto">
                                        <a href="index.php" class="btn btn-secondary">Отмена</a>
                                        <button type="submit" class="btn btn-primary">
                                            Создать пользователя
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-end">
                                                <path d="M5 12l14 0"></path>
                                                <path d="M13 18l6 -6"></path>
                                                <path d="M13 6l6 6"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
        <?php include '../footer.php'; ?>
    </body>
</html>
