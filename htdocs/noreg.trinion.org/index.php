<?php

session_start();

$page_title = 'Главная';

include 'header.php';

if (isset($_SESSION["user_id"])) {
    require 'queries/index_queries.php';
    $user = fetchUserById($mysqli, $_SESSION["user_id"]);
}
?>

<div class="page-body">
    <div class="container-xl">
        <?php if (isset($user)): ?>
            <!-- Logged In Dashboard -->
            <div class="page-wrapper">
                <div class="container-xl">
                    <div class="page-header d-print-none">
                        <div class="row align-items-center">
                            <div class="col">
                                <h2 class="page-title">
                                    Добро пожаловать, <?= htmlspecialchars($user["user_name"]) ?>!
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="page-body">
                    <div class="container-xl">
                        <div class="row row-deck row-cards">
                            
                            <div class="col-md-6 col-lg-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-truncate">
                                            <h3 class="card-title">
                                                <a href="tovary" class="text-reset">Товары</a>
                                            </h3>
                                            <div class="text-secondary">Управление товарами</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="col-md-6 col-lg-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-truncate">
                                            <h3 class="card-title">
                                                <a href="tovary" class="text-reset">Серии</a>
                                            </h3>
                                            <div class="text-secondary">Управление сериями</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="col-md-6 col-lg-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-truncate">
                                            <h3 class="card-title">
                                                <a href="polzovateli" class="text-reset">Пользователи</a>
                                            </h3>
                                            <div class="text-secondary">Управление пользователями</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="col-md-6 col-lg-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-truncate">
                                            <h3 class="card-title">
                                                <a href="postuplenie" class="text-reset">Администрация</a>
                                            </h3>
                                            <div class="text-secondary">Панель администратора</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            
            <div class="page-wrapper">
                <div class="container-xl">
                    <div class="page-header d-print-none" style="margin-top: 60px;">
                        <div class="row align-items-center">
                            <div class="col text-center">
                                <h1 class="page-title mb-4">
                                    Система управления товарами
                                </h1>
                                <p class="text-secondary mb-4" style="font-size: 1.1rem;">
                                    Добро пожаловать в нашу систему управления товарами
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="page-body">
                    <div class="container-xl">
                        <div class="row justify-content-center mb-4">
                            <div class="col-md-6">
                                <a href="log_in.php" class="btn btn-primary btn-lg w-100">
                                    Войти в систему
                                </a>
                            </div>
                        </div>
                        <div class="row row-deck row-cards mt-5">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2 text-primary" width="48" height="48" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="12 3 20 7.5 20 16.5 12 21 4 16.5 4 7.5 12 3" /><line x1="12" y1="12" x2="20" y2="7.5" /><line x1="12" y1="12" x2="12" y2="21" /><line x1="12" y1="12" x2="4" y2="7.5" /></svg>
                                        <h3>Управление товарами</h3>
                                        <p class="text-secondary">Ведение каталога товаров и услуг</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2 text-primary" width="48" height="48" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /><path d="M16 5.25l-8 4.5" /></svg>
                                        <h3>Управление сериями</h3>
                                        <p class="text-secondary">Отслеживание партий и серий товаров</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2 text-primary" width="48" height="48" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3c7.2 0 9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9s1.8 -9 9 -9" /><path d="M12 9v6" /><path d="M9 12h6" /></svg>
                                        <h3>Управление пользователями</h3>
                                        <p class="text-secondary">Контроль доступа и прав пользователей</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>