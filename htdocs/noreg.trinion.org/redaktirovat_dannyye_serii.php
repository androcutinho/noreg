<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit();
}

$page_title = 'Редактировать серию товара';

$mysqli = require 'config/database.php';
require 'queries/redaktirovat_dannyye_serii_queries.php';


$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;

if (!$product_id) {
    die('Товар не найден');
}

// Fetch product data
$stmt = $mysqli->prepare("SELECT id, naimenovanie FROM tovary_i_uslugi WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();
$product = $product_result->fetch_assoc();

if (!$product) {
    die('Товар не найден');
}


$stmt = $mysqli->prepare("SELECT id, nomer, data_izgotovleniya, srok_godnosti FROM serii WHERE id_tovary_i_uslugi = ? LIMIT 1");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$seria_result = $stmt->get_result();
$seria = $seria_result->fetch_assoc();

$error_message = '';
$success_message = '';

// Handle POST request (save data)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    if (isset($_POST['nomer']) && isset($_POST['data_izgotovleniya']) && isset($_POST['srok_godnosti'])) {
        $nomer = trim($_POST['nomer']);
        $data_izgotovleniya = !empty($_POST['data_izgotovleniya']) ? $_POST['data_izgotovleniya'] : null;
        $srok_godnosti = !empty($_POST['srok_godnosti']) ? $_POST['srok_godnosti'] : null;
        
        if (empty($nomer)) {
            $error_message = 'Номер серии не может быть пустым';
        } else {
            // Check if series number already exists in the database
            $check_stmt = $mysqli->prepare("SELECT id FROM serii WHERE nomer = ?");
            $check_stmt->bind_param("s", $nomer);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $existing_seria = $check_result->fetch_assoc();
            
            if ($existing_seria) {
                // Series number exists - check if this product already has this series
                $check_product_seria = $mysqli->prepare("SELECT id FROM serii WHERE nomer = ? AND id_tovary_i_uslugi = ?");
                $check_product_seria->bind_param("si", $nomer, $product_id);
                $check_product_seria->execute();
                $product_seria_result = $check_product_seria->get_result();
                $product_seria = $product_seria_result->fetch_assoc();
                
                if ($product_seria) {
                    // This product already has this series number - just update dates
                    $update_stmt = $mysqli->prepare("UPDATE serii SET data_izgotovleniya = ?, srok_godnosti = ? WHERE id = ?");
                    $update_stmt->bind_param("ssi", $data_izgotovleniya, $srok_godnosti, $product_seria['id']);
                    if ($update_stmt->execute()) {
                        $_SESSION['success_message'] = 'Данные успешно сохранены';
                        header('Location: spisok_serii.php?product_id=' . $product_id);
                        exit();
                    } else {
                        $error_message = 'Ошибка при сохранении данных';
                    }
                } else {
                    // Series exists but for a different product - add it to this product
                    $insert_stmt = $mysqli->prepare("INSERT INTO serii (id_tovary_i_uslugi, nomer, data_izgotovleniya, srok_godnosti) VALUES (?, ?, ?, ?)");
                    $insert_stmt->bind_param("isss", $product_id, $nomer, $data_izgotovleniya, $srok_godnosti);
                    if ($insert_stmt->execute()) {
                        $_SESSION['success_message'] = 'Серия успешно добавлена';
                        header('Location: spisok_serii.php?product_id=' . $product_id);
                        exit();
                    } else {
                        $error_message = 'Ошибка при добавлении серии';
                    }
                }
            } else {
                // Series number doesn't exist - check if we're updating existing series for this product
                if ($seria) {
                    // Update existing series for this product with new number
                    $update_stmt = $mysqli->prepare("UPDATE serii SET nomer = ?, data_izgotovleniya = ?, srok_godnosti = ? WHERE id = ?");
                    $update_stmt->bind_param("sssi", $nomer, $data_izgotovleniya, $srok_godnosti, $seria['id']);
                    if ($update_stmt->execute()) {
                        $_SESSION['success_message'] = 'Данные успешно сохранены';
                        header('Location: spisok_serii.php?product_id=' . $product_id);
                        exit();
                    } else {
                        $error_message = 'Ошибка при сохранении данных';
                    }
                } else {
                    // Create completely new series
                    $insert_stmt = $mysqli->prepare("INSERT INTO serii (id_tovary_i_uslugi, nomer, data_izgotovleniya, srok_godnosti) VALUES (?, ?, ?, ?)");
                    $insert_stmt->bind_param("isss", $product_id, $nomer, $data_izgotovleniya, $srok_godnosti);
                    if ($insert_stmt->execute()) {
                        $_SESSION['success_message'] = 'Серия успешно добавлена';
                        header('Location: spisok_serii.php?product_id=' . $product_id);
                        exit();
                    } else {
                        $error_message = 'Ошибка при добавлении серии';
                    }
                }
            }
        }
    } else {
        $error_message = 'Отсутствуют обязательные поля';
    }
}


