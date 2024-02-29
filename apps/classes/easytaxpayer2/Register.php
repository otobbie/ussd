<?php
include_once 'apps/Easytax2.php';
include_once 'Accessapi.php';

/**
 * Description of Register
 *
 * @author Chuks
 */
class Register extends Easytax2 {

	private $url = 'http://easytaxpayer.com.ng/web/api/register';

	/**
	 * Register new tax payer
	 *
	 * @param type $arr
	 * @return type
	 */
	public function newTaxPayer($arr) {
		switch ($arr['currentStep']) {
			case 4:
				if (in_array($arr['lastInputed'], [1, 2])) {
					switch ($arr['lastInputed']) {
						case 1:
							return $this->result('continue', $this->render('self_others'));
					}
				}
			default:
				switch ($arr['userInputs'][4]) {
					case 1:
						return $this->selfRegister($arr);
					case 2:
						return $this->registerOthers($arr);
				}
		}
	}

	/**
	 * Register self
	 *
	 * @param array $arr
	 * @return type
	 */
	public function selfRegister($arr) {
		switch ($arr['currentStep']) {
			case 5:
				return $this->result('continue', $this->render('enter_taxpayer_fullname'));
			case 6:
				if ($arr['lastInputed'] != '') {
					return $this->result('continue', "New Tax payer\n\n"
						. "Name: " . ucwords($arr['userInputs'][5]) . "\n"
						. "Mobile: " . $arr['msisdn'] . "\n"
						. "State: ". ucfirst($arr['states'][$arr['userInputs'][1]])."\n\n"
						. "Press 1 to confirm");
				}
				return $this->result('continue', $this->render('self_others'));
			case 7:
				if (in_array($arr['lastInputed'], [1])) {
					// Connect to Easytax api
					$rets = Accessapi::makeApiCall($this->url, [
						"name" => ucwords($arr['userInputs'][5]),
						"phone" => $arr['msisdn'],
						"state_id" => $arr['userInputs'][1]
					]);

					$res = json_decode($rets, true);
					// Return message to recipient
					$retMessage = $res['result']['code'] == 200 ? $res['result']['message'] : $res['error'];
					return $this->result('end', $retMessage);
				}
				return $this->result('continue', $this->render('self_others'));
		}
	}

	/**
	 * Register others
	 *
	 * @param array $arr
	 * @return type
	 */
	public function registerOthers($arr) {
		switch ($arr['currentStep']) {
			case 5:
				return $this->result('continue', $this->render('enter_taxpayer_fullname'));
			case 6:
				if ($arr['lastInputed'] != '') {
					return $this->result('continue', $this->render('enter_mobile'));
				}
			case 7:
				if (strlen($arr['lastInputed']) == 11 && is_numeric($arr['lastInputed'])) {
					return $this->result('continue', "New Tax payer\n\n"
						. "Name: " . ucwords($arr['userInputs'][5]) . "\n"
						. "Mobile: " . $arr['userInputs'][6] . "\n"
						. "State: ". ucfirst($arr['states'][$arr['userInputs'][1]])."\n\n"
						. "Press 1 to confirm");
				}
				return $this->result('continue', $this->render('enter_mobile'));
			case 8:
				if (in_array($arr['lastInputed'], [1])) {
					// Connect to Easytax api
					$rets = Accessapi::makeApiCall($this->url, [
						"name" => ucwords($arr['userInputs'][5]),
						"phone" => $arr['msisdn'],
						"state_id" => $arr['userInputs'][1]
					]);

					$res = json_decode($rets, true);
					// Return message to recipient
					return $res['result']['code'] ? $this->result('end', $res['result']['message']) : $res['error'];
				}
				return $this->result('continue', $this->render('self_others'));
		}
	}

}
