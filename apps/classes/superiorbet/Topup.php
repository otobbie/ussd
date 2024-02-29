<?php

include_once 'apps/SuperiorBet.php';

/**
 * Description of Account
 *
 * @author Chuks
 */
class Topup extends SuperiorBet {

	//put your code here

	public function init($arr) {
		switch ($arr['currentStep']) {
			case 2:
				return $this->result("continue", "SGL Wallet Topup\n"
						. "Enter amount");
			case 3:
				if (is_numeric($arr['lastInputed'])) {
					return $this->result("continue", "SGL Wallet Topup\n"
							. "Enter pin");
				}
				$this->cancelLastAction("SGL Wallet Topup \nEnter amount");
			case 4:
				if (is_numeric($arr['lastInputed'])) {
					/* Connect to NIBSS */
					return $this->result("end", "Transaction successful\n"
							. "Refno: 3746664783737\n"
							. "NGN" . number_format($arr['userInputs'][2], 2) . " to SuperiorBet account");
				}
		}
	}

}
