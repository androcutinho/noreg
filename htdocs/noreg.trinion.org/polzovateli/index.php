<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$page_title = 'Пользователи';
$mysqli = require '../config/database.php';

require '../queries/users_queries.php';

// Check if logged in user is admin
$is_admin = isUserAdminById($mysqli, $_SESSION['user_id']);

// Fetch all users
$users = fetchAllUsers($mysqli);

include '../header.php';
?>
    <body>
        <div class="page-body">
        <div class="container-fluid">
            <div class="header">
                <h1>Пользователи</h1>
            </div>

            <?php if (!empty($users)): ?>
                <div class="card">
                <div class="table-responsive">
                <table class="table table-vcenter card-table">
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
                                    <button class="btn btn-1" onclick="deleteUser(<?= htmlspecialchars($user['user_id']) ?>, '<?= htmlspecialchars($user['user_name']) ?>')">Удалить</button>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            </div>
            <?php else: ?>
                <div class="no-users">
                    <p>Пользователей еще не создано. <a href="form.php">Создайте первого пользователя</a></p>
                </div>
            <?php endif; ?>
            <div style="margin-top: 20px; text-align: right;">
                    <a href="form.php" class="btn btn-primary">+ Создать пользователя</a>
                </div>
        </div>
        </div>

        <script>
            function deleteUser(userId, userName) {
                if (confirm('Вы уверены, что хотите удалить пользователя "' + userName + '"? Это действие нельзя отменить.')) {
                    window.location.href = 'delete.php?user_id=' + userId;
                }
            }
        </script>
      
    </body>

<?php
include '../footer.php';
?>
