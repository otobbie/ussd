<?php

class Api {

	public static function makeApiCall($url, $data) {
		if ($url) {

			try {
				$curl = curl_init();

				curl_setopt_array($curl, array(
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_HTTPHEADER => array(
						"api-key: c0bc8f80a79ce42cec0fbacc09fdd3c9",
						"cache-control: no-cache",
						"content-type: application/json",
						"token: c1eee53d7f5ae1e41d9e7a132794d5ef-b82f5a78b7d953346c84ab79f9876043"
					),
					CURLOPT_POSTFIELDS => json_encode($data)
				));

				$response = curl_exec($curl);
				$err = curl_error($curl);

				curl_close($curl);
			} catch (Exception $e) {
				$err = $e->getMessage();
			}

			if ($err) {
				return json_encode(['error' => $err]);
			} else {
				return $response;
			}
		}
	}

}
