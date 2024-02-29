<?php

include_once 'UssdApplication.php';

class Bta extends UssdApplication {

        public function getResponse($body) {
		$this->initValues($body);

		$val = str_replace(['*', '#'], ['/', ''], $this->userInputs[0]);
                $var = explode('/', $val);
                array_shift($var);

                if (isset($var[2])) {
                        switch($var[2]) {
                                case 1:
                                        return $this->result("end", "BAT USSD\n1. View Current Points \n2. Current notice");
                                        break;
                                default:
                                        return $this->result("end", "Thank you for your code. You have received 20 points");
                        }
                } else {
        	       return $this->result('end', "Welcome to BAT USSD\nYou are not registered. Text BAT name,age,location to 371 to register");
                }
	}
}
