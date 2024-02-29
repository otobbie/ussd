<?php

include_once 'apps/Assotel.php';
include_once 'apps/classes/assotel/Api.php';

/**
 * Description of Airtime
 *
 * @author Chuks
 */
class Airtime extends Assotel {

	//put your code here
	private $type = [1 => 'self top-up', 2 => 'others top-up'];

	public function run($arr) {
		switch ($arr['currentStep']) {
			default:
				switch ($arr['userInputs'][2]) {
					case 1:
						return $this->selfTopup($arr);
					case 2:
						return $this->othersTopup($arr);
				}
		}
	}

	public function selfTopup($arr) {
		switch ($arr['currentStep']) {
			case 3:
				return $this->result("continue", $this->render("amount") . $this->type[$arr['userInputs'][2]]);
			case 4:
				if (is_numeric($arr['userInputs'][3])) {
					return $this->result("continue", $this->render("pin"));
				}
				return $this->cancelLastAction($this->render("amount"));
			case 5:
				if ($arr['userInputs'][4]) {
					/* Pull users registered banks via NIBSS */
					return $this->result("continue", $this->render("banks"));
				}
			case 6:
				return $this->result("continue", $this->render("summary") . $arr['userInputs'][3] . "\n"
						. "to " . $arr['msisdn'] . "\n\n"
						. "Press 1 to confirm");
			case 7:
				# Connect to CashEnvoy api
				$ret = Api::cashEnvoyApi(number_format($arr['userInputs'][3], 2), 'MTN', '0' . substr($arr['msisdn'], -10));
				$res = json_decode($ret);

				if ($res->responseCode == '00') {
					return $this->result("end", $this->render("message"));
				}
				return $this->result("end", $this->render("airtime_top_up_failed"));
		}
	}

	public function othersTopup($arr) {
		switch ($arr['currentStep']) {
			case 3:
				return $this->result("continue", $this->render("mobile"));
			case 4:
				if ($arr['userInputs'][3]) {
					return $this->result("continue", $this->render("amount") . $this->type[$arr['userInputs'][2]]);
				}
				return $this->cancelLastAction($this->render("mobile"));
			case 5:
				if (is_numeric($arr['userInputs'][3])) {
					return $this->result("continue", $this->render("pin"));
				}
				return $this->cancelLastAction($this->render("amount"));
			case 6:
				if ($arr['userInputs'][4]) {
					/* Pull users registered banks via NIBSS */
					return $this->result("continue", $this->render("banks"));
				}
			case 7:
				return $this->result("continue", $this->render("summary") . $arr['userInputs'][3] . "\n"
						. "to " . $arr['msisdn'] . "\n\n"
						. "Press 1 to confirm");
			case 8:
				# Connect to CashEnvoy api
				$ret = Api::cashEnvoyApi(number_format($arr['userInputs'][4], 2), 'MTN', '0' . substr($arr['msisdn'], -10));
				$res = json_decode($ret);

				if ($res->responseCode == '00') {
					return $this->result("end", $this->render("message"));
				}
				return $this->result("end", $this->render("airtime_top_up_failed"));
		}
	}

}
