<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$mysqli = require 'database.php';

// Check if logged in user is admin
$sql = "SELECT user_role FROM users WHERE user_id = ?";
$stmt = $mysqli->stmt_init();

if (!$stmt->prepare($sql)) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$logged_in_user = $result->fetch_assoc();
$is_admin = $logged_in_user && $logged_in_user['user_role'] ? true : false;

// Fetch all users
$sql = "SELECT user_id, user_name, email, user_role FROM users ORDER BY user_id DESC";
$result = $mysqli->query($sql);
$users = array();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Управление пользователями</title>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
        <style>
            body {
                max-width: 1000px;
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
                margin-top: 20px;
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
            .no-users {
                text-align: center;
                padding: 20px;
                color: #666;
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
            .badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: bold;
            }
            .badge-admin {
                background-color: #28a745;
                color: white;
            }
            .badge-user {
                background-color: #6c757d;
                color: white;
            }
            .btn-delete {
                background-color: #dc3545;
                color: white;
                padding: 6px 12px;
                font-size: 12px;
                text-decoration: none;
                border-radius: 3px;
                border: none;
                cursor: pointer;
            }
            .btn-delete:hover {
                background-color: #c82333;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Пользователи</h1>
            <div>
                <a href="create_user.php" class="btn">+ Создать пользователя</a>
                <a href="admin_page.php" class="btn btn-secondary">Вернуться в админ панель</a>
            </div>
        </div>

        <?php if (!empty($users)): ?>
            <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Имя</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['user_id']) ?></td>
                            <td><?= htmlspecialchars($user['user_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <?php if ($user['user_role']): ?>
                                    <span class="badge badge-admin">Администратор</span>
                                <?php else: ?>
                                    <span class="badge badge-user">Пользователь</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_admin): ?>
                                    <button class="btn-delete" onclick="deleteUser(<?= htmlspecialchars($user['user_id']) ?>, '<?= htmlspecialchars($user['user_name']) ?>')">Удалить</button>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="no-users">
                <p>Пользователей еще не создано. <a href="create_user.php">Создайте первого пользователя</a></p>
            </div>
        <?php endif; ?>

        <script>
            function deleteUser(userId, userName) {
                if (confirm('Вы уверены, что хотите удалить пользователя "' + userName + '"? Это действие нельзя отменить.')) {
                    window.location.href = 'delete_user.php?user_id=' + userId;
                }
            }
        </script>

    </body>
</html>
