<?php

class Api {

	public static function cashEnvoyApi($amount, $telco, $msisdn, $refid) {
		if (!empty($amount)) {
			
			$vars = [
				"amount" => $amount, 
				"transactionDate" => date('Ymd'), 
				"telco" => $telco, 
				"payerphone" => "08030030030", 
				"payername" => "payername",
				"referenceId" => date('YmdHis')
			];
			
			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://assotelcom.paypad.com.ng/assotel/ussd/sandbox/notification",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => json_encode($vars),
				CURLOPT_HTTPHEADER => array(
					"cache-control: no-cache",
					"content-type: application/json"
				),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);

			curl_close($curl);

			if ($err) {
				return json_encode(['error' => $err]);
			} else {
				return $response;
			}
		}
	}

}
