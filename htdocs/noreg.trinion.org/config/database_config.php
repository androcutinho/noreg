<?php

define('TABLE_NDS_RATES', 'stavki_nds');
define('TABLE_ARRIVALS', 'postupleniya_tovarov');
define('TABLE_DOCUMENT_LINES', 'stroki_dokumentov');
define('TABLE_SERIES', 'serii');
define('TABLE_WAREHOUSES', 'sklady');
define('TABLE_ORGANIZATIONS', 'organizacii');
define('TABLE_VENDORS', 'postavshchiki');
define('TABLE_PRODUCTS', 'tovary_i_uslugi');
define('TABLE_USERS', 'users');
define('TABLE_UNITS', 'edinicy_izmereniya');

define('COL_NDS_ID', 'id');
define('COL_NDS_RATE', 'stavka_nds');

// Column names - Arrivals
define('COL_ARRIVAL_ID', 'id');
define('COL_ARRIVAL_VENDOR_ID', 'id_postavshchika');
define('COL_ARRIVAL_ORG_ID', 'id_organizacii');
define('COL_ARRIVAL_WAREHOUSE_ID', 'id_sklada');
define('COL_ARRIVAL_RESPONSIBLE_ID', 'id_otvetstvennyj');
define('COL_ARRIVAL_DATE', 'data_dokumenta');
define('COL_ARRIVAL_COMMENT', 'kommentarij');
define('COL_ARRIVAL_NUMBER', 'nomer');

// Column names - Document Lines
define('COL_LINE_DOCUMENT_ID', 'id_dokumenta');
define('COL_LINE_PRODUCT_ID', 'id_tovary_i_uslugi');
define('COL_LINE_NDS_ID', 'id_stavka_nds');
define('COL_LINE_PRICE', 'cena');
define('COL_LINE_QUANTITY', 'kolichestvo');
define('COL_LINE_SUMMA', 'summa');
define('COL_LINE_NDS_AMOUNT', 'summa_nds');
define('COL_LINE_UNIT_ID', 'id_edinicy_izmereniya');
define('COL_LINE_SERIES_ID', 'id_serii');


// Column names - Series
define('COL_SERIES_ID', 'id');
define('COL_SERIES_PRODUCT_ID', 'id_tovary_i_uslugi');

// Warehouse columns
define('COL_WAREHOUSE_ID', 'id');
define('COL_WAREHOUSE_NAME', 'naimenovanie');

// Organization columns
define('COL_ORG_ID', 'id');
define('COL_ORG_NAME', 'naimenovanie');

// Vendor columns
define('COL_VENDOR_ID', 'id');
define('COL_VENDOR_NAME', 'naimenovanie');
define('COL_VENDOR_INN', 'INN');

// Product columns
define('COL_PRODUCT_ID', 'id');
define('COL_PRODUCT_NAME', 'naimenovanie');

// Series columns
define('COL_SERIES_NAME', 'naimenovanie');

// User columns
define('COL_USER_ID', 'id');
define('COL_USER_NAME', 'user_name');
define('COL_USER_ROLE', 'role');

// Unit columns
define('COL_UNIT_ID', 'id');
define('COL_UNIT_NAME', 'naimenovanie');

?>
