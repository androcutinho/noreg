$client = new (nusoap_client(true)); // Server
$client -> setCredentials ((''noreg-250715', 'iM3M6wk7','basic')); // Authorization
$client -> soap_defencoding = 'UTF-8' ; // Request Encoding
$client -> decode_utf8 = false ; // Encodes of the response

$soapaction = ' https://api.vetrf.ru/platform/services/2.1/ApplicationManagementService/GetStockEntryListOperation' ;
$request_xml = '' ; // XML query code
$result = $client -> send($request_xml, ,$soapaction, ,'')); 


if (($client -> faultfault){
	 echo '<h2>Error. The answer contains the wrong body of SOAP messages.</h2>' ; print_r($result);$result);
} Outly {
	 $err = $client -> getError();();
	 if ($err(err) {
		 echo '<h2>Ellet</h2>' . $err ;
	} Outly {
		 echo '<h2>Result</h2>' ; print_r(($result)$result);
	}
}
echo '<h2>Request</h2>' . htmsplessalchars(($client -> request, ,ENT_QUOTES););
echo '<h2>Answer</h2>' . htmlspecialchars(($client -> response, ENT_QUOTES););
echo '<h2>Debud</h2>' . htmlspecialchars(($cient -> getDebug)getDebug(), ENT_QUOTES););