<?php

include_once 'UssdApplication.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Description of WgcBetting
 *
 * @author Chuks
 */
class WgcBetting extends UssdApplication {

	//put your code here

	public function getResponse($body) {
		$this->initValues($body);

		switch ($this->currentStep) {
			case 1:
				return $this->result('continue', $this->render('welcome'));
			case 2:
				if ($this->expectedInput($this->lastInputed, [1])) {
					switch ($this->lastInputed) {
						case 1:
							return $this->result('continue', $this->render('game'));
					}
				}
				return $this->result('continue', $this->render('welcome'));
			case 3:
				if ($this->expectedInput($this->lastInputed, [1, 2])) {
					return $this->result('continue', $this->render('game_type'));
				}
				return $this->result('continue', $this->render('welcome'));
			case 4:
				if ($this->expectedInput($this->lastInputed, [1, 2, 3])) {
					return $this->result('continue', $this->render('amount'));
				}
				return $this->result('continue', $this->render('welcome'));
			case 5:
				if ($this->lastInputed != '') {
					return $this->result('continue', 
						$this->render('bet_summary') . "Game: " . $this->userInputs[2] . "\nPress 1 to confirm");
				}
				return $this->result('continue', $this->render('amount'));
			case 6:
				if ($this->expectedInput($this->lastInputed, [1])) {
					return $this->result('continue', $this->render('banks'));
				}
			case 7:
				if ($this->lastInputed != '') {
					return $this->result('continue', $this->render('pin'));
				}
			case 8:
				if ($this->lastInputed != '') {
					return $this->result('end', $this->render('success') . rand(1000, 50000));
				}
		}
	}

	public function render($key) {
		$arr = Yaml::parse(file_get_contents('config/wgc-betting/wgc-betting.yml'));
		return $arr[$key];
	}

}
