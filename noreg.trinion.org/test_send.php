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
	$client->setCredentials('noreg-250715', 'iM3M6wk7', 'basic'); // Авторизация
	$client->soap_defencoding = 'UTF-8'; // Кодировка запроса
	$client->decode_utf8 = false; // Кодировка ответа
  $soapaction = 'https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/GetStockEntryListOperation';
	$request_xml = '<SOAP-ENV:Envelope xmlns:dt="http://api.vetrf.ru/schema/cdm/dictionary/v2" xmlns:bs="http://api.vetrf.ru/schema/cdm/base" xmlns:merc="http://api.vetrf.ru/schema/cdm/mercury/g2b/applications/v2" xmlns:apldef="http://api.vetrf.ru/schema/cdm/application/ws-definitions" xmlns:apl="http://api.vetrf.ru/schema/cdm/application" xmlns:vd="http://api.vetrf.ru/schema/cdm/mercury/vet-document/v2" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
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
            <bs:count>2</bs:count>
            <bs:offset>0</bs:offset>
            </bs:listOptions>
            <dt:enterpriseGuid>7b037904-4c8a-4226-9f3e-1f519789dc4c</dt:enterpriseGuid>
             <merc:searchPattern>
              <vd:blankFilter>NOT_BLANK</vd:blankFilter>
            </merc:searchPattern>
          </merc:getStockEntryListRequest>
        </apl:data>
      </apl:application>
    </apldef:submitApplicationRequest>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>'; // XML код запроса
	$result = $client->send($request_xml, $soapaction, ''); // Отправка запроса
  if ($client->fault) {
	echo '<h2>Ошибка. Ответ содержит неверное тело SOAP сообщения.</h2>'; print_r($result);
} else {
	$err = $client->getError();
	if ($err) {
		echo '<h2>Ошибка</h2>' . $err;
	} else {
		echo '<h2>Результат</h2>'; print_r($result);
	}
}
echo '<h2>Запрос</h2>' . htmlspecialchars($client->request, ENT_QUOTES);
echo '<h2>Ответ</h2>' . htmlspecialchars($client->response, ENT_QUOTES);
echo '<h2>Отладка</h2>' . htmlspecialchars($client->getDebug(), ENT_QUOTES);