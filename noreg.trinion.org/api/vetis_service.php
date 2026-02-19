<?php

require_once(__DIR__ . '/../config/env_helper.php');
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor/econea/nusoap/src/nusoap.php');

function fetchVetisDocument($uuid)
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
            throw new Exception('.env файл не найден в следующих директориях: ' . implode(', ', $possiblePaths));
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

        $soapaction = 'https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/GetVetDocumentByUuidOperation';

        $request_xml_1 = '
<SOAP-ENV:Envelope xmlns:dt="http://api.vetrf.ru/schema/cdm/dictionary/v2" xmlns:bs="http://api.vetrf.ru/schema/cdm/base" xmlns:merc="http://api.vetrf.ru/schema/cdm/mercury/g2b/applications/v2" xmlns:apldef="http://api.vetrf.ru/schema/cdm/application/ws-definitions" xmlns:apl="http://api.vetrf.ru/schema/cdm/application" xmlns:vd="http://api.vetrf.ru/schema/cdm/mercury/vet-document/v2" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Header/>
  <SOAP-ENV:Body>
    <apldef:submitApplicationRequest>
      <apldef:apiKey>'.$apikey.'</apldef:apiKey>
      <apl:application>
        <apl:serviceId>mercury-g2b.service:2.1</apl:serviceId>
        <apl:issuerId>'.$vetis_issuerId.'</apl:issuerId>
        <apl:issueDate>' . date('Y-m-d\TH:i:s') . '</apl:issueDate>
        <apl:data>
          <merc:getVetDocumentByUuidRequest>
            <merc:localTransactionId>' . uniqid('TR') . '</merc:localTransactionId>
            <merc:initiator>
              <vd:login>'.$user_login.'</vd:login>
            </merc:initiator>
            <bs:uuid>'.$uuid.'</bs:uuid>
            <dt:enterpriseGuid>'.$vetis_guid.'</dt:enterpriseGuid>
          </merc:getVetDocumentByUuidRequest>
        </apl:data>
      </apl:application>
    </apldef:submitApplicationRequest>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

        $result_1 = $client->send($request_xml_1, $soapaction, '');

        if ($client->fault) {
            throw new Exception('SOAP Ошибка Шаг 1: ' . ($result_1['faultstring'] ?? 'Неизвестная ошибка'));
        }

        $err = $client->getError();
        if ($err) {
            throw new Exception('Ошибка Шаг 1: ' . $err);
        }

        $applicationId = null;
        if (is_array($result_1) && isset($result_1['application']['applicationId'])) {
            $applicationId = $result_1['application']['applicationId'];
        }

        if (!$applicationId) {
            throw new Exception('Не удалось получить Application ID. Проверьте UUID.');
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
                    $uuid,
                    $requestApplicationId,
                    $requestStatus,
                    $requestIssueDate
                );
                
                if (!$save_stmt->execute()) {
                    error_log("Ошибка сохранения VETIS запроса: " . $save_stmt->error);
                }
                
                $save_stmt->close();
            }
        } catch (Exception $e) {
            error_log("Ошибка БД в fetchVetisDocument: " . $e->getMessage());
        }
         
        
        $dbApplicationId = null;
        try {
            $mysqli = require(__DIR__ . '/../config/database.php');
            $db_sql = "SELECT applicationid FROM vetis_requests WHERE uuid = ? ORDER BY id DESC LIMIT 1";
            $db_stmt = $mysqli->stmt_init();
            
            if ($db_stmt->prepare($db_sql)) {
                $db_stmt->bind_param("s", $uuid);
                $db_stmt->execute();
                $db_result = $db_stmt->get_result();
                
                if ($db_row = $db_result->fetch_assoc()) {
                    $dbApplicationId = $db_row['applicationid'];
                }
                
                $db_stmt->close();
            }
        } catch (Exception $e) {
            error_log("Ошибка получения applicationId из БД: " . $e->getMessage());
        }
         
        
        sleep(5);
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
            throw new Exception('SOAP Ошибка Шаг 2: ' . ($result_2['faultstring'] ?? 'Неизвестная ошибка'));
        }

        $err = $client->getError();
        if ($err) {
            throw new Exception('Ошибка Шаг 2: ' . $err);
        }

        $vet_doc = $result_2['application']['result']['getVetDocumentByUuidResponse']['vetDocument'] ?? null;

        if (!$vet_doc) {
            throw new Exception('Документ не найден в ответе API');
        }

        $doc_uuid = $vet_doc['uuid'] ?? '';
        $date_issued = $vet_doc['issueDate'] ?? '';
        $form = $vet_doc['vetDForm'] ?? '';
        $type = $vet_doc['vetDType'] ?? '';
        $status = $vet_doc['vetDStatus'] ?? '';
        $last_update = $vet_doc['lastUpdateDate'] ?? '';
        $shipper_name = $vet_doc['certifiedConsignment']['consignor']['enterprise']['name'] ?? 'Не указано';
        $shipper_uuid = $vet_doc['certifiedConsignment']['consignor']['enterprise']['uuid'] ?? '';
        $shipper_guid = $vet_doc['certifiedConsignment']['consignor']['enterprise']['guid'] ?? '';
        $shipper_business_guid = $vet_doc['certifiedConsignment']['consignor']['businessEntity']['guid'] ?? '';
        $receiver_name = $vet_doc['certifiedConsignment']['consignee']['enterprise']['name'] ?? 'Не указано';
        $receiver_uuid = $vet_doc['certifiedConsignment']['consignee']['enterprise']['uuid'] ?? '';
        $receiver_guid = $vet_doc['certifiedConsignment']['consignee']['enterprise']['guid'] ?? '';
        $receiver_business_guid = $vet_doc['certifiedConsignment']['consignee']['businessEntity']['guid'] ?? '';
        $vehicle_number = $vet_doc['certifiedConsignment']['transportInfo']['transportNumber']['vehicleNumber'] ?? '';
        $trailer_number = $vet_doc['certifiedConsignment']['transportInfo']['transportNumber']['trailerNumber'] ?? '';
        $storage_type = $vet_doc['certifiedConsignment']['transportStorageType'] ?? '';
        $transportType = $vet_doc['certifiedConsignment']['transportInfo']['transportType'] ?? '1';
        $broker_guid = $vet_doc['certifiedConsignment']['broker']['guid'] ?? '';
        
        $batch = $vet_doc['certifiedConsignment']['batch'] ?? [];
        $productType = $batch['productType'] ?? '2';
        $product_name = $batch['productItem']['name'] ?? '';
        $product_guid = $batch['productItem']['guid'] ?? '';
        $product_uuid = $batch['product']['uuid'] ?? '';
        $subProduct_guid = $batch['subProduct']['guid'] ?? '';
        $subProduct_uuid = $batch['subProduct']['uuid'] ?? '';
        $productItem_guid = $batch['productItem']['guid'] ?? '';
        $volume = $batch['volume'] ?? '';
        $unit_name = $batch['unit']['name'] ?? '';
        $unit_guid = $batch['unit']['guid'] ?? '';
        $unit_uuid = $batch['unit']['uuid'] ?? '';
        $batchID = $batch['batchID'] ?? '';
        $perishable = $batch['perishable'] ?? false;
        $lowGradeCargo = $batch['lowGradeCargo'] ?? false;
        
        $package_type = $batch['packageList']['package']['packingType']['name'] ?? '';
        $package_quantity = $batch['packageList']['package']['quantity'] ?? '';
        
        
        $prod_date = '';
        $prod_date_year = date('Y');
        $prod_date_month = date('m');
        $prod_date_day = date('d');
        if (isset($batch['dateOfProduction']['firstDate'])) {
            $pd = $batch['dateOfProduction']['firstDate'];
            $prod_date_year = $pd['year'] ?? date('Y');
            $prod_date_month = str_pad($pd['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
            $prod_date_day = str_pad($pd['day'] ?? date('d'), 2, '0', STR_PAD_LEFT);
            $prod_date = $prod_date_year . '-' . $prod_date_month . '-' . $prod_date_day;
        }

        
        $exp_date = '';
        $exp_date_year = date('Y');
        $exp_date_month = date('m');
        $exp_date_day = date('d');
        if (isset($batch['expiryDate']['firstDate'])) {
            $ed = $batch['expiryDate']['firstDate'];
            $exp_date_year = $ed['year'] ?? date('Y');
            $exp_date_month = str_pad($ed['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
            $exp_date_day = str_pad($ed['day'] ?? date('d'), 2, '0', STR_PAD_LEFT);
            $exp_date = $exp_date_year . '-' . $exp_date_month . '-' . $exp_date_day;
        }
        
        $country_guid = $batch['origin']['country']['guid'] ?? '';
        $country = $batch['origin']['country']['name'] ?? '';
        $producer_enterprise_guid = $batch['origin']['producer']['enterprise']['guid'] ?? '';
        $producer = $batch['origin']['producer']['enterprise']['name'] ?? '';
        $producer_role = $batch['origin']['producer']['role'] ?? 'PRODUCER';
        
        $status_changes = $vet_doc['statusChange'] ?? [];
        if (!isset($status_changes[0])) {
            $status_changes = [$status_changes];
        }


        
        return [
            'success' => true,
            'doc_uuid' => $doc_uuid,
            'date_issued' => $date_issued,
            'form' => $form,
            'type' => $type,
            'status' => $status,
            'last_update' => $last_update,
            'shipper_name' => $shipper_name,
            'shipper_uuid' => $shipper_uuid,
            'shipper_enterprise_guid' => $shipper_guid,
            'shipper_business_guid' => $shipper_business_guid,
            'receiver_name' => $receiver_name,
            'receiver_uuid' => $receiver_uuid,
            'receiver_enterprise_guid' => $receiver_guid,
            'receiver_business_guid' => $receiver_business_guid,
            'vehicle_number' => $vehicle_number,
            'trailer_number' => $trailer_number,
            'storage_type' => $storage_type,
            'transportType' => $transportType,
            'broker_guid' => $broker_guid,
            'productType' => $productType,
            'product_name' => $product_name,
            'product_guid' => $product_guid,
            'product_uuid' => $product_uuid,
            'subProduct_guid' => $subProduct_guid,
            'subProduct_uuid' => $subProduct_uuid,
            'productItem_guid' => $productItem_guid,
            'volume' => $volume,
            'unit_name' => $unit_name,
            'unit_guid' => $unit_guid,
            'unit_uuid' => $unit_uuid,
            'batchID' => $batchID,
            'perishable' => $perishable,
            'lowGradeCargo' => $lowGradeCargo,
            'package_type' => $package_type,
            'package_quantity' => $package_quantity,
            'prod_date' => $prod_date,
            'prod_date_year' => $prod_date_year,
            'prod_date_month' => $prod_date_month,
            'prod_date_day' => $prod_date_day,
            'exp_date' => $exp_date,
            'exp_date_year' => $exp_date_year,
            'exp_date_month' => $exp_date_month,
            'exp_date_day' => $exp_date_day,
            'country' => $country,
            'country_guid' => $country_guid,
            'producer' => $producer,
            'producer_enterprise_guid' => $producer_enterprise_guid,
            'producer_role' => $producer_role,
            'status_changes' => $status_changes
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}