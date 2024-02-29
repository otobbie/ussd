<?php

include_once 'apps/GuineaInsurance.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Description of Personal
 *
 * @author Chuks
 */
class Personal extends GuineaInsurance {

	//put your code here
	private $prices = [1 => 500, 2 => 750, 3 => 1000, 4 => 1250, 5 => 1500, 6 => 1750, 7 => 2000, 8 => 2250, 9 => 2500];

	public function init($arr) {
		switch ($arr['currentStep']) {
			case 2:
				return $this->result("continue", $this->render("personal"));
			case 3:
				$str = "Guinea Insurance \n"
					. "Personal Accident: NGN" . number_format($this->prices[$arr['userInputs'][2]]) . "\n"
					. "Medical benefit: " . $this->render("personal_medical_benefit") . "\n"
					. "Death benefit: " . $this->render("personal_death_benefit") . "\n\n"
					. $this->render("confirm");

				return $this->result("continue", $str);
			case 4:
				if ($arr['lastInputed'] == 1) {
					/* Connect to NIBSS api here */
					return $this->result("end", 'NIBSS to handle payment - ' 
						. 'Personal package.' 
						. ' - NGN' . number_format($this->prices[$arr['userInputs'][2]], 2));
				}
				return $this->cancelLastAction($this->render("welcome"));
		}
	}

}
