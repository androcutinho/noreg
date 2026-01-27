<?php
require 'vendor/autoload.php';

$client = new nusoap_client('https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService', true); // Сервер
	$client->setCredentials('noreg-250715', 'iM3M6wk7', 'basic'); // Авторизация
	$client->soap_defencoding = 'UTF-8'; // Кодировка запроса
	$client->decode_utf8 = false; // Кодировка ответа
  $soapaction = 'https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/GetStockEntryListOperation';
	$request_xml = '
   <SOAP-ENV:Envelope xmlns:bs="http://api.vetrf.ru/schema/cdm/base" 
xmlns:merc="http://api.vetrf.ru/schema/cdm/mercury/g2b/applications/v2" 
xmlns:apldef="http://api.vetrf.ru/schema/cdm/application/ws-definitions" 
xmlns:apl="http://api.vetrf.ru/schema/cdm/application" 
xmlns:vd="http://api.vetrf.ru/schema/cdm/mercury/vet-document/v2" 
xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Header/>
  <SOAP-ENV:Body>
    <apldef:submitApplicationRequest>
      <apldef:apiKey>ZWM0M2ZlM2ItOGIzNy00Y2FlLTkwYjMtM2QxYmVmMGI4YmM0MGRhM2ViNzYtZWM0Ny00ZjQyLWJhNTUtNGIyYjhmNTA5ODQ5</apldef:apiKey>
      <apl:application>
        <apl:serviceId>mercury-g2b.service:2.0</apl:serviceId>
        <apl:issuerId>0da3eb76-ec47-4f42-ba55-4b2b8f509849</apl:issuerId>
        <apl:issueDate>2026-01-08T10:32:08</apl:issueDate>
        <apl:data>
	  <merc:getBusinessEntityUserListRequest>
            <merc:localTransactionId>TR1000448</merc:localTransactionId>
            <merc:initiator>
              <vd:login>sundkvist_la_220202</vd:login>
            </merc:initiator>
            <bs:listOptions>
              <bs:count>100</bs:count>
              <bs:offset>0</bs:offset>
            </bs:listOptions>
          </merc:getBusinessEntityUserListRequest>
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


