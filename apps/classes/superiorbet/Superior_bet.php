<?php

include_once 'apps/SuperiorBet.php';

/**
 * Description of Superior Bet
 *
 * @author Chuks
 */
class Superior_bet extends SuperiorBet {
	
	private $games = [1 => 'Nigeria vs Zambia', 2 => 'Ghana vs Congo', 3 => 'Cameroun vs Algeria'];

	public function init($arr) {
		switch ($arr['currentStep']) {
			case 3:
				if (in_array($arr['lastInputed'], [1, 2, 3])) {
					switch ($arr['lastInputed']) {
						case 1:
							return $this->result('continue', $this->render('superior_pick'));
					}
				}
			default:
				switch ($arr['userInputs'][2]) {
					case 1:
						return $this->pick($arr);
				}
		}
	}

	public function pick($arr) {
		switch ($arr['currentStep']) {
			case 4:
				return $this->result('continue', $this->render('slot') ."\n". $this->games[$arr['userInputs'][3]]);
			case 5:
				$score = explode("-", $arr['userInputs'][4]);
				$match = explode("vs", $this->games[$arr['userInputs'][3]]);
				
				return $this->result('end', $this->render('bet_summary') . $match[0] . $score[0] ." - ". $score[1] . $match[1]);
		}
	}

}
