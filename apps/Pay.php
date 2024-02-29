<?php

include_once 'UssdApplication.php';

use Symfony\Component\Yaml\Yaml;

class Pay extends UssdApplication {

    public function getResponse($body) {
        // always call this first
        $this->initValues($body);
        switch ($this->currentStep) {
            case 1:
                return $this->result('continue', $this->display('input_account_number'));
            case 2:
                if ($this->validAccountNumber($this->lastInputed)) {
                    return $this->result('continue', $this->display('input_bank1'));
                }
                return $this->cancelLastAction($this->display('input_account_number'));
            case 3:
                return $this->result('continue', $this->display('input_cardno'));
            case 4:
                if ($this->validCardDigits($this->lastInputed)) {
                    return $this->result('continue', $this->display('input_pin'));
                }
                return $this->cancelLastAction($this->display('input_cardno'));

            case 5:
                return $this->result('end', $this->display('txt_payment_completed'));
            default:
                return $this->result('continue', $this->display('input_account_number'));
        }
    }

    private function display($key) {

        $arr = Yaml::parse(file_get_contents('config/payment_config.yml'));
        $pages = $arr['views'];
        return $pages[$key];
    }

    private function validAccountNumber($val) {
        return ($this->integerEntered($val) && strlen($val) == 10);
    }

    private function validCardDigits($val) {
        return ($this->integerEntered($val) && strlen($val) == 4);
    }

}
