<?php

require_once(__DIR__ . '/../config/env_helper.php');
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor/econea/nusoap/src/nusoap.php');

function fetchDocumentList($vetis_guid = null)
{
    try {
        
        $possiblePaths = [
            __DIR__ . '/../../.env',       
            __DIR__ . '/../../../.env',     
            '/home/trinion-noreg/.env',    
            $_SERVER['DOCUMENT_ROOT'] . '/../.env',  
        ];

        $envPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $envPath = $path;
                break;
            }
        }

        if (!$envPath) {
            throw new Exception('.env file not found in any of the expected locations: ' . implode(', ', $possiblePaths));
        }

        $env = loadEnvFile($envPath);

        $login = $env['LOGIN'];
        $password = $env['PASSWORD'];
        $apikey = $env['API_KEY'];
        $vetis_issuerId = $env['VETIS_ISSUER_ID'];
        $user_login = $env['USER_LOGIN'];
        
        
        if (!$vetis_guid) {
            $vetis_guid = $env['VETIS_GUID'];
        }

        // Initialize SOAP client
        $client = new nusoap_client('https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService', true);
        $client->setCredentials($login, $password, 'basic');
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;

        date_default_timezone_set('Europe/Moscow');

        $soapaction = 'https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/GetStockEntryListOperation';

        $request_xml_1 = '<SOAP-ENV:Envelope xmlns:dt="http://api.vetrf.ru/schema/cdm/dictionary/v2" xmlns:bs="http://api.vetrf.ru/schema/cdm/base" xmlns:merc="http://api.vetrf.ru/schema/cdm/mercury/g2b/applications/v2" xmlns:apldef="http://api.vetrf.ru/schema/cdm/application/ws-definitions" xmlns:apl="http://api.vetrf.ru/schema/cdm/application" xmlns:vd="http://api.vetrf.ru/schema/cdm/mercury/vet-document/v2" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Header/>
  <SOAP-ENV:Body>
    <apldef:submitApplicationRequest>
      <apldef:apiKey>'.$apikey.'</apldef:apiKey>
      <apl:application>
        <apl:serviceId>mercury-g2b.service:2.1</apl:serviceId>
        <apl:issuerId>'.$vetis_issuerId.'</apl:issuerId>
        <apl:issueDate>' . date('Y-m-d\TH:i:s') . '</apl:issueDate>
        <apl:data>
          <merc:getStockEntryListRequest>
            <merc:localTransactionId>' . uniqid('TR') . '</merc:localTransactionId>
            <merc:initiator>
              <vd:login>'.$user_login.'</vd:login>
            </merc:initiator>
            <bs:listOptions>
            <bs:count>1000</bs:count>
            </bs:listOptions>
            <dt:enterpriseGuid>'.$vetis_guid.'</dt:enterpriseGuid>
            <merc:searchPattern>
              <vd:blankFilter>NOT_BLANK</vd:blankFilter>
            </merc:searchPattern>
          </merc:getStockEntryListRequest>
        </apl:data>
      </apl:application>
    </apldef:submitApplicationRequest>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

        $result_1 = $client->send($request_xml_1, $soapaction, '');

        if ($client->fault) {
            throw new Exception('SOAP Fault Step 1: ' . ($result_1['faultstring'] ?? 'Unknown error'));
        }

        $err = $client->getError();
        if ($err) {
            throw new Exception('Error Step 1: ' . $err);
        }

        $applicationId = null;
        if (is_array($result_1) && isset($result_1['application']['applicationId'])) {
            $applicationId = $result_1['application']['applicationId'];
        }

        if (!$applicationId) {
            throw new Exception('Failed to retrieve Application ID. Check UUID.');
        }

        
        try {
            $mysqli = require(__DIR__ . '/../config/database.php');
            $requestApplicationId = (string)$applicationId;
            $requestStatus = $result_1['application']['status'] ?? '';
            $requestIssueDate = $result_1['application']['issueDate'] ?? date('Y-m-d\TH:i:s');
            
            $save_sql = "INSERT INTO vetis_requests (uuid, applicationid, status, issuedate) VALUES (?, ?, ?, ?)";
            $save_stmt = $mysqli->stmt_init();
            
            if ($save_stmt->prepare($save_sql)) {
                $save_stmt->bind_param(
                    "ssss",
                    $vetis_guid,
                    $requestApplicationId,
                    $requestStatus,
                    $requestIssueDate
                );
                
                if (!$save_stmt->execute()) {
                    error_log("Error saving VETIS request: " . $save_stmt->error);
                }
                
                $save_stmt->close();
            }
        } catch (Exception $e) {
            error_log("Database error in fetchDocumentList: " . $e->getMessage());
        }
         
        
        $dbApplicationId = null;
        try {
            $mysqli = require(__DIR__ . '/../config/database.php');
            $db_sql = "SELECT applicationid FROM vetis_requests WHERE uuid = ? ORDER BY id DESC LIMIT 1";
            $db_stmt = $mysqli->stmt_init();
            
            if ($db_stmt->prepare($db_sql)) {
                $db_stmt->bind_param("s", $vetis_guid);
                $db_stmt->execute();
                $db_result = $db_stmt->get_result();
                
                if ($db_row = $db_result->fetch_assoc()) {
                    $dbApplicationId = $db_row['applicationid'];
                }
                
                $db_stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error retrieving applicationId from database: " . $e->getMessage());
        }
         
        sleep(3);
        $request_xml_2 = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://api.vetrf.ru/schema/cdm/application/ws-definitions">
   <soapenv:Header/>
   <soapenv:Body>
      <ws:receiveApplicationResultRequest>
         <ws:apiKey>' . $apikey . '</ws:apiKey>
         <ws:issuerId>' . $vetis_issuerId . '</ws:issuerId>
         <ws:applicationId>' . $dbApplicationId . '</ws:applicationId>
      </ws:receiveApplicationResultRequest>
   </soapenv:Body>
</soapenv:Envelope>';

        $result_2 = $client->send($request_xml_2, $soapaction, '');

        if ($client->fault) {
            throw new Exception('SOAP Fault Step 2: ' . ($result_2['faultstring'] ?? 'Unknown error'));
        }

        $err = $client->getError();
        if ($err) {
            throw new Exception('Error Step 2: ' . $err);
        }

        $stock_entries = $result_2['application']['result']['getStockEntryListResponse']['stockEntryList']['stockEntry'] ?? null;

        if (!$stock_entries) {
            throw new Exception('Stock entries not found in API response');
        }

        if (!is_array($stock_entries) || !isset($stock_entries[0])) {
            $stock_entries = [$stock_entries];
        }

        
        $enterprise_name = 'Не указано';
        try {
            $mysqli_ent = require(__DIR__ . '/../config/database.php');
            $ent_sql = "SELECT naimenovaniye FROM vetis_predpriyatiya WHERE enterpriseGuid = ? LIMIT 1";
            $ent_stmt = $mysqli_ent->stmt_init();
            
            if ($ent_stmt->prepare($ent_sql)) {
                $ent_stmt->bind_param("s", $vetis_guid);
                $ent_stmt->execute();
                $ent_result = $ent_stmt->get_result();
                
                if ($ent_row = $ent_result->fetch_assoc()) {
                    $enterprise_name = $ent_row['naimenovaniye'];
                }
                
                $ent_stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error retrieving enterprise name: " . $e->getMessage());
        }

        $documents_data = [];

        foreach ($stock_entries as $entry) {
            $uuid = $entry['uuid'] ?? '';
            $batch = $entry['batch'] ?? [];
            $vet_document = $entry['vetDocument'] ?? [];
            
            $product_name = '';
            if (!empty($batch['productItem']) && isset($batch['productItem']['name'])) {
                $product_name = $batch['productItem']['name'];
            }
            
            $remaining_amount = $batch['volume'] ?? 0;
            
            $unit_name = '';
            if (!empty($batch['unit']) && isset($batch['unit']['name'])) {
                $unit_name = $batch['unit']['name'];
            }
            
            $vsd_uuid = $vet_document['uuid'] ?? '';

            $documents_data[] = [
                'enterprise_name' => $enterprise_name,
                'enterprise_guid' => $vetis_guid,
                'product_name' => $product_name,
                'remaining_amount' => $remaining_amount,
                'unit' => $unit_name,
                'uuid' => $uuid,
                'vsd_uuid' => $vsd_uuid
            ];
        }

        return [
            'success' => true,
            'data' => $documents_data
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}