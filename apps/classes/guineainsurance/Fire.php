<?php

include_once 'apps/GuineaInsurance.php';

/**
 * Description of Fire
 *
 * @author Chuks
 */
class Fire extends GuineaInsurance {

	//put your code here
	private $prices = [1 => 2000];

	public function init($arr) {
		switch ($arr['currentStep']) {
			case 2:
				return $this->result("continue", $this->render("fire"));
			case 3:
				$str = "Guinea Insurance \n"
					. "Fire: NGN" . number_format($this->prices[$arr['userInputs'][2]]) . "\n"
					. "Medical benefit: " . $this->render("personal_medical_benefit") . "\n"
					. "Death benefit: " . $this->render("personal_death_benefit") . "\n\n"
					. $this->render("confirm");

				return $this->result("continue", $str);
			case 4:
				if ($arr['lastInputed'] == 1) {
					/* Connect to NIBSS api here */
					return $this->result("end", 'NIBSS to handle payment - ' 
						. 'Fire package.'
						. ' - NGN' . number_format($arr['userInputs'][2], 2));
				}
				return $this->cancelLastAction($this->render("banks"));
		}
	}

}
