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
	
// Создание клиента
$client = new nusoap_client('https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService', true); // Сервер
	$client->setCredentials('noreg-250715', 'iM3M6wk7', 'basic'); // Авторизация
	$client->soap_defencoding = 'UTF-8'; // Кодировка запроса
	$client->decode_utf8 = false; // Кодировка ответа
    $apikey= 'ZWM0M2ZlM2ItOGIzNy00Y2FlLTkwYjMtM2QxYmVmMGI4YmM0MGRhM2ViNzYtZWM0Ny00ZjQyLWJhNTUtNGIyYjhmNTA5ODQ5';

  $soapaction = 'https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/';
	$request_xml = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://api.vetrf.ru/schema/cdm/application/ws-definitions">
   <soapenv:Header/>
   <soapenv:Body>
      <ws:receiveApplicationResultRequest>
         <ws:apiKey>' . $apikey . '</ws:apiKey>
         <ws:issuerId>' . $vetis_issuerId . '</ws:issuerId>
         <ws:applicationId>6a2d7f08-008c-4a34-ae67-3a744543dd0d</ws:applicationId>
      </ws:receiveApplicationResultRequest>
   </soapenv:Body>
</soapenv:Envelope>'; // XML код запроса
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
echo '<h2>Запрос</h2>';
echo '<pre>' . htmlspecialchars($client->request, ENT_QUOTES) . '</pre>';
echo '<h2>Ответ</h2>';
echo '<pre>' . htmlspecialchars($client->response, ENT_QUOTES) . '</pre>';
?>