<?php

require_once(__DIR__ . '/../config/env_helper.php');
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor/econea/nusoap/src/nusoap.php');
require_once(__DIR__ . '/vetis_service.php');


function processIncomingConsignmentRequest($uuid, $options = [])
{
    try {
        
        $vetisData = fetchVetisDocument($uuid);
        
        if (!$vetisData['success']) {
            throw new Exception('Не удалось получить VETIS документ: ' . $vetisData['error']);
        }

        
        $possiblePaths = [
            __DIR__ . '/../../../.env',
            __DIR__ . '/../../.env',
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
            throw new Exception('.env файл не найден');
        }

        $env = loadEnvFile($envPath);

        $login = $env['LOGIN'];
        $password = $env['PASSWORD'];
        $apikey = $env['API_KEY'];
        $vetis_issuerId = $env['VETIS_ISSUER_ID'];
        $user_login = $env['USER_LOGIN'];
        $vetis_guid = $env['VETIS_GUID'];

        
        $client = new nusoap_client('https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService', true);
        $client->setCredentials($login, $password, 'basic');
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;

        $soapaction = 'https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/ProcessIncomingConsignment';

        
        $vetInspection_result = $options['vetInspection_result'] ?? 'CORRESPONDS';
        $decision = $options['decision'] ?? 'ACCEPT_ALL';
        $deliveryDate = $options['deliveryDate'] ?? date('Y-m-d\TH:i:s');
        $request_xml = '
<SOAP-ENV:Envelope xmlns:dt="http://api.vetrf.ru/schema/cdm/dictionary/v2" 
	xmlns:bs="http://api.vetrf.ru/schema/cdm/base" 
	xmlns:merc="http://api.vetrf.ru/schema/cdm/mercury/g2b/applications/v2" 
	xmlns:apldef="http://api.vetrf.ru/schema/cdm/application/ws-definitions" 
	xmlns:apl="http://api.vetrf.ru/schema/cdm/application" 
	xmlns:vd="http://api.vetrf.ru/schema/cdm/mercury/vet-document/v2" 
	xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Header/>
  <SOAP-ENV:Body>
    <apldef:submitApplicationRequest>
      <apldef:apiKey>' . $apikey . '</apldef:apiKey>
      <apl:application>
        <apl:serviceId>mercury-g2b.service:2.1</apl:serviceId>
        <apl:issuerId>' . $vetis_issuerId . '</apl:issuerId>
        <apl:issueDate>' . date('Y-m-d\TH:i:s') . '</apl:issueDate>
        <apl:data>
          <merc:processIncomingConsignmentRequest>
            <merc:localTransactionId>' . uniqid('TR') . '</merc:localTransactionId>
            <merc:initiator>
              <vd:login>' . $user_login . '</vd:login>
            </merc:initiator>
            <merc:delivery>
              <vd:deliveryDate>' . $deliveryDate . '</vd:deliveryDate>
              <vd:consignor>
                <dt:businessEntity>
                  <bs:guid>' . $vetisData['shipper_business_guid'] . '</bs:guid>
                </dt:businessEntity>
                <dt:enterprise>
                  <bs:guid>' . $vetisData['shipper_enterprise_guid'] . '</bs:guid>
                </dt:enterprise>
              </vd:consignor>
              <vd:consignee>
                <dt:businessEntity>
                  <bs:guid>' . $vetisData['receiver_business_guid'] . '</bs:guid>
                </dt:businessEntity>
                <dt:enterprise>
                  <bs:guid>' . $vetisData['receiver_enterprise_guid'] . '</bs:guid>
                </dt:enterprise>
              </vd:consignee>
              <vd:consignment>
                <vd:productType>' . $vetisData['productType'] . '</vd:productType>
                <vd:product>
                  <bs:guid>' . $vetisData['product_guid'] . '</bs:guid>
                </vd:product>
                <vd:subProduct>
                  <bs:guid>' . $vetisData['subProduct_guid'] . '</bs:guid>
                </vd:subProduct>
                <vd:productItem>
                  <bs:guid>' . $vetisData['productItem_guid'] . '</bs:guid>
                </vd:productItem>
                <vd:volume>' . $vetisData['volume'] . '</vd:volume>
                <vd:unit>
                  <bs:guid>' . $vetisData['unit_guid'] . '</bs:guid>
                </vd:unit>
                <vd:dateOfProduction>
                  <vd:firstDate>
                    <dt:year>' . $vetisData['prod_date_year'] . '</dt:year>
                    <dt:month>' . $vetisData['prod_date_month'] . '</dt:month>
                    <dt:day>' . $vetisData['prod_date_day'] . '</dt:day>
                    <dt:hour>0</dt:hour>
                  </vd:firstDate>
                </vd:dateOfProduction>
                <vd:expiryDate>
                  <vd:firstDate>
                    <dt:year>' . $vetisData['exp_date_year'] . '</dt:year>
                    <dt:month>' . $vetisData['exp_date_month'] . '</dt:month>
                    <dt:day>' . $vetisData['exp_date_day'] . '</dt:day>
                    <dt:hour>0</dt:hour>
                  </vd:firstDate>
                </vd:expiryDate>
                <vd:batchID>' . htmlspecialchars($vetisData['batchID'], ENT_XML1) . '</vd:batchID>
                <vd:perishable>' . ($vetisData['perishable'] ? 'true' : 'false') . '</vd:perishable>
                <vd:origin>
                  <vd:country>
                    <bs:guid>' . $vetisData['country_guid'] . '</bs:guid>
                  </vd:country>
                  <vd:producer>
                    <dt:enterprise>
                      <bs:guid>' . $vetisData['producer_enterprise_guid'] . '</bs:guid>
                    </dt:enterprise>
                    <dt:role>' . $vetisData['producer_role'] . '</dt:role>
                  </vd:producer>
                </vd:origin>
                <vd:lowGradeCargo>' . ($vetisData['lowGradeCargo'] ? 'true' : 'false') . '</vd:lowGradeCargo>
              </vd:consignment>';

        
        if (!empty($vetisData['broker_guid'])) {
            $request_xml .= '
              <vd:broker>
                <bs:guid>' . $vetisData['broker_guid'] . '</bs:guid>
              </vd:broker>';
        }

        $request_xml .= '
              <vd:transportInfo>
                <vd:transportType>' . $vetisData['transportType'] . '</vd:transportType>
                <vd:transportNumber>
                  <vd:vehicleNumber>' . htmlspecialchars($vetisData['vehicle_number'], ENT_XML1) . '</vd:vehicleNumber>' ;

        if (!empty($vetisData['trailer_number'])) {
            $request_xml .= '
                  <vd:trailerNumber>' . htmlspecialchars($vetisData['trailer_number'], ENT_XML1) . '</vd:trailerNumber>';
        }

        $request_xml .= '
                </vd:transportNumber>
              </vd:transportInfo>
              <vd:transportStorageType>' . $vetisData['storage_type'] . '</vd:transportStorageType>
              <vd:accompanyingForms>
                <vd:vetCertificate>
                  <bs:uuid>' . $uuid . '</bs:uuid>
                </vd:vetCertificate>
              </vd:accompanyingForms>
            </merc:delivery>
            <merc:deliveryFacts>
              <vd:vetCertificatePresence>ELECTRONIC</vd:vetCertificatePresence>
              <vd:docInspection>
                <vd:responsible>
                  <vd:login>' . $user_login . '</vd:login>
                </vd:responsible>
                <vd:result>CORRESPONDS</vd:result>
              </vd:docInspection>
              <vd:vetInspection>
                <vd:responsible>
                  <vd:login>' . $user_login . '</vd:login>
                </vd:responsible>
                <vd:result>' . $vetInspection_result . '</vd:result>
              </vd:vetInspection>
              <vd:decision>' . $decision . '</vd:decision>
            </merc:deliveryFacts>
          </merc:processIncomingConsignmentRequest>
        </apl:data>
      </apl:application>
    </apldef:submitApplicationRequest>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

        
        $result_1 = $client->send($request_xml, $soapaction, '');

        
        $applicationId = null;
        if ($client->fault) {
            throw new Exception('SOAP Ошибка: ' . ($result_1['faultstring'] ?? 'Неизвестная ошибка'));
        }

        $err = $client->getError();
        if ($err) {
            throw new Exception('SOAP Ошибка: ' . $err);
        }

        if (is_array($result_1) && isset($result_1['application']['applicationId'])) {
            $applicationId = $result_1['application']['applicationId'];
        }

        if (!$applicationId) {
            throw new Exception('Не удалось получить Application ID из ответа');
        }

        
        sleep(5);

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
            throw new Exception('SOAP Ошибка при получении результата: ' . ($result_2['faultstring'] ?? 'Неизвестная ошибка'));
        }

        $err = $client->getError();
        if ($err) {
            throw new Exception('SOAP Ошибка при получении результата: ' . $err);
        }

        // Return clean response
        return [
            'success' => true,
            'applicationId' => $applicationId,
            'status' => $result_2['application']['status'] ?? 'UNKNOWN',
            'uuid' => $uuid,
            'message' => 'Запрос ProcessIncomingConsignment успешно отправлен',
            'full_response' => $result_2
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'uuid' => $uuid ?? null
        ];
    }
}


if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) === 'process_incoming_consignment_service.php') {
    // Get UUID from query parameter
    $uuid = $_GET['uuid'] ?? null;
    
    if (!$uuid) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'UUID параметр обязателен'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    
    $options = [
        'vetInspection_result' => $_GET['vetInspection'] ?? 'CORRESPONDS',
        'decision' => $_GET['decision'] ?? 'ACCEPT_ALL',
        'deliveryDate' => $_GET['deliveryDate'] ?? date('Y-m-d\TH:i:s')
    ];
    
    
    $result = processIncomingConsignmentRequest($uuid, $options);
    
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
