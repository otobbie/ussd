<?php

include_once 'UssdApplication.php';
include_once 'classes/easytaxpayer/SendSms.php';

use Symfony\Component\Yaml\Yaml;

class OracleId extends UssdApplication { 

	public function getResponse($body) {
		$this->initValues($body);

		$val = str_replace(['*', '#'], ['/', ''], $this->userInputs[0]);
                $var = explode('/', $val);
                array_shift($var);

                //return $this->result('end', $var[3]);

                switch ($this->currentStep) {
        	        case 1:
        		      return $this->result('continue', "Primera Credit MFB \nName: John Fred\nMaximum amount: N100,000\nEnter loan tenure");
        	        case 2:
        		      return $this->result("continue", "Select bank \n1. Access Bank\n2. GTBank\n3. UBA\n4. Zenith Bank");
        	        case 3:
        		      return $this->result("continue", "Primera Credit MFB\nOracleID:".$var[2]."\nAmt: N". $var[3] ."\nAcct.No.:0009283727\nInterest: 1.5%/day\nTenure:". $this->userInputs[1] ."days\n\nEnter date of birth to confirm(ddmmyy)");
        	        case 4:
        		      SendSms::sendSmsMessage("Congrats. Your Primera Credit MFB loan has been approved", "234" . substr($this->getMsisdn(), -10));

        		      return $this->result("end", "Congrats. Your Primera Credit MFB loan is being processed. You will get a confirmation by sms shortly. Thanks.");
        	        default:
        		      return;
                }
	}
}
