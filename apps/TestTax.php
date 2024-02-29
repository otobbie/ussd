<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of TestTax
 *
 * @author banks
 */
include_once 'UssdApplication.php';

use Symfony\Component\Yaml\Yaml;

class TestTax 
{
     private $api_username;
    private $api_password;

    function __construct() {
       //print "In BaseClass constructor\n";
        $api = $this->api_credentials();
        $this->api_username = $api['api_username'];
        $this->api_password = $api['api_password'];
   }
    public function getResponse($body) {
        //global $redis;
        $this->posted = $body;
        $action = $this->continueSession();
        $reply = $this->display('welcome');
        $userKey = Ussd::getUserKey($body['msisdn']);
        $this->inputedKey = Ussd::getInputKey($body['msisdn']);
        $this->userInputs = Ussd::getUserInputs($this->inputedKey);
        #$lastInput = $body['content'];
        # main application service flow
        $this->currentStep = count($this->userInputs);
        $this->initValues($body);
        switch ($this->currentStep) {
            case 1:
                # select language
                return $this->result('continue', $this->language());
            case 2:
                # Step 1: Welcome
                if ($this->expectedInput($this->lastInputed, [1, 2,3])) {
                    return $this->result('continue', $this->display('welcome'));
                }
                return $this->cancelLastAction($this->language());
            case 3:
                if ($this->expectedInput($this->lastInputed, [1, 2, 3, 4, 5])) {
                    switch ($this->lastInputed) {
                        case 1:
                            // Registration 
                            return $this->registerNewTaxPayer();
                        case 2;
                            // Get Payer ID
                            return $this->getPayerID();
                        CASE 3:
                            //  Payment
                            return $this->payTax();

                        case 4:
                            // Calculator
                            $reply = $this->display('calculator_options');
                            break;
                        case 5:
                            return $this->taxInfo();
                        //break;
                        default:
                            break;
                    }
                } else {
                    $reply = $this->display('welcome');
                    Ussd::deleteLastInput($this->inputedKey);
                }
                $action = $this->continueSession();
                break;
            case 4:
                return $this->executeService($body);
            //break;
            case 5:
                return $this->executeService($body);
            case 6:
                return $this->executeService($body);
            case 7:
                return $this->executeService($body);
            case 8:
                return $this->executeService($body);
            //break;
            case 9:
                return $this->executeService($body);
            default:
                $reply = $this->display('welcome');
                Ussd::deleteLastInput($this->inputedKey);
                break;
        }
        return $this->result($action, $reply);
        //return ['action' => $action, 'message' => $reply];
    }

    private function display($key) {
        $langId = $this->userInputs[1];
        $config_file = "config/easytaxpayer-lang".$langId.".yml";
        $arr = Yaml::parse(file_get_contents($config_file));
        $pages = $arr['pages'];
        return $pages[$key];
    }

    public function executeService($body) {
        $command = $this->userInputs[2];
        $reply = $this->display("new_payer");
        $action = $this->endSession();
        switch ($command) {
            case 1:
                //$reply = $this->display("new_payer");
                return $this->registerNewTaxPayer();
            //break;
            case 2:
                //$mobile = $this->userInputs[3];
                return $this->getPayerID();
            //break;
            case 3:
                return $this->payTax();
            case 4:
                $tax_type = $this->userInputs[3];
                switch ($tax_type) {
                    case 1:
                        return $this->doSelfAssessment();
                    case 2:
                        return $this->calculatePaye();
                    default:
                        # invalid input, start again
                        Ussd::deleteLastInput($this->inputedKey);
                        return $this->result('Continue', $this->display('calculator_options'));
                }
            case 5:
                return $this->taxInfo();
        }
        return ['action' => $action, 'message' => $reply];
        //return $reply;
    }

    private function getSelfAssessmentAmount() {
        $amt = $this->userInputs[4];
        if (is_numeric($amt)) {
            return $amt;
        }
        return 0.00;
    }

    private function calculateSelfAssessment($amt) {
        $rate = $this->minimumTaxRate();
        $tax = 0.00;
        if ($amt > 0) {
            $tax = $amt * ($rate / 100);
        }
        return $tax;
    }

    private function minimumTaxRate() {
        return 1.0;
    }

