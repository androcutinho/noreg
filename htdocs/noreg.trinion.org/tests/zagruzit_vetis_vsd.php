<?php
require 'vendor/autoload.php';
require_once('vendor/econea/nusoap/src/nusoap.php'); // Подключение библиотеки NuSOAP

// Load shared environment helper
require_once(__DIR__ . '/config/env_helper.php');

// Load .env file
$envPath = __DIR__ . '/../../.env';
$env = loadEnvFile($envPath);

$login = $env['LOGIN'];
$password = $env['PASSWORD'];
$apikey = $env['API_KEY'];
$vetis_issuerId = $env['VETIS_ISSUER_ID'];
$user_login = $env['USER_LOGIN'];
$vetis_guid = $env['VETIS_GUID'];

$client = new nusoap_client('https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService', true); // Сервер
	$client->setCredentials($login, $password, 'basic'); // Авторизация
	$client->soap_defencoding = 'UTF-8'; // Кодировка запроса
	$client->decode_utf8 = false; // Кодировка ответа

$soapaction = 'https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/GetStockEntryListOperation';
$uuid_document = 'aa9cdb37-1da4-4313-86e5-3ff2949dc082';


// STEP 1: GET VET DOCUMENT BY UUID (Request to get application ID)

echo '<h2>STEP 1: Получение информации о документе по UUID</h2>';

$request_xml_1 = '
 <SOAP-ENV:Envelope xmlns:dt="http://api.vetrf.ru/schema/cdm/dictionary/v2" xmlns:bs="http://api.vetrf.ru/schema/cdm/base" xmlns:merc="http://api.vetrf.ru/schema/cdm/mercury/g2b/applications/v2" xmlns:apldef="http://api.vetrf.ru/schema/cdm/application/ws-definitions" xmlns:apl="http://api.vetrf.ru/schema/cdm/application" xmlns:vd="http://api.vetrf.ru/schema/cdm/mercury/vet-document/v2" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Header/>
  <SOAP-ENV:Body>
    <apldef:submitApplicationRequest>
      <apldef:apiKey>' . $apikey . '</apldef:apiKey>
      <apl:application>
        <apl:serviceId>mercury-g2b.service:2.1</apl:serviceId>
        <apl:issuerId>' . $vetis_issuerId . '</apl:issuerId>
        <apl:issueDate>2026-01-30T12:58:20</apl:issueDate>
        <apl:data>
          <merc:getVetDocumentByUuidRequest>
            <merc:localTransactionId>TR10004555</merc:localTransactionId>
            <merc:initiator>
              <vd:login>' . $user_login . '</vd:login>
            </merc:initiator>
            <bs:uuid>' . $uuid_document . '</bs:uuid>
            <dt:enterpriseGuid>' . $vetis_guid . '</dt:enterpriseGuid>
          </merc:getVetDocumentByUuidRequest>
        </apl:data>
      </apl:application>
    </apldef:submitApplicationRequest>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>'; // XML код запроса

$result_1 = $client->send($request_xml_1, $soapaction, '');

// Parse the response to get the application ID
$applicationId = null;
if ($client->fault) {
	echo '<h2>Ошибка на шаге 1. Ответ содержит неверное тело SOAP сообщения.</h2>';
	print_r($result_1);
} else {
	$err = $client->getError();
	if ($err) {
		echo '<h2>Ошибка на шаге 1:</h2>' . $err;
	} else {
		echo '<h2>Результат шага 1:</h2>';
		
		// Try to extract applicationId from response 
		if (is_array($result_1)) {
		
		if (isset($result_1['application']['applicationId'])) {
				$applicationId = $result_1['application']['applicationId'];
			}
		}
		
		if ($applicationId) {
			echo '<p><strong>Полученный ID приложения:</strong> ' . htmlspecialchars($applicationId) . '</p>';
		} else {
			echo '<p><strong>Не удалось извлечь ID приложения из ответа</strong></p>';
		}
	}
}

// STEP 2: RECEIVE APPLICATION RESULT

if ($applicationId) {
	echo '<h2>STEP 2: Получение результата приложения</h2>';
	
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
		echo '<h2>Ошибка на шаге 2. Ответ содержит неверное тело SOAP сообщения.</h2>';
		print_r($result_2);
	} else {
		$err = $client->getError();
		if ($err) {
			echo '<h2>Ошибка на шаге 2:</h2>' . $err;
		} else {
			echo '<h2>Результат шага 2 (Информация приложения):</h2>';
			print_r($result_2);
		}
	}
	
	echo '<h2>Ответ шага 2:</h2>';
	echo '<pre>' . htmlspecialchars($client->response, ENT_QUOTES) . '</pre>';
} else {
	echo '<h2>Не удалось получить ID приложения на шаге 1. Шаг 2 пропущен.</h2>';
}
?>