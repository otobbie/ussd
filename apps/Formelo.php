<?php

include_once 'UssdApplication.php';
include_once 'init.php';
require_once 'vendor/autoload.php';
require_once 'lib/classes/rb.php';

/**
 * Description of GunineaInsurance
 *
 * @author Chuks
 */
class Formelo extends UssdApplication {

	public $input_count;
	public $reply;
	public $action;
	public $mobile;

	public function getResponse($body) {
		UssdApplication::connect();

		$this->posted = $body;
		$this->action = $this->continueSession();
		$this->reply = $this->display('welcome');
		$userKey = Ussd::getUserKey($body['msisdn']);
		$this->inputedKey = Ussd::getInputKey($body['msisdn']);
		$this->userInputs = Ussd::getUserInputs($this->inputedKey);

		$this->mobile = $this->posted['msisdn'];
		# main application service flow
		$this->currentStep = count($this->userInputs);
		$this->initValues($body);

		if ($body['src'] == 'flash') {
			switch ($this->currentStep) {
				case 1:
					$this->reply = $this->display('banks');
					$this->action = $this->continueSession();
					break;
				case 2:
					$this->reply = $this->display('confirm');
					$this->action = $this->continueSession();
					break;
				case 3:
					// Post payment response via json
					$this->postPaymentResponse();
					// Send response
					$this->reply = $this->display('bye');
					$this->action = $this->continueSession();
					break;
				default:
					$this->reply = $this->display('default');
					$this->action = $this->continueSession();
					break;
			}
		} else {
			$this->displayUSSDApp();
		}

		return ['action' => $this->action, 'message' => $this->reply];
	}

	public function displayUSSDApp() {
		$res = $this->fetchCurrentRequest();

		switch ($this->currentStep) {
			case 1:
				if ($res) {
					$this->reply = (string)$res['description'];
					$this->action = $this->continueSession();
				} else {
					$this->reply = "Sorry, Invalid service request.";
					$this->action = $this->endSession();
				}
				break;
			case 2:
					$this->reply = $this->display('banks');
					$this->action = $this->continueSession();
				break;
			case 3:
					$this->reply = $this->display('confirm');
					$this->action = $this->continueSession();
				break;
			case 4:
					// Post payment response via json
					$this->postPaymentResponse();
					// Send response
					$this->reply = $this->display('bye');
					$this->action = $this->endSession();
				break;
			default:
					$this->reply = $this->display('default');
					$this->action = $this->continueSession();
				break;
		}
	}

	public function display($key) {
		$options = array(
			'banks' => "Select bank: \n1. GTBank \n2. Access \n3. UBA \n4. Diamond",
			'confirm' => "Press 1 to confirm",
			'bye' => "Payment successful. Thanks for using Formelo",
			'default' => "Awaiting response from payment gateway"
		);

		return $options[$key];
	}

	public function postPaymentResponse() {
		// Fetch reference code
		$ref = $this->fetchCurrentRequest();
		// Build json response
		$data = json_encode(["data" => ["transaction_status" => 200]]);
		// Get token
		$token = $this->getToken();

		try {
			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://insuredemo.formelo.com/api/records/" . $ref['reference_code'] . ".json",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "PATCH",
				CURLOPT_POSTFIELDS => $data,
				CURLOPT_HTTPHEADER => array(
					"authorization: bearer " . $token,
					"cache-control: no-cache",
					"content-type: application/json"
				),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);

			curl_close($curl);

			if ($err) {
				echo "cURL Error #:" . $err;
			} else {
//				echo $response;
				// Update payment status
				$this->updateTransaction($ref['id']);
			}
		} catch (Exception $e) {
			return array('error' => $e->getMessage());
		}
	}

	public function fetchCurrentRequest() {
		$ret = R::getRow('select * from novaji_formelo_api where msisdn = ? and status = ? order by id desc limit 1', [$this->mobile, 0]);
		return $ret; //return $ret['reference_code'];
	}

	protected function getToken() {
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://insuredemo.formelo.com/oauth/token",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => "grant_type=client_credentials",
			CURLOPT_HTTPHEADER => array(
				"authorization: Basic bmRqeDdycGstNjA4NDg5MTkxOTA3MDMzMC5hcGktYXBwcy5pbnN1cmVkZW1vLmZvcm1lbG8uY29tOmszS21qQVo5MmRzbnFnUXBtOTROdFFaTkVNejZaYlNWTktXQUdyamR0eTZQcmdMMW00SFcxN2JyTHBLZA==",
				"cache-control: no-cache",
				"content-type: application/x-www-form-urlencoded"
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			echo "cURL Error #:" . $err;
		} else {
			$ret = json_decode($response, true);
			return $ret['access_token'];
		}
	}

	protected function updateTransaction($id) {
		R::exec('UPDATE novaji_formelo_api SET status = 1 WHERE id = ?', [intval($id)]);
	}

}