    public function getTaxpayerInfo($id) {
        $url = "http://easytaxpayer.com/api/validatetin";
        /*
          $parameters = array('tin' => $id,
          'state' => 'Lagos',
          'username' => 'support',
          'password' > '07066192100');

          $headers = null;
          $result = Unirest\Request::get($url, $headers, $parameters);
         * var_dump($result->raw_body);
         * 
         */
        # remove all nonnumric characters
        $payerId = preg_replace('~\D~', '', $id);
        $result = json_decode(file_get_contents($url . "?username=07066192100&password=demo&state=Lagos&tin=$payerId"));

        $obj = $result->result;
        if ($obj->PayerID) {
            $ret = "{$this->display('payer_id')} {$obj->PayerID}\n{$this->display('lbl_fullname')} {$obj->FullName}"
                    . "\n{$this->display('lbl_last_payment')} "
                    . "{$obj->LastPayment}\n{$this->display('lbl_clearance_issued')} {$obj->ClearanceIssued}"
                    . "\n{$obj->eTCCStatus}";
            return $this->result('end', $ret);
        }
        $temp = $this->getTempPayerInfo($this->posted['msisdn']);
        if ($temp->payer_id) {
            return $this->result('end', "{$this->display('lbl_tmp_id')} {$temp->payer_id}");
        }
        return $this->result('end', $this->display('lbl_payer_notfound'));
    }

    private function calculatePaye() {
        $current_step = $this->currentStep;
        $heading = $this->display('PAYE') . "\n";
        switch ($current_step) {
            case 4:
                return $this->result('continue', $heading . $this->display('monthly_basic'));
            case 5:
                //return $this->result('continue', $heading . $this->display('input_pension_rate'));
                $paye = $this->computePAYE();
                return $this->result('end', "{$this->display('lbl_estimated')} " . $heading . "{$this->display('lbl_gross')} " . number_format($paye['gross'], 2) .
                                "\n{$this->display('lbl_takehome')} " . number_format($paye['net'], 2) .
                                "\n{$this->display('lbl_tax')} " . number_format($paye['tax'], 2) .
                                "\n{$this->display('lbl_deductions')} " . number_format($paye['deductions'], 2) .
                                "\n{$this->display('lbl_freepay')} " . number_format($paye['tax_free'], 2) .
                                "\n{$this->display('lbl_pension')} " . number_format($paye['pension_pct'], 2) .
                                "\n{$this->display('lbl_nhf')} " . number_format($paye['nhf_pct'], 2) .
                                "\n{$this->display('lbl_tax_rate')} " . number_format($paye['tax_rate'], 2)
                );
            /*
              case 5:
              //return $this->result('continue', $heading . $this->display('input_nhf_rate'));
              case 6:
             */
            default:
                break;
        }
    }

    private function doSelfAssessment() {
        if ($this->currentStep == 4) {
            $reply = "{$this->display('self_assessment')}\n" . $this->display('monthly_income');
            return $this->result('Continue', $reply);
        }
        if ($this->currentStep == 5) {
            $amt = $this->getSelfAssessmentAmount();
            $payable = $this->calculateSelfAssessment($amt);
            $reply = "{$this->display('self_assessment')}\n" . $this->display('lbl_monthly_income')
                    . number_format($amt, 2) . "\n"
                    . $this->display('lbl_min_tax') . number_format($payable, 2) . "\n"
                    . $this->display('lbl_min_tax_rate') . number_format($this->minimumTaxRate(), 2) . '%';
            return $this->result('End', $reply);
        }
    }

    private function computePAYE() {
        $monthly = $this->parseNumeric($this->userInputs[4]);
        //$pension_pct = $this->parseNumeric($this->userInputs[4]);
        $pension_pct = 7.5;
        //$nhf_pct = $this->parseNumeric($this->userInputs[5]);
        $nhf_pct = 2.5;
        $annual = $monthly * 12;
        $income = $annual;
        $excemption = 0;
        $taxable = 0;
        if ($annual <= 0) {
            return 0;
        }
        $relief = $this->taxRelief($annual);


        $pension = ($pension_pct * $annual) / 100;
        $nhf = ($nhf_pct * $annual) / 100;
        $excemption+= $pension + $nhf + $relief;
        if ($annual <= $excemption) {
            $excemption = $income;
        } else {
            $taxable = $income - $excemption;
        }

        /*
         * var p1 = Math.min(300000, taxable) * (7/100);
          var p2 = (taxable >= 300000) ? Math.min(300000, taxable - 300000) * (11/100) : 0;
          var p3 = (taxable >= 600000) ? Math.min(500000, taxable - 600000) * (15/100) : 0;
          var p4 = (taxable >= 1100000) ? Math.min(500000, taxable - 1100000) * (19/100) : 0;
          var p5 = (taxable >= 1600000) ? Math.min(1600000, taxable - 1600000) * (21/100) : 0;
          var p6 = (taxable >= 3200000) ? (taxable - 3200000) * (24/100) : 0;
         */
        $p1 = min(300000, $taxable) * (7 / 100);
        $p2 = ($taxable >= 300000) ? min(300000, $taxable - 300000) * (11 / 100) : 0;
        $p3 = ($taxable >= 600000) ? min(500000, $taxable - 600000) * (15 / 100) : 0;
        $p4 = ($taxable >= 1100000) ? min(500000, $taxable - 1100000) * (19 / 100) : 0;
        $p5 = ($taxable >= 1600000) ? min(1600000, $taxable - 1600000) * (21 / 100) : 0;
        $p6 = ($taxable >= 3200000) ? min($taxable - 3200000) * (24 / 100) : 0;
        $tax = $p1 + $p2 + $p3 + $p4 + $p5 + $p6;
        // var deduction = pension + nhf + tax;
        $deduction = $pension + $nhf + $tax;
        $net = $income - $deduction;
        $tax_rate = 100 * ($tax / $income);
        return [
            'gross' => $monthly,
            'net' => $net / 12,
            'tax_free' => $excemption / 12,
            'taxable' => $taxable / 12,
            'tax' => $tax / 12,
            'pension_pct' => $pension_pct,
            'nhf_pct' => $nhf_pct,
            'tax_rate' => $tax_rate,
            'deductions' => $deduction / 12
        ];
    }

