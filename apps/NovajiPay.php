<?php

use Symfony\Component\Yaml\Yaml;



include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
include_once 'Rave.php';
require_once 'lib/classes/rb.php';

/**
 * Description of Payment
 *]
 * @author Emmanuel
 */


class NovajiPay extends UssdApplication
{

    private $merchant_code = "77000717";

    public function getResponse($body)
    {
        $this->initValues($body);
        $phone = $this->posted["msisdn"];
        $flash = R::getRow('SELECT * FROM rave_pending_payments WHERE msisdn = ? order by entry_date desc LIMIT 1', [$phone]);
        if ($flash) {
            switch ($this->currentStep) {
                case 1:
                    return $this->result('continue', $this->display('otp'));
                case 2:
                    return $this->rave_validate($phone, $this->lastInputed);
            }
        } else {
            switch ($this->currentStep) {
                case 1:
                    return $this->result('continue', $this->display('select_bank'));
                case 2:
                    switch ($this->lastInputed) {
                        case 1:
                            $this->set_value("action", "all");
                            return $this->flow($body);
                        case 2:
                            $this->set_value("action", "all");
                            return $this->flow($body);
                        case 3:
                            $this->set_value("action", "all");
                            return $this->flow($body);
                        case 4:
                            $this->set_value("action", "all");
                            return $this->flow($body);
                        case 5:
                            $this->set_value("action", "zen");
                            return $this->flow($body);
                        case 6:
                            $this->set_value("action", "nibbs");
                            return $this->flow($body);
                        default:
                            return $this->cancelLastAction($this->display("select_bank"));
                    }
                default:
                    return $this->flow($body);
            }
        }
    }
    public function flow($body)
    {
        $mode = $this->get_value("action");
        if ($mode == "all") {
            return $this->otherBanks($body);
        } elseif ($mode == "zen") {
            return $this->zenith($body);
        } elseif ($mode == "nibbs") {
            return $this->mcash();
        }
    }


    public function mcash()
    {
        $curr = $this->currentStep;
        switch ($curr) {
            case 2:
                return $this->select_bank('5', $this->merchant_code);
            case 3:
                $vals = "novajipay," . "test";
                return $this->initiate_payment($this->merchant_code, $vals);
        }
    }

    public function select_bank($amount, $merchant_code)
    {
        $mcash = new Mcash();
        # set merchant code, very important
        $mcash->set_merchant_code($merchant_code);
        $msisdn = $this->posted["msisdn"];
        return $mcash->prepayment($msisdn, $amount);
    }

    public function otherBanks()
    {

        $curr = $this->currentStep;
        switch ($curr) {
            case 2:
                $this->set_value("choice", $this->lastInputed);
                return $this->result("continue", $this->display("account_number"));
            case 3:
                $this->set_value("account", $this->lastInputed);
                $bank_id = $this->get_value('choice');
                $account_num = $this->get_value('account');
                $phone_number = $this->posted["msisdn"];
                $amount_payable = "120";
                $this->set_value("ref", time());
                $ref = $this->get_value('ref');
                return $this->rave_charge($ref, $phone_number, $amount_payable, $account_num, $bank_id);
            case 4:
                $phone_number = $this->posted["msisdn"];
                return $this->rave_validate($phone_number, $this->lastInputed);
        }
    }



    public function zeniith()
    {
        $curr = $this->currentStep;
        switch ($curr) {
            case 2:
                $this->set_value("choice", $this->lastInputed);
                return $this->result("continue", $this->display("dob"));
            case 3:
                $this->set_value("dob", $this->lastInputed);
                return $this->result("continue", $this->display("account_number"));
            case 4:
                $this->set_value("account", $this->lastInputed);
                $bank_id = $this->get_value('choice');
                $account_num = $this->get_value('account');
                $phone_number = $this->posted["msisdn"];
                $amount_payable = "120";
                $dob = $this->get_value('dob');
                $this->set_value("ref", time());
                $ref = $this->get_value('ref');
                return $this->rave_charge($ref, $phone_number, $amount_payable, $account_num, $bank_id, $bvn = null, $dob);
            case 5:
                $phone_number = $this->posted["msisdn"];
                return $this->rave_validate($phone_number, $this->lastInputed);
        }
    }

    public function rave_charge($ref, $msisdn, $amount, $account_number, $option, $bvn = null, $passcode = null)
    {
        $rave = new Rave();
        return $rave->charge($ref, $msisdn, $amount, $account_number, $option, $bvn, $passcode);
    }

    public function rave_validate($msisdn, $otp)
    {
        $rave = new Rave();
        return $rave->validate($otp, $msisdn);
    }

    public function processTransaction()
    {
        return $this->result("continue", "Transaction in Process.........");
    }

    private function display($key)
    {

        $arr = Yaml::parse(file_get_contents("apps/config/novajiipay/config.yml"));
        return $arr['pages'][$key];
    }
}
