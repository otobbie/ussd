<?php

include_once 'UssdApplication.php';
include_once 'classes/guineainsurance/Personal.php';
include_once 'classes/guineainsurance/Fire.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Description of GunineaInsurance
 *
 * @author Chuks
 */
class GuineaInsurance extends UssdApplication {

	public function getResponse($body) {
		$this->initValues($body);
		
		switch($this->currentStep) {
			case 1:
				return $this->result("continue", $this->render("welcome"));
			default:
				return $this->exec([
					'currentStep' => $this->currentStep,
					'lastInputed' => $this->lastInputed,
					'userInputs' => $this->userInputs,
				]);
		}
	}
	
	public function exec($arr) {
		switch ($this->userInputs[1]) {
			case 1:
				$ret = new Personal;
				return $ret->init($arr);
			case 2:
				$ret = new Fire;
				return $ret->init($arr);
		}
	}

	public function render($key) {
		$arr = Yaml::parse(file_get_contents("config/guineainsurance/guineainsurance.yml"));
		return $arr['pages'][$key];
	}

}
