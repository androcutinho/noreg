<?php

function getStockEntriesByVSD($mysqli)
{
    try {
        $sql = "SELECT 
                    vo.id,
                    vo.vetis_uuid,
                    vo.ostatok,
                    vp.naimenovaniye AS predpriyataya_naimenovanie,
                    tu.naimenovanie AS naimenovanie_tovara,
                    eu.naimenovanie AS ed_naimenovanie
                FROM vetis_ostatki vo
                LEFT JOIN vetis_predpriyatiya vp ON vo.id_predpriyatiya = vp.id
                LEFT JOIN tovary_i_uslugi tu ON vo.id_tovary_i_uslugi = tu.id
                LEFT JOIN edinicy_izmereniya eu ON vo.id_edinicy_izmereniya = eu.id
                ORDER BY vp.naimenovaniye, tu.naimenovanie";
        
        $result = $mysqli->query($sql);
        
        if (!$result) {
            throw new Exception('Database query error: ' . $mysqli->error);
        }
        
        $entries = [];
        while ($row = $result->fetch_assoc()) {
            $entries[] = [
                'id' => $row['id'],
                'predpriyataya_naimenovanie' => $row['predpriyataya_naimenovanie'] ?? 'Не указано',
                'naimenovanie_tovara' => $row['naimenovanie_tovara'] ?? 'Не указано',
                'vsd_uuid' => $row['vetis_uuid'] ?? 'Не указано',
                'ostatok' => $row['ostatok'] ?? 0,
                'ed_naimenovanie' => $row['ed_naimenovanie'] ?? ''
            ];
        }
        
        return [
            'success' => true,
            'data' => $entries
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function getStockEntriesByProduct($mysqli)
{
    try {
        $sql = "SELECT 
                    tu.naimenovanie AS naimenovanie_tovara,
                    vp.naimenovaniye AS predpriyataya_naimenovanie,
                    SUM(vo.ostatok) AS summa_ostatok
                FROM vetis_ostatki vo
                LEFT JOIN vetis_predpriyatiya vp ON vo.id_predpriyatiya = vp.id
                LEFT JOIN tovary_i_uslugi tu ON vo.id_tovary_i_uslugi = tu.id
                GROUP BY tu.naimenovanie, vp.naimenovaniye
                ORDER BY tu.naimenovanie, vp.naimenovaniye";
        
        $result = $mysqli->query($sql);
        
        if (!$result) {
            throw new Exception('Database query error: ' . $mysqli->error);
        }
        
        $entries = [];
        while ($row = $result->fetch_assoc()) {
            $entries[] = [
                'naimenovanie_tovara' => $row['naimenovanie_tovara'] ?? 'Не указано',
                'predpriyataya_naimenovanie' => $row['predpriyataya_naimenovanie'] ?? 'Не указано',
                'summa_ostatok' => $row['summa_ostatok'] ?? 0
            ];
        }
        
        return [
            'success' => true,
            'data' => $entries
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
