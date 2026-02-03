<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$mysqli = require 'config/database.php';

// Check if logged in user is admin
$sql = "SELECT user_role FROM users WHERE user_id = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$logged_in_user = $result->fetch_assoc();
$stmt->close();

// Only admins can delete users
if (!$logged_in_user || !$logged_in_user['user_role']) {
    header('Location: users.php?error=Доступ запрещен. Только администраторы могут удалять пользователей.');
    exit;
}

// Check if user_id is provided
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    header('Location: users.php');
    exit;
}

$user_id = intval($_GET['user_id']);

// Verify user exists
$sql = "SELECT user_id FROM users WHERE user_id = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Don't allow deleting your own user
if ($user_id == $_SESSION['user_id']) {
    header('Location: users.php?error=Невозможно удалить свой аккаунт');
    exit;
}

// Delete the user
$sql = "DELETE FROM users WHERE user_id = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    die("SQL error: " . $mysqli->error);
}

$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $stmt->close();
    // Redirect to users page after successful deletion
    header('Location: users.php');
    exit;
} else {
    die("Ошибка при удалении пользователя: " . $mysqli->error);
}

?>
