<?php

include_once 'apps/SuperiorBet.php';

/**
 * Description of Perm_2
 *
 * @author Chuks
 */
class Perm_2 extends SuperiorBet {

	//put your code here

	private $type = [1 => "Olofin 8890 12:45", 2 => "Mandela 8891 04:40", 3 => "Martin Luther 8894 21:45"];

	public function init($arr) {
		switch ($arr['currentStep']) {
			case 2:
				return $this->result("continue", $this->render("2direct"));
			case 3:
				if ($this->expectedInput($arr['lastInputed'], [1, 2, 3])) {
					return $this->result("continue", $this->type[$arr['userInputs'][2]] . "\n"
							. "Enter min of 3 or max of 5 numbers btw 1-90 (E.g. 1,5,4,8,88) \n"
							. "Higher chance of winnings");
				}
				return $this->cancelLastAction($this->render("2direct"));
			case 4:
				if (count(explode(',', $arr['lastInputed'])) >= 3 && count(explode(',', $arr['lastInputed'] <= 5))) {
					return $this->result("continue", $this->render("amt"));
				}
				return $this->cancelLastAction($this->render("2direct"));
			case 5:
				if ($this->expectedInput($arr['lastInputed'], [1, 2, 3, 4, 5, 6])) {
					return $this->result("end", "Successful!\n"
							. "Your bet placed is, " . $arr['userInputs'][3] . "\n"
							. "You will get an SMS ticket confirmation. Goodluck.");
				}
				return $this->cancelLastAction($this->render("2direct"));
		}
	}

}
