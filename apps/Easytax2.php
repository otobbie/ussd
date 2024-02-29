<?php

include_once 'UssdApplication.php';
include_once 'classes/easytaxpayer2/Register.php';
include_once 'classes/easytaxpayer2/TaxPayerInfo.php';
include_once 'classes/easytaxpayer2/PayTax.php';
include_once 'classes/easytaxpayer2/TaxCalculator.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Description of Easytax
 *
 * @author Chuks
 */
class Easytax2 extends UssdApplication {

	public $lang = 1;
	public $arr = [];
	public $liststates = [1 => 'Lagos', 2 => 'Delta', 3 => 'Ogun', 4 => 'Akwa Ibom'];

	public function getResponse($body) {
		$this->initValues($body);

		switch ($this->currentStep) {
			case 1:
				return $this->result('continue', $this->states());
			case 2:
				return $this->result('continue', $this->language());
			case 3:
				if ($this->expectedInput($this->lastInputed, [1, 2, 3])) {
					$this->lang = $this->userInputs[2];
					return $this->result('continue', $this->render('welcome'));
				}
				return $this->cancelLastAction($this->language());
			default:
				$this->arr = [
					'currentStep' => $this->currentStep,
					'lastInputed' => $this->lastInputed,
					'userInputs' => $this->userInputs,
					'states' => $this->liststates,
					'msisdn' => '0' . substr($this->getMsisdn(), -10)
				];
				return $this->executeService();
		}
	}

	public function executeService() {
		switch ($this->userInputs[3]) {
			case 1:
				$register = new Register();
				return $register->newTaxPayer($this->arr);
			case 2:
				$info = new TaxPayerInfo();
				return $info->getPayerInfo($this->arr);
			case 3:
				$paytax = new PayTax();
				return $paytax->payTax($this->arr);
			case 4:
				$cal = new TaxCalculator();
				return $cal->calculateTax($this->arr);
		}
	}

	private function language() {
		return "Select language\n1. English\n2. Yoruba\n3. Pidgin";
	}

	private function states() {
		return "Welcome to Easytax payer\nSelect state\n1. Lagos\n2. Delta\n3. Ogun\n4. Akwa Ibom";
	}

	public function render($key) {
		$arr = Yaml::parse(file_get_contents("config/easytaxpayer-lang" . $this->lang . ".yml"));
		return $arr['pages'][$key];
	}

	public function render_2($key) {
		try {
			$arr = Yaml::parse(file_get_contents("config/easytaxextension.yml"));
			return $arr[$key];
		} catch (ParseException $e) {
			return "Yaml exception: " . $e->getMessage();
		}
	}

}
