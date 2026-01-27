<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

if (!isset($_GET['uuid']) || empty($_GET['uuid'])) {
    die("UUID документа не предоставлен.");
}

$uuid = $_GET['uuid'];

require_once(__DIR__ . '/api/vetis_service.php');

$data = fetchVetisDocument($uuid);

if (!$data['success']) {
    die('Ошибка: ' . htmlspecialchars($data['error']));
}
extract($data);

$page_title = 'Name';

include 'header.php';
?>

<div class="container-xl">
    <div class="row mb-3 d-print-none" style="margin-top: 30px;">
        <div class="col-auto ms-auto">
            <button type="button" class="btn btn-primary" onclick="window.location.href='add_postupleniye_tovara_vetis.php?uuid=<?php echo $uuid; ?>';"> Создать поступление</a>
            </button>
            <button type="button" class="btn btn-primary" onclick="window.print();">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                    <path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2"></path>
                    <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4"></path>
                    <path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z"></path>
                </svg>
                Печать
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_page.php';">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M9 6l-6 6l6 6"></path>
                    <path d="M15 18h-6a6 6 0 0 1 0 -12h7"></path>
                </svg>
                Назад
            </button>
        </div>
    </div>

    <div class="card card-lg">
        <div class="card-body">
            <div class="row">
                <div class="col-4">
                    <p class="h3">Отправитель</p>
                    <address>
                        <?= htmlspecialchars($shipper_name) ?><br>
                    </address>
                </div>
                <div class="col-4 text-center">
                    <p class="h3">Дата оформления</p>
                    <address>
                        <?= htmlspecialchars($date_issued) ?><br>
                    </address>
                </div>
                <div class="col-4 text-end">
                    <p class="h3">Получатель</p>
                    <address>
                        <?= htmlspecialchars($receiver_name) ?><br>
                    </address>
                </div>
                <div class="col-12 my-5">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1>Name of the document</h1>
                        <span class="badge bg-<?php 
                            if ($status === 'CONFIRMED') echo 'info';
                            elseif ($status === 'WITHDRAWN') echo 'success';
                            elseif ($status === 'UTILIZED') echo 'danger';
                            else echo 'secondary';
                        ?> text-white">
                            <?php 
                                $status_map = [
                                    'CONFIRMED' => 'Оформлен',
                                    'WITHDRAWN' => 'Аннулирован',
                                    'UTILIZED' => 'Погашен',
                                    'FINALIZED' => 'Закрыт'
                                ];
                                echo htmlspecialchars($status_map[$status] ?? $status);
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            
            <div class="row mt-4">
                <div class="col-12">
                    <h3>Информация о документе</h3>
                    <table class="table table-sm table-hover">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 30%">UUID:</td>
                                <td><?= htmlspecialchars(substr($doc_uuid, 0, 36)) ?></td>
                            </tr>
                             <tr>
                                <td class="text-muted">Форма:</td>
                                <td><?php 
                                    $form_map = [
                                        'CERTCU1' => 'Форма 1 ветеринарного сертификата ТС',
                                        'LIC1' => 'Форма 1 ветеринарного свидетельства',
                                        'CERTCU2' => 'Форма 2 ветеринарного сертификата ТС',
                                        'LIC2' => 'Форма 2 ветеринарного свидетельства',
                                        'CERTCU3' => 'Форма 3 ветеринарного сертификата ТС',
                                        'LIC3' => 'Форма 3 ветеринарного свидетельства',
                                        'NOTE4' => 'Форма 4 ветеринарной справки',
                                        'CERT5I' => 'Форма 5i ветеринарного сертификата',
                                        'CERT61' => 'Форма 6.1 ветеринарного сертификата',
                                        'CERT62' => 'Форма 6.2 ветеринарного сертификата',
                                        'CERT63' => 'Форма 6.3 ветеринарного сертификата',
                                        'PRODUCTIVE' => 'Форма производственного ветеринарного сертификата'
                                    ];
                                    echo htmlspecialchars($form_map[$form] ?? $form);
                                ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Тип:</td>
                                <td><?php 
                                    $type_map = [
                                        'INCOMING' => 'Входящий ВСД',
                                        'OUTGOING' => 'Исходящий ВСД',
                                        'PRODUCTIVE' => 'Производственный ВСД',
                                        'RETURNABLE' => 'Возвратный ВСД',
                                        'TRANSPORT' => 'Транспортный ВСД'
                                    ];
                                    echo htmlspecialchars($type_map[$type] ?? $type);
                                ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Дата последнего обновления:</td>
                                <td><?= htmlspecialchars($last_update) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div class="row mt-5">
                <div class="col-12">
                    <h3>Информация о транспортировке</h3>
                    <table class="table table-sm table-hover">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 30%">Номер автомашины:</td>
                                <td><?= htmlspecialchars($vehicle_number) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Номер полуприцепа/контейнера:</td>
                                <td><?= htmlspecialchars($trailer_number) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Способ хранения при перевозке:</td>
                                <td>
                                    <?php 
                                        $storage_map = [
                                            'FROZEN' => 'Замороженный',
                                            'CHILLED' => 'Охлажденный',
                                            'COOLED' => 'Охлаждаемый',
                                            'VENTILATED' => 'Вентилируемый'
                                        ];
                                        echo htmlspecialchars($storage_map[$storage_type] ?? $storage_type);
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cargo Info Section -->
            <div class="row mt-5">
                <div class="col-12">
                    <h3>Информация о продукции</h3>
                    <table class="table table-sm table-hover">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 30%">Название продукции:</td>
                                <td><?= htmlspecialchars($product_name) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Объём:</td>
                                <td><?= htmlspecialchars($volume) ?> <?= htmlspecialchars($unit_name) ?></td>
                            </tr>
                             <tr>
                                <td class="text-muted">Дата выработки продукции:</td>
                                <td><?= htmlspecialchars($prod_date) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Годен до:</td>
                                <td><?= htmlspecialchars($exp_date) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Упаковка:</td>
                                <td><?= htmlspecialchars($package_type) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Количество упаковок:</td>
                                <td><?= htmlspecialchars($package_quantity) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Страна происхождения:</td>
                                <td><?= htmlspecialchars($country) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Производитель:</td>
                                <td><?= htmlspecialchars($producer) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div class="row mt-5">
                <div class="col-12">
                    <h3>История статусов</h3>
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th style="width: 20%">Статус</th>
                                <th style="width: 25%">Дата и время</th>
                                <th style="width: 25%">ФИО</th>
                                <th style="width: 30%">Организация</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($status_changes)): ?>
                                <?php foreach ($status_changes as $change): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                                $status_map = [
                                                    'CONFIRMED' => 'Оформлен',
                                                    'WITHDRAWN' => 'Аннулирован',
                                                    'UTILIZED' => 'Погашен',
                                                    'FINALIZED' => 'Закрыт'
                                                ];
                                                echo htmlspecialchars($status_map[$change['status']] ?? $change['status']);
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($change['actualDateTime'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($change['specifiedPerson']['fio'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($change['specifiedPerson']['organization']['name'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-muted text-center">История статусов отсутствует</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-secondary text-center mt-5">Документ получен из системы ВЕТИС (Ветеринарная электронная торговая информационная система)</p>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
