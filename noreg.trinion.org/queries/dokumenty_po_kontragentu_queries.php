<?php
function getUniqueKontragenty($mysqli) {
    $sql = "SELECT DISTINCT k.naimenovanie FROM kontragenti k
            WHERE k.naimenovanie IS NOT NULL 
            ORDER BY k.naimenovanie";
    
    $result = $mysqli->query($sql);
    $kontragenty = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $kontragenty[] = $row['naimenovanie'];   
        }
    }
    
    return $kontragenty;
}

function getDokumenty($mysqli, $kontragent_filter = null) {
    $dokumenty = [];
    
    
    $kontragent_id = null;
    if ($kontragent_filter) {
        $sql_id = "SELECT id FROM kontragenti WHERE naimenovanie = '" . $mysqli->real_escape_string($kontragent_filter) . "' LIMIT 1";
        $result_id = $mysqli->query($sql_id);
        if ($result_id && $row_id = $result_id->fetch_assoc()) {
            $kontragent_id = $row_id['id'];
        }
    }
    
    
    if (!$kontragent_id) {
        return $dokumenty;
    }
    

    $where_clause_postavka = "(p.id_kontragenti_pokupatel = $kontragent_id OR p.id_kontragenti_postavshik = $kontragent_id)";
    $where_clause_otgruzka = "(o.id_kontragenti_pokupatel = $kontragent_id OR o.id_kontragenti_postavshik = $kontragent_id)";
    $where_clause_zp = "(zp.id_kontragenti_pokupatel = $kontragent_id OR zp.id_kontragenti_postavshik = $kontragent_id)";
    $where_clause_zpa = "(zpa.id_kontragenti_pokupatel = $kontragent_id OR zpa.id_kontragenti_postavshchik = $kontragent_id)";
    $where_clause_sp = "(sp.id_kontragenti_pokupatel = $kontragent_id OR sp.id_kontragenti_postavshik = $kontragent_id)";
    $where_clause_sno = "(sno.id_kontragenti_pokupatel = $kontragent_id OR sno.id_kontragenti_postavshik = $kontragent_id)";

    $sql = "(SELECT 
                     p.id,
                     p.data_dokumenta,
                     'Поступление товара' as tip_dokumenta,
                     p.utverzhden,
                     p.zakryt,
                     k.naimenovanie AS kontragent,
                     (sd.summa + sd.summa_nds) AS total_summa
                FROM postupleniya_tovarov p
                INNER JOIN stroki_dokumentov sd ON sd.id_index = p.id_index
                INNER JOIN kontragenti k ON k.id = $kontragent_id
                WHERE $where_clause_postavka )
            UNION ALL
            (SELECT 
                     o.id,
                     o.data_dokumenta,
                     'Отгрузка товара' as tip_dokumenta,
                     o.utverzhden,
                     o.zakryt,
                     k.naimenovanie AS kontragent, 
                     (sd.summa + sd.summa_nds) AS total_summa
                FROM otgruzki_tovarov_pokupatelyam o
                INNER JOIN stroki_dokumentov sd ON sd.id_index = o.id_index
                INNER JOIN kontragenti k ON k.id = $kontragent_id
                WHERE $where_clause_otgruzka)
            UNION ALL
            (SELECT 
                     zp.id,
                     zp.data_dokumenta,
                     'Заказ покупателя' as tip_dokumenta,
                     zp.utverzhden,
                     zp.zakryt,
                     k.naimenovanie AS kontragent, 
                     (sd.summa + sd.summa_nds) AS total_summa
                FROM zakazy_pokupatelei zp
                INNER JOIN stroki_dokumentov sd ON sd.id_index = zp.id_index
                INNER JOIN kontragenti k ON k.id = $kontragent_id
                WHERE $where_clause_zp)
            UNION ALL
            (SELECT 
                     zpa.id,
                     zpa.data_dokumenta,
                     'Заказ поставщику' as tip_dokumenta,
                     zpa.utverzhden,
                     zpa.zakryt,
                     k.naimenovanie AS kontragent, 
                     (sd.summa + sd.summa_nds) AS total_summa
                FROM zakazy_postavshchikam zpa
                INNER JOIN stroki_dokumentov sd ON sd.id_index = zpa.id_index
                INNER JOIN kontragenti k ON k.id = $kontragent_id
                WHERE $where_clause_zpa)
            UNION ALL
            (SELECT 
                     sp.id,
                     sp.data_dogovora as data_dokumenta,
                     'Спецификация' as tip_dokumenta,
                     sp.utverzhden,
                     sp.zakryt,
                     k.naimenovanie AS kontragent, 
                     (sd.summa + sd.summa_nds) AS total_summa
                FROM noreg_specifikacii_k_zakazam sp
                INNER JOIN stroki_dokumentov sd ON sd.id_index = sp.id_index
                INNER JOIN kontragenti k ON k.id = $kontragent_id
                WHERE $where_clause_sp)
            UNION ALL
            (SELECT 
                     sno.id,
                     sno.data_dokumenta,
                     'Счет на оплату' as tip_dokumenta,
                     sno.utverzhden,
                     sno.zakryt,
                     k.naimenovanie AS kontragent, 
                     (sd.summa + sd.summa_nds) AS total_summa
                FROM scheta_na_oplatu sno
                INNER JOIN stroki_dokumentov sd ON sd.id_index = sno.id_index
                INNER JOIN kontragenti k ON k.id = $kontragent_id
                WHERE $where_clause_sno)           

            ORDER BY data_dokumenta ASC";
    
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dokumenty[] = $row;
        }
    }
    
    return $dokumenty;
}
?>
