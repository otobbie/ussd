<?php

include_once 'apps/SuperiorBet.php';

/**
 * Description of PredictandWin
 *
 * @author Chuks
 */
class PredictandWin extends SuperiorBet {

	//put your code here

	public function init($arr) {
		switch ($arr['currentStep']) {
			case 2:
				/* Api connection to SuperiorBet for game code available and matches */
				return $this->result("continue", $this->render("predictnwin"));
			case 3:
				if (isset($arr['lastInputed'])) {
					return $this->result("end", $this->render("confirm_predictnwin"));
				}
		}
	}

}
