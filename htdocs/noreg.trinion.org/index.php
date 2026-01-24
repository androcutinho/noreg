<?php

session_start();

if (isset($_SESSION["user_id"])) {
    require 'queries/index_queries.php';
}

include 'header.php';

if (isset($_SESSION["user_id"])) {
    $user = fetchUserById($mysqli, $_SESSION["user_id"]);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Главная</title>
    <meta charset="UTF-8">
    <link rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css" />

</head>
<body>
    
    <h1>Главная</h1>
    
    <?php if (isset($user)): ?>
        
        <p>Привет <?= htmlspecialchars($user["user_name"]) ?></p>
        
        <p><a href="log_out.php">Выход</a></p>
        
    <?php else: ?>
        
        <p><a href="log_in.php">Вход</a></p>
        
    <?php endif; ?>
    <script
  src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js">
</script>
</body>
</html>
<?php include 'footer.php'; ?>