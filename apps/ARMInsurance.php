<?php

include_once 'UssdApplication.php';
include_once '_init.php';
require 'vendor/autoload.php';

/**
 * Description of PALInsurance
 *
 * @author Chuks
 */

class ARMInsurance extends UssdApplication {
	
	private $inputKey;
    private $userKey;
    private $body;
    private $lastInput;
	
	public function getResponse($body) {

        //global $redis;
        $action = $this->continueSession();
        $reply = 'Stanbic IBTC Pension';
        $userKey = Ussd::getUserKey($body['msisdn']);
        $inputKey = Ussd::getInputKey($body['msisdn']);
        $inputs = Ussd::getUserInputs($inputKey);
        $lastInput = $body['content'];
        $this->inputKey = $inputKey;
        $this->userKey = $userKey;
        $this->body = $body;
        $this->lastInput = $lastInput;
        # main application service flow
        $input_count = count($inputs);
		
        # Step 1: Welcome
        if ($input_count == 1) {
            $reply = $this->display('intro');
            $action = $this->continueSession();
        }
		
        # step 2: Get request
        if ($input_count == 2) {
			$opts = [1, 2, 3, 4, 5];
			
			if (in_array($lastInput, $opts)) {
				switch ($lastInput) {
					case '1':
						$reply = $this->display('check_balance'); # Check balance
						break;
					case '2':
						$reply = $this->display('statement_check'); # Statment check
						break;
					case '3':
						$reply = $this->display('pfa_account');
						break;
					case '4':
						$reply = $this->display('open_pfa_account');
						break;
					case '5':
						$reply = $this->display('contribution');
						break;
				}
			} else {
				$reply = $this->display('intro');
				Ussd::deleteLastInput($inputKey);
			}
			
			$action = $this->continueSession();
        }
		
		# Display response to customer's request
		if ($input_count == 3) { 
			switch ($inputs[1]) {
				case '1':
					$reply = $this->display('balance_response');
					$action = $this->endSession();
					break;
				case '2':
					$reply = $this->display('statement_response');
					$action = $this->endSession();
					break;
				case '3':
					$reply = $this->display('pfa_response');
					$action = $this->endSession();
					break;
				case '4':
					$reply = $this->display('open_pfa_response');
					$action = $this->endSession();
					break;
				case '5':
					$reply = $this->display('contribution_response');
					$action = $this->endSession();
					break;
			}
		}

        return array(
			'action' => $action,
            'message' => $reply
		);
    }

    public function display($key) {
		$options = array(
			'intro' => "Welcome to Stanbic IBTC Pension. \n1. Check Balance \n2. Statement Check \n3. My PFA account number \n4. Open PFA account \n5. Confirm monthly contribution",
			'check_balance' => "Balance check\n Please input last four digits of your PFA number or DOB",
			'statement_check' => "Balance check\n Please input last four digits of your PFA number or DOB. Your last three month statement will be display",
			'pfa_account' => "PFA Account Number Retrieval\n Please input DOB",
			'open_pfa_account' => "Open PFA Account\n Welcome Olufemi Balogun to pension account opening. Press 1 to open PFA account with SIM Reg. details",
			'contribution' => "Monthly contribution by employer\n Please input DOB",
			'balance_response' => "Your pension bal. as at 30th Mar. 2017is N1,500,000.78k. Thank you for choosing Stanbic IBTC Pension",
			'statement_response' => "Your pension statement as at 30th Mar. 2017 is \n Dec.2016 - N1,500,000.82k \n Jan.2017 - N1,600,010.01k \n Mar.2017 - N1,702,000.00k",
			'pfa_response' => "Your pension account number is 105210898. Thank you for choosing Stanbic IBTC Pension",
			'open_pfa_response' => "Your pension fund account number is 12379008. Thank you for choosing Stanbic IBTC Pension",
			'contribution_response' => "Your last employer contribution is N100,050. Thank you for choosing Stanbic IBTC Pension"
		);
		
		return $options[$key];
	}
}
