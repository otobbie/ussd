<?php

include_once 'apps/SuperiorBet.php';

/**
 * Description of TwoDirect
 *
 * @author Chuks
 */
class TwoDirect extends SuperiorBet {

	//put your code here

	private $type = [1 => "Olofin 8890 12:45", 2 => "Mandela 8891 04:40", 3 => "Martin Luther 8894 21:45"];

	public function init($arr) {
		switch ($arr['currentStep']) {
			case 2:
				return $this->result("continue", $this->render("2direct"));
			case 3:
				if ($this->expectedInput($arr['lastInputed'], [1, 2, 3])) {
					return $this->result("continue", $this->type[$arr['userInputs'][2]] . "\n"
							. "Enter only 2 numbers btw 1-90 (E.g. 1,5) \n"
							. "Higher winnings");
				}
				return $this->cancelLastAction($this->render("2direct"));
			case 4:
				if (count(explode(',', $arr['lastInputed'])) == 6) {
					return $this->result("end", "Successful!\n"
							. "Your bet placed is, " . $arr['userInputs'][3] . "\n"
							. "You will get an SMS ticket confirmation. Goodluck.");
				}
				return $this->cancelLastAction($this->render("2direct"));
		}
	}

}
