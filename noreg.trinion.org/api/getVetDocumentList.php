<?php

require_once(__DIR__ . '/../config/env_helper.php');
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor/econea/nusoap/src/nusoap.php');

function fetchDocumentList()
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
        $vetis_guid = $env['VETIS_GUID'];

        // Initialize SOAP client
        $client = new nusoap_client('https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService', true);
        $client->setCredentials($login, $password, 'basic');
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;

        date_default_timezone_set('Europe/Moscow');

        $soapaction = 'https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/GetVetDocumentListOperation';

        $request_xml_1 = '<SOAP-ENV:Envelope xmlns:dt="http://api.vetrf.ru/schema/cdm/dictionary/v2" xmlns:bs="http://api.vetrf.ru/schema/cdm/base" xmlns:merc="http://api.vetrf.ru/schema/cdm/mercury/g2b/applications/v2" xmlns:apldef="http://api.vetrf.ru/schema/cdm/application/ws-definitions" xmlns:apl="http://api.vetrf.ru/schema/cdm/application" xmlns:vd="http://api.vetrf.ru/schema/cdm/mercury/vet-document/v2" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Header/>
  <SOAP-ENV:Body>
    <apldef:submitApplicationRequest>
      <apldef:apiKey>' . $apikey . '</apldef:apiKey>
      <apl:application>
        <apl:serviceId>mercury-g2b.service:2.1</apl:serviceId>
        <apl:issuerId>' . $vetis_issuerId . '</apl:issuerId>
        <apl:issueDate>' . date('Y-m-d\TH:i:s') . '</apl:issueDate>
        <apl:data>
          <merc:getVetDocumentListRequest>
            <merc:localTransactionId>' . uniqid('TR') . '</merc:localTransactionId>
            <merc:initiator>
              <vd:login>' . $user_login . '</vd:login>
            </merc:initiator>
            <bs:listOptions>
              <bs:count>1000</bs:count>
              <bs:offset>0</bs:offset>
            </bs:listOptions>
            <vd:vetDocumentStatus>CONFIRMED</vd:vetDocumentStatus>
            <dt:enterpriseGuid>' . $vetis_guid . '</dt:enterpriseGuid>
          </merc:getVetDocumentListRequest>
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

        $vet_documents = $result_2['application']['result']['getVetDocumentListResponse']['vetDocumentList']['vetDocument'] ?? null;

        if (!$vet_documents) {
            throw new Exception('Documents not found in API response');
        }

        if (!is_array($vet_documents) || !isset($vet_documents[0])) {
            $vet_documents = [$vet_documents];
        }

        $documents_data = [];

        foreach ($vet_documents as $vet_doc) {
            $doc_uuid = $vet_doc['uuid'] ?? '';
            $date_issued = $vet_doc['issueDate'] ?? '';
            $type = $vet_doc['vetDType'] ?? '';
            $status = $vet_doc['vetDStatus'] ?? '';
            $last_update = $vet_doc['lastUpdateDate'] ?? '';
            $shipper_name = $vet_doc['certifiedConsignment']['consignor']['enterprise']['name'] ?? 'Не указано';
            
            
            
            $consignee_enterprise = $vet_doc['certifiedConsignment']['consignee']['enterprise'] ?? [];
            $receiver_name = (!empty($consignee_enterprise) && isset($consignee_enterprise['name'])) 
                ? $consignee_enterprise['name'] 
                : 'Не указано';
            
            $batch = $vet_doc['certifiedConsignment']['batch'] ?? [];
            $product_name = '';
            if (!empty($batch['productItem']) && isset($batch['productItem']['name'])) {
                $product_name = $batch['productItem']['name'];
            }
            
            $prod_date = '';
            if (isset($batch['dateOfProduction']['firstDate'])) {
                $pd = $batch['dateOfProduction']['firstDate'];
                $prod_date = $pd['year'] . '-' . str_pad($pd['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($pd['day'], 2, '0', STR_PAD_LEFT);
            }

            $exp_date = '';
            if (isset($batch['expiryDate']['firstDate'])) {
                $ed = $batch['expiryDate']['firstDate'];
                $exp_date = $ed['year'] . '-' . str_pad($ed['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($ed['day'], 2, '0', STR_PAD_LEFT);
            }

            $documents_data[] = [
                'uuid' => $doc_uuid,
                'issueDate' => $date_issued,
                'vetDType' => $type,
                'vetDStatus' => $status,
                'lastUpdateDate' => $last_update,
                'dateOfProduction' => $prod_date,
                'expiryDate' => $exp_date,
                'enterprise' => $shipper_name,
                'consignee' => $receiver_name,
                'naimenovanie_tovara' => $product_name
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