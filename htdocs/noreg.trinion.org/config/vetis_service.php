<?php

require_once(__DIR__ . '/env_helper.php');
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

        $soapaction = 'https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/GetStockEntryListOperation';

        $request_xml_1 = '
         <SOAP-ENV:Envelope xmlns:dt="http://api.vetrf.ru/schema/cdm/dictionary/v2" xmlns:bs="http://api.vetrf.ru/schema/cdm/base" xmlns:merc="http://api.vetrf.ru/schema/cdm/mercury/g2b/applications/v2" xmlns:apldef="http://api.vetrf.ru/schema/cdm/application/ws-definitions" xmlns:apl="http://api.vetrf.ru/schema/cdm/application" xmlns:vd="http://api.vetrf.ru/schema/cdm/mercury/vet-document/v2" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP-ENV:Header/>
          <SOAP-ENV:Body>
            <apldef:submitApplicationRequest>
              <apldef:apiKey>' . $apikey . '</apldef:apiKey>
              <apl:application>
                <apl:serviceId>mercury-g2b.service:2.1</apl:serviceId>
                <apl:issuerId>' . $vetis_issuerId . '</apl:issuerId>
                <apl:issueDate>' . date('Y-m-d\TH:i:s') . '</apl:issueDate>
                <apl:data>
                  <merc:getVetDocumentByUuidRequest>
                    <merc:localTransactionId>' . uniqid('TR') . '</merc:localTransactionId>
                    <merc:initiator>
                      <vd:login>' . $user_login . '</vd:login>
                    </merc:initiator>
                    <bs:uuid>' . $uuid . '</bs:uuid>
                    <dt:enterpriseGuid>' . $vetis_guid . '</dt:enterpriseGuid>
                  </merc:getVetDocumentByUuidRequest>
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

        $request_xml_2 = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://api.vetrf.ru/schema/cdm/application/ws-definitions">
           <soapenv:Header/>
           <soapenv:Body>
              <ws:receiveApplicationResultRequest>
                 <ws:apiKey>' . $apikey . '</ws:apiKey>
                 <ws:issuerId>' . $vetis_issuerId . '</ws:issuerId>
                 <ws:applicationId>' . $applicationId . '</ws:applicationId>
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

        $vet_doc = $result_2['application']['result']['getVetDocumentByUuidResponse']['vetDocument'] ?? null;

        if (!$vet_doc) {
            throw new Exception('Document not found in API response');
        }

        $doc_uuid = $vet_doc['uuid'] ?? '';
        $date_issued = $vet_doc['issueDate'] ?? '';
        $form = $vet_doc['vetDForm'] ?? '';
        $type = $vet_doc['vetDType'] ?? '';
        $status = $vet_doc['vetDStatus'] ?? '';
        $last_update = $vet_doc['lastUpdateDate'] ?? '';
        $shipper_name = $vet_doc['certifiedConsignment']['consignor']['enterprise']['name'] ?? 'Не указано';
        $shipper_uuid = $vet_doc['certifiedConsignment']['consignor']['enterprise']['uuid'] ?? '';
        $receiver_name = $vet_doc['certifiedConsignment']['consignee']['enterprise']['name'] ?? 'Не указано';
        $receiver_uuid = $vet_doc['certifiedConsignment']['consignee']['enterprise']['uuid'] ?? '';
        $vehicle_number = $vet_doc['certifiedConsignment']['transportInfo']['transportNumber']['vehicleNumber'] ?? '';
        $trailer_number = $vet_doc['certifiedConsignment']['transportInfo']['transportNumber']['trailerNumber'] ?? '';
        $storage_type = $vet_doc['certifiedConsignment']['transportStorageType'] ?? '';
        $batch = $vet_doc['certifiedConsignment']['batch'] ?? [];
        $product_name = $batch['productItem']['name'] ?? '';
        $volume = $batch['volume'] ?? '';
        $unit_name = $batch['unit']['name'] ?? '';
        $package_type = $batch['packageList']['package']['packingType']['name'] ?? '';
        $package_quantity = $batch['packageList']['package']['quantity'] ?? '';
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
        $country = $batch['origin']['country']['name'] ?? '';
        $producer = $batch['origin']['producer']['enterprise']['name'] ?? '';
        $status_changes = $vet_doc['statusChange'] ?? [];
        if (!isset($status_changes[0])) {
            $status_changes = [$status_changes];
        }

        // Return clean array with all extracted data
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
            'receiver_name' => $receiver_name,
            'receiver_uuid' => $receiver_uuid,
            'vehicle_number' => $vehicle_number,
            'trailer_number' => $trailer_number,
            'storage_type' => $storage_type,
            'product_name' => $product_name,
            'volume' => $volume,
            'unit_name' => $unit_name,
            'package_type' => $package_type,
            'package_quantity' => $package_quantity,
            'prod_date' => $prod_date,
            'exp_date' => $exp_date,
            'country' => $country,
            'producer' => $producer,
            'status_changes' => $status_changes
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
