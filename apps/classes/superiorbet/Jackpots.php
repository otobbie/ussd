<?php

include_once 'apps/SuperiorBet.php';

/**
 * Description of Jackpots
 *
 * @author Chuks
 */
class Jackpots extends SuperiorBet {
	//put your code here
	
	private $type = [1 => "6/45", 2 => "6/49"];
	
	public function init($arr) {
		switch ($arr['currentStep']) {
			case 2:
				return $this->result("continue", $this->render("jackpot"));
			case 3:
				if ($this->expectedInput($arr['lastInputed'], [1, 2])) {
					return $this->result("continue", $this->type[$arr['userInput'][3]] . "\n"
						. "Ebeano " . \Carbon\Carbon::now() . "\n"
						. "Please input not more than 6 numbers btw 1-49 seperated by space\n"
						. "e.g. 0 15 12 3 8 9");
				}
				return $this->result("continue", $this->render("jackpot"));
			case 4:
				if (count(explode(' ', $arr['lastInputed'])) == 6) {
					return $this->result("continue", $this->render("amt"));
				}
				return $this->result("continue", $this->render("jackpot"));
			case 5:
				/* Api connect to submit user entry */
				return $this->result("end", "Successful!\n"
					. "Your bet is, " . $arr['userInput'][3] . "\n"
					. "You will receive an SMS ticket confirmation. Goodluck");
		}
	}
}
