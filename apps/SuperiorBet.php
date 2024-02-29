<?php

include_once 'UssdApplication.php';
include_once 'classes/superiorbet/PredictandWin.php';
include_once 'classes/superiorbet/Virtuals.php';
include_once 'classes/superiorbet/Jackpots.php';
include_once 'classes/superiorbet/TwoDirect.php';
include_once 'classes/superiorbet/Perm_2.php';
include_once 'classes/superiorbet/Topup.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Description of SuperiorBet
 *
 * @author Chuks
 */
class SuperiorBet extends UssdApplication {

	private $arr = [];

	public function getResponse($body) {
		$this->initValues($body);

		$val = str_replace(['*', '#'], ['/', ''], $this->userInputs[0]);
		$game = explode('/', $val);
		array_shift($game);

		$this->arr = [
			'currentStep' => $this->currentStep,
			'lastInputed' => $this->lastInputed,
			'userInputs' => $this->userInputs,
			'msisdn' => '0' . substr($this->getMsisdn(), -10)
		];

		if ($this->userInputs[0]) {
			if (isset($game[2])) {
				$this->arr['currentStep'] = $this->currentStep + 1; // Increase current step by 1
				return $this->routeGamePlay($game[2]); // Re-direct via short code string
			} else {
				switch ($this->currentStep) {
					case 1:
						return $this->result("continue", $this->render("welcome"));
					default:
						if ($this->expectedInput($this->userInputs[1], [1, 2, 3, 4, 5, 6, 7])) {
							return $this->routeGamePlay($this->userInputs[1]);
						}
						return $this->cancelLastAction($this->render("welcome"));
				}
			}
		}
	}

	public function routeGamePlay($gameSelection) {
		switch ($gameSelection) {
			case 1:
				$ret = new PredictandWin;
				return $ret->init($this->arr);
			case 2:
				$ret = new Virtuals;
				return $ret->init($this->arr);
			case 3:
				$ret = new Jackpots;
				return $ret->init($this->arr);
			case 4:
				$ret = new TwoDirect;
				return $ret->init($this->arr);
			case 5:
				$ret = new Perm_2;
				return $ret->init($this->arr);
			case 6:
				/*Api call to Superiorbet for payout summary */
				return $this->result("end", "SGL Payouts:\n"
					. "Promo: NGN40.00 \n"
					. "Winnings: NGN13,620.30");
			case 7:
				$ret = new Topup;
				return $ret->init($this->arr);
			case 8:
				/*Api call to Superiorbet for payout summary */
				return $this->result("end", "Account balance\n"
					. "Your SGL wallet balance: NGN4,500.00");
			case 9:
				return $this->result("end", "SGL Terms & Conditions\n"
					. "Please visit https://www.superiorbetng.com/rules");
			case 10:
				return $this->result("end", "SGL Help\n"
					. "Please contact info@superiorgamesng.com or\n"
					. "supportsuperiorgamesng.com");
		}
	}

	public function render($key) {
		$arr = Yaml::parse(file_get_contents("config/superior-bet/superior-bet.yml"));
		return $arr[$key];
	}

}
