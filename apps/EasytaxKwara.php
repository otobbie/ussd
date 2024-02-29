<?php

include_once 'UssdApplication.php';
include_once 'classes/easytaxpayer/SendSms.php';

use Symfony\Component\Yaml\Yaml;

class EasytaxKwara extends UssdApplication {

    public function getResponse($body) {
        $this->initValues($body);

        $val = str_replace(['*', '#'], ['/', ''], $this->userInputs[0]);
        $var = explode('/', $val);
        array_shift($var);

        $arr = [1 => 'Road', 2 => 'PAYE', 3 => 'Market', 4 => 'Okada/Keke', 5 => 'Resturants', 6 => 'Others'];

        switch ($this->currentStep) {
            case 1:
                return $this->result('continue', "Gongola Internal Revenue Service\nSelect Tax Type:\n1. Road\n2. PAYE\n3. Market \n4. Okada/Keke\n5. Resturants\n6. Others");
            case 2:
                return $this->result('continue', "Gongola Internal Revenue Service\nEnter your tax ID/TIN or mobile number");
            case 3:
                return $this->result('continue', "Gongola Internal Revenue Service\nAmount: ". $var[2] ."\nTax:". $arr[$this->userInputs[1]] ." levy\nTIN: ". $this->userInputs[2] ."\n\nEnter PIN");
            case 4:
                $code = rand();

                SendSms::sendSmsMessage("Transactin successful. ". $var[2] ." paid for ". $arr[$this->userInputs[1]] ." levy." , "234" . substr($this->getMsisdn(), -10));

                return $this->result("continue", "Gongola Internal Revenue Service\nTransaction successful.\nReceipt No.: ". $code);
        }
    }
}
