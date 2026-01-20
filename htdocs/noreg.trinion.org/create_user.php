<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$mysqli = require 'database.php';

// Check if user has admin permissions
$sql = "SELECT user_role FROM users WHERE user_id = ?";
$stmt = $mysqli->stmt_init();

if (!$stmt->prepare($sql)) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$logged_in_user = $result->fetch_assoc();

// If user doesn't exist or doesn't have admin role, deny access
if (!$logged_in_user || !$logged_in_user['user_role']) {
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
        
        // Prepare insert statement
        $sql = "INSERT INTO users (user_name, email, user_password_hash, user_role) VALUES (?, ?, ?, ?)";
        $stmt = $mysqli->stmt_init();
        
        if (!$stmt->prepare($sql)) {
            $error = "SQL error: " . $mysqli->error;
        } else {
            $stmt->bind_param("sssi", $_POST['user_name'], $_POST['email'], $password_hash, $user_role);
            
            if ($stmt->execute()) {
                $success = true;
                header('Location: users.php');
                exit;
            } else {
                if ($mysqli->errno === 1062) {
                    $error = 'Электронная почта уже существует';
                } else {
                    $error = "Ошибка при создании пользователя: " . $mysqli->error;
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Создать пользователя</title>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
        <style>
            .error {
                color: red;
                margin-bottom: 20px;
            }
            .checkbox-container {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .checkbox-container input[type="checkbox"] {
                width: 20px;
                height: 20px;
                cursor: pointer;
            }
            .checkbox-container label {
                margin: 0;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <h1>Создать нового пользователя</h1>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">

            <div>
                <label for="user_name">Имя:</label>
                <input type="text" id="user_name" name="user_name" required
                value="<?= htmlspecialchars($_POST['user_name'] ?? '') ?>">
            </div>

            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div>
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div>
                <label for="password_confirmation">Подтвердите пароль:</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <div class="checkbox-container">
                <input type="checkbox" id="user_role" name="user_role"
                <?= isset($_POST['user_role']) ? 'checked' : '' ?>>
                <label for="user_role">Права администратора</label>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit">Создать пользователя</button>
                <a href="users.php" style="margin-left: 10px;">Отмена</a>
            </div>

        </form>
    </body>
</html>
