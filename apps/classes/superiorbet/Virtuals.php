<?php

include_once 'apps/SuperiorBet.php';

/**
 * Description of Virtual
 *
 * @author Chuks
 */
class Virtual extends SuperiorBet {
	//put your code here
	
	public function init($arr) {
		switch ($arr['currentStep']) {
			case 2:
				return $this->result("continue", $this->render("epl"));
			default:
				if ($this->expectedInput($arr['lastInputed'], [1, 2, 3, 4])) {
					switch ($arr['lastInputed']) {
						case 1:
							return $this->epl($arr);
					}
				}
				$this->cancelLastAction($this->render("epl"));
		}
	}
	
	protected function epl($arr) {
		switch ($arr['currentStep']) {
			case 3:
				/* Api call to fetch epl data */
				return $this->result("continue", $this->render("epl"));
			case 4:
				return $this->result("continue", $this->render("epl_opts"));
			case 5:
				return $this->result("continue", $this->render("epl_picks"));
			case 6:
				return $this->result("continue", $this->render("amt"));
			case 7:
				return $this->result("end", $this->render("epl_confirm"));
		}
	}
}