    private function taxRelief($annual) {
        return ($annual * 0.2) + 200000;
    }

    private function switchToPayment() {
        return $this->doSwitch('*371', '*371', '*371*19#');
    }

    public function registerNewTaxPayer() {
        $curr = $this->currentStep;
        $title = $this->display('lbl_registration_title');
        switch ($curr) {
            case 3:
                return $this->result('continue', "$title:\n" . $this->display('self_others'));
            case 4:
                if ($this->lastInputed == 2) {
                    return $this->result('continue', "$title:\n" . $this->display('enter_mobile'));
                } else {
                    return $this->confirmRegistration();
                }
            case 5:
                $target = $this->userInputs[3];
                if ($target == 1) {
                    $mobile = $this->posted['msisdn'];
                    $obj = $this->getTempPayerInfo($mobile);
                    if ($obj->message == 'Success') {
                        # send an SMS
                        return $this->result('end', "{$this->display('lbl_new_payer_created')}\n"
                                        . "{$this->display('lbl_tmp_id')} {$obj->payer_id}");
                    }
                    return $this->result('end', $this->display('registration_failed'));
                    //return $this->result('end', $obj->message);
                } else {
                    return $this->confirmRegistration();
                }
            case 6:
                $target = $this->userInputs[3];
                if ($target == 2) {
                    # third party mobile number
                    $mobile = $this->userInputs[4];
                    $obj = $this->getTempPayerInfo($mobile);
                    if ($obj->message == 'Success') {
                        # send an SMS
                        return $this->result('end', "{$this->display('lbl_new_payer_created')}\n"
                                        . "{$this->display('lbl_tmp_id')} {$obj->payer_id}");
                    }
                    return $this->result('end', $this->display('registration_failed'));
                }
                return $this->result('end', $this->display('invalid_request'));
            default:
                break;
        }
    }

    private function confirmRegistration() {
        $bio = $this->getUserInfo($this->posted['msisdn']);
        return $this->result('continue', $this->display('confirm_registration') .
                        "{$this->display('lbl_name')} " . $bio['name'] .
                        "\n" . $this->display('confirm'));
    }

    private function getTempPayerInfo($mobile) {
        $url = "http://easytaxpayer.com/api/getpayerid";
        $result = json_decode(file_get_contents($url
                        . "?username=07066192100&password=demo&state=Lagos&mobile=$mobile"));
        return $result->result;
    }

    private function getUserInfo($mobile) {
        return ['name' => 'Taiwo Emeka Ibrahim'];
    }

    private function taxInfo() {
        $current = $this->currentStep;
        switch ($current) {
            case 2:
                null;
                break;
            case 3:
                return $this->result('continue', $this->display('taxinfo'));
            case 4;
                # subscribe to tax info
                $this->subscribeToTaxInfo($this->posted['msisdn']);
                return $this->result('end', $this->display('taxinfo_2'));
            default:
                break;
        }
    }

    private function subscribeToTaxInfo($mobile) {
        
    }

