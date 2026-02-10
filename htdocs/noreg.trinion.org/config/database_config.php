<?php

define('stavki_nds', 'stavki_nds');
define('postupleniya_tovarov', 'postupleniya_tovarov');
define('stroki_dokumentov', 'stroki_dokumentov');
define('serii', 'serii');
define('sklady', 'sklady');
define('organizacii', 'organizacii');
define('kontragenti', 'kontragenti');
define('tovary_i_uslugi', 'tovary_i_uslugi');
define('users', 'users');
define('edinicy_izmereniya', 'edinicy_izmereniya');
define('noreg_specifikacii_k_dogovoru', 'noreg_specifikacii_k_dogovoru');
define('sotrudniki', 'sotrudniki');


define('COL_NDS_ID', 'id');
define('stavka_nds', 'stavka_nds');

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
define('COL_LINE_PLANNED_DELIVERY_DATE', 'planiruemaya_data_postavki');


// Column names - Series
define('COL_SERIES_ID', 'id');
define('COL_SERIES_NUMBER', 'nomer');
define('serii_id_tovary_i_uslugi', 'id_tovary_i_uslugi');
define('data_izgotovleniya', 'data_izgotovleniya');
define('srok_godnosti', 'srok_godnosti');

// Warehouse columns
define('COL_WAREHOUSE_ID', 'id');
define('COL_WAREHOUSE_NAME', 'naimenovanie');

// Organization columns
define('COL_ORG_ID', 'id');
define('COL_ORG_NAME', 'naimenovanie');
define('org_inn', 'INN');
define('org_kpp', 'KPP');
define('org_yuridicheskij_adress', 'yuridicheskij_adress');
define('org_pochtovyj_adress', 'pochtovyj_adress');
define('org_ogrn', 'OGRN');
define('org_polnoe_naimenovanie_organizacii', 'polnoe_naimenovanie_organizacii');
define('org_sokrashchyonnoe_naimenovanie', 'sokrashchyonnoe_naimenovanie');
define('org_v_lice_dlya_documentov', 'v_lice_dlya_documentov');


// kontragenti columns
define('kon_id', 'id');
define('kon_naimenovanie', 'naimenovanie');
define('kon_inn', 'INN');
define('kon_kpp', 'KPP');
define('kon_yuridicheskij_adress', 'yuridicheskij_adress');
define('kon_pochtovyj_adress', 'pochtovyj_adress');
define('kon_orgn', 'OGRN');
define('kon_polnoe_naimenovanie_organizacii', 'polnoe_naimenovanie_organizacii');
define('kon_sokrashchyonnoe_naimenovanie', 'sokrashchyonnoe_naimenovanie');
define('kon_v_lice_dlya_documentov', 'v_lice_dlya_documentov');

// Product columns
define('COL_PRODUCT_ID', 'id');
define('COL_PRODUCT_NAME', 'naimenovanie');


// User columns
define('user_id', 'user_id');
define('user_name', 'user_name');
define('email', 'email');
define('user_password_hash', 'user_password_hash');
define('user_role', 'role');

// Unit columns
define('COL_UNIT_ID', 'id');
define('COL_UNIT_NAME', 'naimenovanie');

// noreg_specifikacii_k_dogovoru columns
define('noreg_specifikacii_k_dogovoru_id','id');
define('id_kontragenti','id_kontragenti');
define('id_organizacii','id_organizacii');
define('gorod','gorod');
define('usloviya_otgruzki','usloviya_otgruzki');
define('usloviya_oplaty','usloviya_oplaty');
define('inye_usloviya','inye_usloviya');
define('nomer_specifikacii','nomer_specifikacii');
define('nomer_dogovora','nomer_dogovora');
define('data_dogovora','data_dogovora');
define('preambula','preambula');
define('id_sotrudniki','id_sotrudniki');
define('podpisant_postavshchika_dolzhnost','podpisant_postavshchika_dolzhnost');
define('podpisant_postavshchika_fio','podpisant_postavshchika_fio');

// sotrudniki  columns
define('sotrudnika_id','id');
define('familiya','familiya');
define('imya','imya');
define('otchestvo','otchestvo');
define('dolgnost','dolgnost');

?>
