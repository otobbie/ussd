<?php

include_once 'UssdApplication.php';
include_once 'Mcash.php';

class MCashTest extends UssdApplication {

    public function getResponse($body) {
// always call this first
        $this->initValues($body);
        #return ["action"=>"End","message"=>"mCASH Test"];
        $client_id = "0xACEVxi";
        $secret = "REDuBrNXq86OKt4AkwSp7xC5";
        $merchant_code = "77000701";
        $msisdn = $body["msisdn"];
        switch ($this->currentStep) {
            case 1:
                $mcash = new Mcash($client_id, $secret, $merchant_code);
                return $mcash->prepayment($msisdn,50);
            case 2:
                $selected_option = $body["content"];
                $mcash = new Mcash($client_id, $secret, $merchant_code);
                $bankId = $mcash->get_bank_id($msisdn, $selected_option);
                return $mcash->pay_with_bank($msisdn, $bankId,"assotel");
                #return ["action"=>"End","message"=>substr($bankId,-10)];
            default:
               return ["action"=>"End","message"=>"Application ended"];
        }
    }

}