    private function payTax() {
        switch ($this->currentStep) {
            case 2:
                break;
            case 3:
                //return $this->result('continue', $this->display('enter_tin'));
                return $this->result('continue', $this->display('tax_payment_options'));
            case 4:
                if ($this->expectedInput($this->lastInputed, [1, 2, 3, 4, 5, 6, 7])) {
                    switch ($this->lastInputed) {
                        case 1:
                            #witholding
                            return $this->result('continue', $this->display('witholding_tax_options'));
                        case 2:
                            #business premises
                            return $this->result('continue', $this->display('business_premises_options'));
                        case 3:
                            #development levy
                            return $this->result('continue', $this->display('development_levy_options'));
                        case 4:
                            # market tax
                            return $this->result('continue', $this->display('market_tax_options'));
                        case 5:
                            # individual
                            return $this->result('continue', $this->display('individual_options'));
                        case 6:
                            return $this->result('continue', $this->display('land_use_options'));
                        case 7:
                            return $this->result('continue', $this->display('enter_bill_id'));
                    }
                }
                return $this->cancelLastAction($this->display('tax_payment_options'));
            /*
              $reply = $this->display("self_assessment") . "\n" . $this->display("payment_amount");
              $action = $this->continueSession();
              return $this->result($action, $reply);
             * 
             */
            case 5:
                // enter tax year
                return $this->result('continue', $this->display('enter_tax_year'));
            case 6:

                if ($this->validTaxYear($this->lastInputed)) {
                    //enter payerID or mobile number of tax payer
                    return $this->result('continue', $this->display('enter_tin'));
                }
                return $this->cancelLastAction($this->display('enter_tax_year'));
            case 7:
                if ($this->validTaxpayer($this->lastInputed)) {
                    return $this->result('continue', $this->display('payment_amount'));
                }
                return $this->cancelLastAction($this->display('enter_tin'));
            //enter payerID or mobile number of tax payer
            case 8:
                // Show payment summary
                $tax_category_id = $this->userInputs[3];
                $tax_id = ($tax_id <= 5 && $tax_id >= 1 ) ? $this->userInputs[4] : 1;
                $key = ($tax_category_id == 7) ? "$tax_category_id:1" : "$tax_category_id:$tax_id";
                $taxType = $this->getTaxType($key);
                $amount = number_format($this->parseNumeric($this->userInputs[7]), 2);
                $payer_id = $this->userInputs[6];
                $tax_year = $this->userInputs[5];
                $reply = "$taxType\n"
                        . $this->display("payer_id") . "$payer_id\n"
                        . $this->display("amount") . "$amount\n" .
                        $this->display("lbl_tax_year") . $tax_year
                        . "\n" . $this->display('mcash_note');
                $action = $this->continueSession();
                return $this->result($action, $reply);
            case 9:
                return $this->switchToPayment();
            default:
                break;
        }
    }

    private function validTaxYear($yr) {
        $currYr = (int) date('Y');
        try {
            $int = (int) $yr;
            return ($int <= $currYr) && ($int >= ($currYr - 50));
        } catch (Exception $e) {
            return false;
        }
    }

    private function getTaxType($key) {
        $types = [
            '1:1' => 'Witholding Tax - Rent',
            '1:2' => 'Witholding Tax - Professional Services',
            '1:3' => 'Witholding Tax - Contract',
            '1:4' => 'Witholding Tax - Directors Fees',
            '2:1' => 'Business Premises - Urban Registration',
            '2:2' => 'Business Premises - Urban Renewal',
            '2:3' => 'Business Premises - Rural Registration',
            '2:4' => 'Business Premises - Rural Renewal',
            '3:1' => 'Development Levy',
            '4:1' => 'Market Taxes & Levies',
            '5:1' => 'Individual - Self Assessment',
            '5:2' => 'Individual - PAYE',
            '6:1' => 'Land Use Fees',
            '7:1' => 'Other Taxes & Levies'
        ];
        return $types[$key];
    }

    private function validTaxpayer($id) {
        $url = "http://easytaxpayer.com/api/validatetin";
        /*
          $parameters = array('tin' => $id,
          'state' => 'Lagos',
          'username' => 'support',
          'password' > '07066192100');

          $headers = null;
          $result = Unirest\Request::get($url, $headers, $parameters);
         * var_dump($result->raw_body);
         * 
         */
        # remove all nonnumric characters

        if ($id == 0) {
            $temp = $this->getTempPayerInfo($this->posted['msisdn']);
            if ($temp->payer_id) {
                return true;
            }
        }
        $temp2 = $this->getTempPayerInfo($id);
        if ($temp2->payer_id) {
            return true;
        }
        $payerId = preg_replace('~\D~', '', $id);
        $result = json_decode(file_get_contents($url . "?username={$this->api_username}&password={$this->api_password}&state=Lagos&tin=$payerId"));

        $obj = $result->result;
        if ($obj->PayerID) {
            return true;
        }

        return false;
    }

    private function getPayerID() {
        switch ($this->currentStep) {
            case 3:
                return $this->result('continue', $this->display('enter_tin'));

            case 4:
                $id = $this->userInputs[3];
                return $this->getTaxpayerInfo($id);
        }
    }

    private function language() {
        return "Select language\n1. English\n2. Yoruba\n3. Pidgin";
    }
    private function api_credentials(){
        return ['username'=>'07066192100',
            'password'=>'demo'];
    }
}
