<?php
$mysqli = new mysqli('localhost', 'root', '', 'noreg');

// Get last shipment
$result = $mysqli->query("SELECT id, id_index, nomer FROM otgruzki_tovarov_pokupatelyam ORDER BY id DESC LIMIT 1");
$lastShipment = $result->fetch_assoc();

echo "\n=== LAST SHIPMENT ===\n";
echo "ID: " . $lastShipment['id'] . "\n";
echo "ID_INDEX: " . $lastShipment['id_index'] . "\n";
echo "NOMER: " . $lastShipment['nomer'] . "\n\n";

// Get products in that shipment
echo "=== PRODUCTS IN SHIPMENT (id_index=" . $lastShipment['id_index'] . ") ===\n";
$result = $mysqli->query("SELECT id_tovary_i_uslugi, kolichestvo, summa FROM stroki_dokumentov WHERE id_dokumenta = " . $lastShipment['id_index']);
while ($row = $result->fetch_assoc()) {
    echo "Product ID: " . $row['id_tovary_i_uslugi'] . ", Qty: " . $row['kolichestvo'] . ", Sum: " . $row['summa'] . "\n";
}
?>