if (isset($_POST['cancel'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

include 'header.php';
?>

<div class="page-body">
    <div class="container-xl">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Редактировать серию: <?= htmlspecialchars($product['naimenovanie']) ?></h3>
            </div>

            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="max-width: 500px;">
                    <div class="mb-3" style="position: relative;">
                        <label class="form-label" for="nomer">Номер серии</label>
                        <input class="form-control" type="text" id="nomer" name="nomer" value="<?= htmlspecialchars($seria['nomer'] ?? '') ?>" placeholder="Введите серию..." autocomplete="off" required>
                        <div id="seria-dropdown" class="autocomplete-dropdown" style="display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="data_izgotovleniya">Дата выпуска</label>
                        <input class="form-control" type="date" id="data_izgotovleniya" name="data_izgotovleniya" value="<?= htmlspecialchars($seria['data_izgotovleniya'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="srok_godnosti">Срок годности</label>
                        <input class="form-control" type="date" id="srok_godnosti" name="srok_godnosti" value="<?= htmlspecialchars($seria['srok_godnosti'] ?? '') ?>">
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary"><?= $seria ? 'Сохранить' : 'Добавить' ?></button>
                        <a href="javascript:history.back()" class="btn">Отменить</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// Helper function to position dropdown using fixed positioning (same as in add_product.js)
function positionDropdown(dropdown, input) {
    const rect = input.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.left = rect.left + 'px';
    dropdown.style.top = (rect.bottom + 2) + 'px';
    dropdown.style.width = rect.width + 'px';
}

// Initialize series autocomplete on document ready
document.addEventListener('DOMContentLoaded', () => {
    const seriaInput = document.getElementById('nomer');
    const dropdown = document.getElementById('seria-dropdown');

    if (!seriaInput) return;

    seriaInput.addEventListener('input', async (e) => {
        const query = e.target.value.trim();
        
        if (query.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        try {
            const timestamp = new Date().getTime(); // Cache busting
            const url = `api/autocomplete.php?search=${encodeURIComponent(query)}&table=serii&col=nomer&id=id&t=${timestamp}`;
            
            const response = await fetch(url);
            const results = await response.json();
            
            dropdown.innerHTML = '';
            if (results && results.length > 0) {
                results.forEach(item => {
                    const option = document.createElement('div');
                    option.className = 'autocomplete-option';
                    option.textContent = item.name;
                    option.style.padding = '8px 12px';
                    option.style.cursor = 'pointer';
                    option.style.borderBottom = '1px solid #eee';
                    
                    option.addEventListener('click', () => {
                        seriaInput.value = item.name;
                        dropdown.style.display = 'none';
                    });

                    option.addEventListener('mouseover', () => {
                        option.style.backgroundColor = '#f0f0f0';
                    });
                    option.addEventListener('mouseout', () => {
                        option.style.backgroundColor = 'transparent';
                    });

                    dropdown.appendChild(option);
                });
                dropdown.style.display = 'block';
                positionDropdown(dropdown, seriaInput);
            } else {
                dropdown.style.display = 'none';
            }
        } catch (error) {
            console.error('Series autocomplete error:', error);
        }
    });

    seriaInput.addEventListener('focus', () => {
        if (dropdown.children.length > 0 && seriaInput.value.trim()) {
            dropdown.style.display = 'block';
            positionDropdown(dropdown, seriaInput);
        }
    });

    seriaInput.addEventListener('blur', () => {
        setTimeout(() => dropdown.style.display = 'none', 200);
    });

    window.addEventListener('scroll', () => {
        if (dropdown.style.display === 'block') {
            positionDropdown(dropdown, seriaInput);
        }
    });
});
</script>
