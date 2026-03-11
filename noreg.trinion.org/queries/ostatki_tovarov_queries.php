<?php
function getSummaTovarov($mysqli) {
    $sql = "SELECT COUNT(DISTINCT ot.id) as total FROM ostatki_tovarov ot";
    $result = $mysqli->query($sql);
    
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    
    return 0;
}

function getVceTovary($mysqli, $sklad_filter = null, $tovar_filter = null) {
    $sql = "SELECT 
        ot.id,
        ot.ostatok,
        tiu.naimenovanie as naimenovanie_tovara,
        s.nomer,
        sk.naimenovanie as sklad
    FROM ostatki_tovarov ot
    LEFT JOIN tovary_i_uslugi tiu ON ot.id_tovary_i_uslugi = tiu.id
    LEFT JOIN serii s ON ot.id_serii = s.id
    LEFT JOIN sklady sk ON ot.id_sklady = sk.id
    WHERE 1=1";
    
    if ($sklad_filter !== null && $sklad_filter !== '') {
        $sql .= " AND sk.naimenovanie = ?";
    }
    
    if ($tovar_filter !== null && $tovar_filter !== '') {
        $sql .= " AND tiu.naimenovanie = ?";
    }
    
    $sql .= " GROUP BY ot.id ORDER BY ot.id DESC";
    
    if ($sklad_filter !== null || $tovar_filter !== null) {
        $stmt = $mysqli->prepare($sql);
        $params = [];
        $types = '';
        
        if ($sklad_filter !== null && $sklad_filter !== '') {
            $params[] = &$sklad_filter;
            $types .= 's';
        }
        
        if ($tovar_filter !== null && $tovar_filter !== '') {
            $params[] = &$tovar_filter;
            $types .= 's';
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $mysqli->query($sql);
    }
    
    $tovary = array();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tovary[] = $row;
        }
    }
    
    return $tovary;
}

function getUniqueSklady($mysqli) {
    $sql = "SELECT DISTINCT sk.naimenovanie FROM ostatki_tovarov ot
            LEFT JOIN sklady sk ON ot.id_sklady = sk.id
            WHERE sk.naimenovanie IS NOT NULL
            ORDER BY sk.naimenovanie";
    
    $result = $mysqli->query($sql);
    $sklady = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sklady[] = $row['naimenovanie'];
        }
    }
    
    return $sklady;
}

function getUniqueTovary($mysqli) {
    $sql = "SELECT DISTINCT tiu.naimenovanie FROM ostatki_tovarov ot
            LEFT JOIN tovary_i_uslugi tiu ON ot.id_tovary_i_uslugi = tiu.id
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
?>
