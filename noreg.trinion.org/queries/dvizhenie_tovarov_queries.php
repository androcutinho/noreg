<?php
function getUniqueTovary($mysqli) {
    $sql = "SELECT DISTINCT tiu.naimenovanie FROM tovary_i_uslugi tiu
            INNER JOIN ostatki_tovarov o ON o.id_tovary_i_uslugi = tiu.id
            WHERE tiu.naimenovanie IS NOT NULL 
            ORDER BY tiu.naimenovanie";
    
    $result = $mysqli->query($sql);
    $tovary = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tovary[] = $row['naimenovanie'];   
        }
    }
    
    return $tovary;
}

function getDokumenty($mysqli, $tovar_filter = null, $data_s = null, $data_do = null) {
    $dokumenty = [];
    
    
    $tovar_where_postavka = $tovar_filter ? " AND tiu.naimenovanie = '" . $mysqli->real_escape_string($tovar_filter) . "'" : "";
    $tovar_where_otgruzka = $tovar_filter ? " AND tiu.naimenovanie = '" . $mysqli->real_escape_string($tovar_filter) . "'" : "";
    $tovar_where_izmenenie = $tovar_filter ? " AND tiu.naimenovanie = '" . $mysqli->real_escape_string($tovar_filter) . "'" : "";
    
    
    $date_where = "";
    
    if ($data_s) {
        $date_where .= " AND DATE(data_dokumenta) >= '" . $mysqli->real_escape_string($data_s) . "'";
    }
    if ($data_do) {
        $date_where .= " AND DATE(data_dokumenta) <= '" . $mysqli->real_escape_string($data_do) . "'";
    }
    
   
    $sql = "(SELECT 
                     p.id,
                     p.data_dokumenta,
                     'Поступление товара' as tip_dokumenta,
                     sd.kolichestvo as plius,
                     0 as minus,
                     p.utverzhden,
                     p.id_sklada,
                     sk.naimenovanie AS sklad,
                     tiu.naimenovanie AS tovar
                FROM postupleniya_tovarov p
                INNER JOIN stroki_dokumentov sd ON sd.id_index = p.id_index
                INNER JOIN tovary_i_uslugi tiu ON tiu.id = sd.id_tovary_i_uslugi
                INNER JOIN sklady sk ON sk.id= p.id_sklada
                WHERE p.utverzhden = true $tovar_where_postavka $date_where)
            UNION ALL
            (SELECT 
                     o.id,
                     o.data_dokumenta,
                     'Отгрузка товара' as tip_dokumenta,
                     0 as plius,
                     sd.kolichestvo as minus,
                     o.utverzhden,
                     sd.id_sklada, 
                     sk.naimenovanie AS sklad,
                     tiu.naimenovanie AS tovar
                FROM otgruzki_tovarov_pokupatelyam o
                INNER JOIN stroki_dokumentov sd ON sd.id_index = o.id_index
                INNER JOIN tovary_i_uslugi tiu ON tiu.id = sd.id_tovary_i_uslugi
                INNER JOIN sklady sk ON sk.id= sd.id_sklada
                WHERE o.utverzhden = true $tovar_where_otgruzka $date_where)
            UNION ALL
            (SELECT 
                     i.id,
                     i.data_dokumenta,
                     'Изменение остатка товара' as tip_dokumenta,
                     sd.pribavit as plius,
                     sd.ubavit as minus,
                     i.utverzhden,
                     i.id_sklada, 
                     sk.naimenovanie AS sklad,
                     tiu.naimenovanie AS tovar
                FROM izmenenie_ostatka_tovarov i
                INNER JOIN stroki_dokumentov sd ON sd.id_index = i.id_index
                INNER JOIN tovary_i_uslugi tiu ON tiu.id = sd.id_tovary_i_uslugi
                INNER JOIN sklady sk ON sk.id= i.id_sklada
                WHERE i.utverzhden = true $tovar_where_izmenenie $date_where)
            ORDER BY id_sklada ASC, data_dokumenta ASC";
    
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dokumenty[] = $row;
        }
    }
    
    return $dokumenty;
}

function calculateItogo(&$dokumenty, $mysqli) {
    
    $groups = [];
    foreach ($dokumenty as $key => $doc) {
        $group_key = $doc['id_sklada'] . '_' . $doc['tovar'];
        if (!isset($groups[$group_key])) {
            $groups[$group_key] = [];
        }
        $groups[$group_key][] = $key;
    }
    
    foreach ($groups as $group_key => $keys) {
        
        $latest_key = end($keys);
        
        list($id_sklada, $tovar_name) = explode('_', $group_key, 2);
        
        $sql = "SELECT ostatok FROM ostatki_tovarov 
                WHERE id_sklady = " . intval($id_sklada) . " 
                AND id_tovary_i_uslugi = (SELECT id FROM tovary_i_uslugi WHERE naimenovanie = '" . $mysqli->real_escape_string($tovar_name) . "')
                LIMIT 1";
        
        $result = $mysqli->query($sql);
        $latest_ostatok = 0;
        
        if ($result && $row = $result->fetch_assoc()) {
            $latest_ostatok = $row['ostatok'];
        }
        
        $dokumenty[$latest_key]['itogo'] = $latest_ostatok;
        
        $reverse_keys = array_reverse($keys);
        for ($i = 1; $i < count($reverse_keys); $i++) {
            $current_key = $reverse_keys[$i];
            $next_key = $reverse_keys[$i - 1];
            
            $next_itogo = $dokumenty[$next_key]['itogo'];
            $next_plius = $dokumenty[$next_key]['plius'];
            $next_minus = $dokumenty[$next_key]['minus'];
            
            $dokumenty[$current_key]['itogo'] = $next_itogo - $next_plius + $next_minus;
        }
    }
}
?>