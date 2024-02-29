<?php

include_once 'apps/Assotel.php';

/**
 * Description of Bills
 *
 * @author Chuks
 */
class Bills extends Assotel {

	//put your code here

	private $billtypes = [1 => 'Electricity'];

	public function runBills($arr) {
		switch ($arr['currentStep']) {
			default:
				switch ($arr['userInputs'][2]) {
					case 1:
						return $this->payElectricityBill($arr);
				}
		}
	}

	public function payElectricityBill($arr) {
		switch ($arr['currentStep']) {
			case 3:
				return $this->result("continue", $this->render("amount") . $this->billtypes[$arr['userInputs'][2]]);
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
						. "for " . $this->billtypes[$arr['userInputs'][2]] . "\n\n"
						. "Press 1 to confirm");
			case 7:
				if ($this->expectedInput($arr['lastInputed'], [1])) {
					return $this->result("end", "Assotel top up for " . $this->billtypes[$arr['userInputs'][2]] . " successful");
				}
				return $this->cancelLastAction($this->render("amount"));
		}
	}

}
