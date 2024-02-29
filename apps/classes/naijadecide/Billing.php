<?php

class Billing {

	public static function bill($sender) {
		$curl = curl_init();

		$msisdn = '0' . substr($sender, -10);

		curl_setopt_array($curl, array(
		CURLOPT_URL => "https://directbillstage.9mobile.com.ng/stg/syncbilling",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => json_encode(["serviceName" => "9JADEC50", "msisdn" => $msisdn, "id" => time(), "amount" => 5000]),
		// CURLOPT_POSTFIELDS => "{\n  \"serviceName\":\"9JADEC50\",\n  \"msisdn\":\"08180010534\",\n  \"id\":\"123265490\",\n  \"amount\":5000\n}",
		CURLOPT_HTTPHEADER => array(
		    "authorization: 503AF4D1A7D049740198C0679FA58B88949F9F9B7A9AA3D66670F85CBF76B67B",
		    "cache-control: no-cache",
		    "content-type: application/json",
		    "ocp-apim-subscription-key: deb3c7c967484adc94ad8852dcd80b22",
		    "username: novaji"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  return "cURL Error #:" . $err;
		} else {
		  return $response;
		}
	}
}