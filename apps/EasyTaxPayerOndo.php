<?php

include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
include_once 'Mcash.php';

use Symfony\Component\Yaml\Yaml;

class EasyTaxPayerOndo extends UssdApplication
{

    private $api_username;
    private $api_password;
    # 77000717 is NOVAJI account
    #Added New Repo
    private $merchant_code = "77000717";
    private $database = "easytaxpayer.development";
    private $back_option = "\n0. Go Back";

    function __construct()
    { }

    public function getResponse($body)
    {
        //global $redis;
        $action = "continue";
        $reply = $this->display('welcome');
        $this->initValues($body);
        switch ($this->currentStep) {
            case 1:
                # show introduction
                return $this->result('continue', $this->display("intro"));
            case 2:
                # show main menu
                return $this->result('continue', $this->display('welcome'));
            case 3:
                switch ($this->lastInputed) {
                    case 1:
                        // Registration 
                        $this->set_value("task", "registration");
                        return $this->doRegistration();
                    case 2;
                        // Get Payer ID
                        $this->set_value("task", "get_payer_id");
                        return $this->getPayerID();
                    case 3:
                        //  Payment
                        $this->set_value("task", "pay_tax");
                        return $this->payTax();

                    case 4:
                        // Calculator
                        $this->set_value("task", "calculate_tax");
                        return $this->result("continue", $this->display('calculator_options'));
                    case 5:
                        // Tax Officer
                        $this->set_value("task", "tax-officer");
                        return $this->checkTaxPayer();
                    default:
                        return $this->cancelLastAction($this->display("welcome"));
                }
            default:
                # main entry into the service
                return $this->main($body);
        }
        return $this->result($action, $reply);
    }

    private function checkTaxPayer()
    {
        $curr = $this->currentStep;
        switch ($curr) {
            case 3:
                #check if user is allowed access to end session
                if ($this->isTaxOfficer()) {
                    return $this->result('continue', "TaxPayer Verification\nEnter TaxPayer Mobile Number:");
                }
                return $this->result('end', "You are not authorized to perform this task");
            case 4:
                # Check last payment
                $msisdn = $this->lastInputed;
                return $this->getPayerID($msisdn);
        }
    }

    private function isTaxOfficer()
    {
        $msisdn = $this->posted["msisdn"];
        $url = "http://notification.novajii.com/web/easytaxpayer/development/check-compliance?msisdn=$msisdn";
        $result = json_decode(file_get_contents($url));
        #$not_registered = "Mobile number not registered as TaxID";
        if ($result && (isset($result->status))) {
            if (strtolower($result->status) == "success") {
                return true;
            }
            return false;
        }
        return false;
    }

    // help us render a display to ussd menu
    private function display($key)
    {
        $langId = $this->userInputs[1];
        $id = empty($langId) ? "1" : $langId;
        $config_file = "apps/config/easytaxpayerondo/config.yml";
        $arr = Yaml::parse(file_get_contents($config_file));
        $pages = $arr['pages'];
        return $pages[$key];
    }

    public function main($body)
    {
        $task = $this->get_value("task");
        $reply = $this->display("new_payer");
        $action = $this->endSession();
        switch ($task) {
            case "registration":
                //$reply = $this->display("new_payer");
                #return $this->registerNewTaxPayer();
                return $this->doRegistration();
                //break;
            case "get_payer_id":
                //$mobile = $this->userInputs[3];
                return $this->getPayerID();
                //break;
            case "pay_tax":
                return $this->payTax();
            case "calculate_tax":
                /* 0=intro
                 * 1= welcome
                 * 2 = self or paye
                 * 3 = do self or do paye 
                 * 
                 */
                $tax_type = $this->userInputs[3];
                #$tax_type = $this->lastInputed;
                switch ($tax_type) {
                    case 1:
                        return $this->doSelfAssessment();
                    case 2:
                        return $this->calculatePaye();
                    default:
                        # invalid input, start again
                        #Ussd::deleteLastInput($this->inputedKey);
                        return $this->cancelLastAction($this->display('calculator_options'));
                }
            case "tax-officer":
                return $this->checkTaxPayer();
            case 5:
                return $this->taxInfo();
        }
        return ['action' => $action, 'message' => $reply];
        //return $reply;
    }

    private function getSelfAssessmentAmount()
    {
        $amt = $this->userInputs[4];
        if (is_numeric($amt)) {
            return $amt;
        }
        return 0.00;
    }

    private function calculateSelfAssessment($amt)
    {
        $rate = $this->minimumTaxRate();
        $tax = 0.00;
        if ($amt > 0) {
            $tax = $amt * ($rate / 100);
        }
        return $tax;
    }

    private function minimumTaxRate()
    {
        return 1.0;
    }

    private function calculatePaye()
    {
        $current_step = $this->currentStep;
        $heading = $this->display('PAYE') . "\n";
        switch ($current_step) {
            case 4:
                return $this->result('continue', $heading . $this->display('monthly_basic'));
            case 5:
                //return $this->result('continue', $heading . $this->display('input_pension_rate'));
                $paye = $this->computePAYE();
                return $this->result(
                    'end',
                    "{$this->display('lbl_estimated')} " . $heading . "{$this->display('lbl_gross')} " . number_format($paye['gross'], 2) .
                        "\n{$this->display('lbl_takehome')} " . number_format($paye['net'], 2) .
                        "\n{$this->display('lbl_tax')} " . number_format($paye['tax'], 2) .
                        "\n{$this->display('lbl_deductions')} " . number_format($paye['deductions'], 2) .
                        "\n{$this->display('lbl_freepay')} " . number_format($paye['tax_free'], 2) .
                        "\n{$this->display('lbl_pension')} " . number_format($paye['pension_pct'], 2) .
                        "\n{$this->display('lbl_nhf')} " . number_format($paye['nhf_pct'], 2) .
                        "\n{$this->display('lbl_tax_rate')} " . number_format($paye['tax_rate'], 2)
                );
            default:
                break;
        }
    }

    private function doSelfAssessment()
    {
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

    private function computePAYE()
    {
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
        $excemption += $pension + $nhf + $relief;
        if ($annual <= $excemption) {
            $excemption = $income;
        } else {
            $taxable = $income - $excemption;
        }

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

    private function taxRelief($annual)
    {
        return ($annual * 0.2) + 200000;
    }

    public function doRegistration()
    {
        $curr = $this->currentStep;
        $title = $this->display('lbl_registration_title');
        switch ($curr) {
            case 3:
                # enter fullname
                return $this->result('continue', "$title:\n" . $this->display('enter_taxpayer_fullname'));
            case 4:
                # save name and mobile number
                $this->set_value("new_taxpayer_name", $this->lastInputed);
                $this->set_value("new_taxpayer_mobile", $this->posted["msisdn"]);
                # enter email
                return $this->result('continue', "$title:\n" . $this->display('enter_email'));
            case 5:
                # save email and show confirmation
                $this->set_value("new_taxpayer_email", $this->lastInputed);
                return $this->confirmRegistration();

            case 6:
                return $this->register();
        }
    }

    public function registerNewTaxPayer()
    {
        $curr = $this->currentStep;
        $title = $this->display('lbl_registration_title');
        switch ($curr) {
            case 3:
                # Choose self or others
                return $this->result('continue', "$title:\n" . $this->display('self_others'));
            case 4:
                # user entered 1=self or 2=others
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("tax_registration_type", "self");
                        return $this->result('continue', $this->display('enter_taxpayer_fullname'));
                    case 2:
                        # others
                        $this->set_value("tax_registration_type", "other");
                        return $this->result('continue', "$title:\n" . $this->display('enter_mobile'));
                    default:
                        return $this->cancelLastAction("$title:\n" . $this->display('self_others'));
                }
            case 5:
                # set name for when user registers himself
                $registration_type = $this->get_value("tax_registration_type");
                if ($registration_type == "self") {
                    $this->set_value("new_taxpayer_name", $this->lastInputed);
                    $this->set_value("new_taxpayer_mobile", $this->posted["msisdn"]);
                    return $this->confirmRegistration();
                }
                # set name for others
                $this->set_value("new_taxpayer_mobile", $this->lastInputed);
                return $this->result('continue', $this->display('enter_taxpayer_fullname'));


            case 6:
                $registration_type = $this->get_value("tax_registration_type");
                #  register for self
                if ($registration_type == "self") {
                    $this->set_value("new_taxpayer_mobile", $this->posted['msisdn']);
                    return $this->register();
                }
                # show final confirmation for others
                $this->set_value("new_taxpayer_name", $this->lastInputed);
                return $this->confirmRegistration();

            case 7:
                # only if registering for others
                $registration_type = $this->get_value("tax_registration_type");
                if ($registration_type == "other") {
                    return $this->register();
                }
                return $this->result('end', $this->display('invalid_request'));
            default:
                return $this->result('end', $this->display('invalid_request'));
        }
    }

    private function confirmRegistration()
    {
        #$bio = $this->getUserInfo($this->posted['msisdn']);
        #return $this->result('continue', $this->display('enter_taxpayer_fullname'));
        #$name = $this->userInputs[4];
        $msisdn = "0" . substr($this->get_value("new_taxpayer_mobile"), -10);
        $name = strtoupper($this->get_value("new_taxpayer_name"));
        $email = strtolower($this->get_value("new_taxpayer_email"));
        #$this->set_value("new_taxpayer_name", $name);
        return $this->result('continue', $this->display('confirm_registration') .
            "{$this->display('lbl_name')} " . $name .
            "\nPhone: " . $msisdn .
            "\nEmail: " . $email .
            "\n" . $this->display('confirm'));
    }

    /* private function getTempPayerInfo($mobile) {
      $url = "http://easytaxpayer.com/api/getpayerid";
      $result = json_decode(file_get_contents($url
      . "?username=07066192100&password=demo&state=Lagos&mobile=$mobile"));
      return $result->result;
      } */

    private function getTempPayerInfo($mobile)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://easytaxpayer.com/web/api/getpayerid",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "username=07066192100&password=Amore123_&phone=" . $mobile . "&state=Lagos",
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return $err;
        } else {
            return $response;
        }
    }

    private function getUserInfo($mobile)
    {
        return ['name' => 'Taiwo Emeka Ibrahim'];
    }

    private function taxInfo()
    {
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

    private function subscribeToTaxInfo($mobile)
    { }

    private function payTax()
    {
        $back = $this->display("back");
        $main_menu = $this->display('tax_payment_options');
        $entry_step = 3;
        switch ($this->currentStep) {
            case 2:
                return $this->result('continue', $this->display('welcome'));
            case 3:
                //return $this->result('continue', $this->display('enter_tin'));
                return $this->result('continue', $main_menu);
            case 4:
                # show the products per category
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
                    default:
                        return $this->cancelLastAction($main_menu);
                }
            case 5:
                #enter tax year
                return $this->result('continue', $this->display('enter_tax_year'));

            case 6:
                # enter TIN number
                if ($this->validTaxYear($this->lastInputed)) {
                    //enter payerID or mobile number of tax payer
                    return $this->result('continue', $this->display('enter_tin'));
                }
                return $this->cancelLastAction($this->display('enter_tax_year'));
            case 7:
                $this->set_value("mobile_payer_id", $this->lastInputed);
                # enter payment amount
                if ($this->validTaxpayer($this->lastInputed)) {
                    return $this->result('continue', $this->display('payment_amount'));
                }
                return $this->cancelLastAction($this->display('enter_tin'));
            case 8:
                // Show payment summary
                $tax_category_id = $this->userInputs[3];
                $tax_id = ($tax_id <= 5 && $tax_id >= 1) ? $this->userInputs[4] : 1;
                $key = ($tax_category_id == 7) ? "$tax_category_id:1" : "$tax_category_id:$tax_id";
                $this->set_value("tax_code", $key);
                $taxType = $this->getTaxType($key);
                $this->set_value("tax_type", $taxType);
                $amount = number_format($this->parseNumeric($this->userInputs[7], 2));
                $this->set_value("tax_amount", $amount);
                $product_id = $this->userInputs[4];
                $this->set_value("category_id", $product_id);
                $category_id = $this->userInputs[3];
                $this->set_value("category", $category_id);
                $payer_id = $this->userInputs[6];
                $this->set_value("payer_id", $payer_id);
                $tax_year = $this->userInputs[5];
                $this->set_value("tax_year", $tax_year);
                $reply = "$taxType\n"
                    . $this->display("payer_id") . "$payer_id\n"
                    . $this->display("amount") . "$amount\n" .
                    $this->display("lbl_tax_year") . $tax_year
                    . "\n" . $this->display('mcash_note');
                $action = $this->continueSession();
                $msisdn = '0' . substr($this->posted["msisdn"], -10);
                $state_id = ("*372*6");
                $call = $this->logDetails($msisdn, $payer_id, $tax_year, $state_id, $category_id, $amount, $product_id);
                return $this->result($action, $reply, $call);

            case 9:
                # show bank opions
                $amount = $this->get_value("tax_amount");
                return $this->choose_bank($amount);
            case 10:
                # launch payment
                return $this->do_payment();
            default:
                break;
        }
    }

    public function logDetails($msisdn, $payer_id, $tax_year, $state_id, $category_id, $amount, $product_id)
    {

        $query = "INSERT INTO easytaxpayer_payments("
            . "client_msisdn, payer_id, payment_year, state_id, category_id, amount, product_id)"
            . "VALUES(?,?,?,?,?,?,?)";

        try {
            R::exec($query, [$msisdn, $payer_id, $tax_year,  $state_id, $category_id, $amount, $product_id]);
        } catch (Exception $ex) {
            return   $ex->getMessage();
        }

    }


    private function validTaxYear($yr)
    {
        $currYr = (int) date('Y');
        try {
            $int = (int) $yr;
            return ($int <= $currYr) && ($int >= ($currYr - 50));
        } catch (Exception $e) {
            return false;
        }
    }

    private function getTaxType($key)
    {
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

    private function get_merchant_code_by_tax_type($key = "1.1")
    {
        $types = [
            '1:1' => '77000717',
            '1:2' => '77000717',
            '1:3' => '77000717',
            '1:4' => '77000717',
            '2:1' => '77000717',
            '2:2' => '77000717',
            '2:3' => '77000717',
            '2:4' => '77000717',
            '3:1' => '77000717',
            '4:1' => '77000717',
            '5:1' => '77000717',
            '5:2' => '77000717',
            '6:1' => '77000717',
            '7:1' => '77000717'
        ];
        return "77000717";
    }

    private function validTaxpayer($id)
    {
        #$url = "http://easytaxpayer.com/api/validatetin";
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
        /*
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
         */
        return true;
    }

    private function getPayerID($mobile_number = null)
    {
        $main_menu = $this->display("welcome") . $this->display("back");
        $msisdn = isset($mobile_number) ? $mobile_number : $this->posted["msisdn"];
        $url = "http://notification.novajii.com/web/easytaxpayer/development/last-payment?msisdn=$msisdn";
        $result = json_decode(file_get_contents($url));
        $not_registered = "Mobile number not registered as TaxPayer";
        if ($result && (isset($result->status))) {
            if (strtolower($result->status) == "success") {
                $name = $result->name;
                $phone = $result->phone;
                $payer_id = $result->payer_id;
                $last_payment = $result->last_payment;
                $amt = $result->amount;
                return $this->result('end', "TaxPayer Info\nName: $name\nPayerID: $payer_id"
                    . "\nLast Payment:$last_payment\nAmount:NGN$amt");
            }
            return $this->cancelLastAction("$not_registered");
        }
        return $this->cancelLastAction("$not_registered");
    }

    private function language()
    {
        return "Select language\n1. English\n2. Yoruba\n3. Pidgin";
    }

    private function api_credentials()
    {
        return [
            'username' => '07066192100',
            'password' => 'demo'
        ];
    }

    public function choose_bank($amount)
    {
        return $this->select_bank($amount, $this->merchant_code);
        $mcash = new Mcash();
        # set merchant code, very important
        $mcash->set_merchant_code($this->merchant_code);
        $msisdn = $this->posted["msisdn"];
        return $mcash->prepayment($msisdn, $amount);
    }

    public function do_payment()
    {

        #return $this->result("End", "Pay With Bank: " . $option);
        # parse this in comma separated values fo processing
        $vals = "easytaxpayer.development," . $this->get_value("payer_id")
            . "," . $this->get_value("tax_year")
            . "," . $this->get_value("tax_type")
            . "," . $this->get_value("tax_code");
        return $this->initiate_payment($this->merchant_code, $vals);
        $mcash = new Mcash();
        $mcash->set_merchant_code($this->merchant_code);
        $msisdn = $this->posted["msisdn"];
        $option = $this->posted["content"];
        $bank_id = $mcash->get_bank_id($msisdn, $option);
    }

    public function register()
    {
        $msisdn = $this->get_value("new_taxpayer_mobile");
        $name = urlencode($this->get_value("new_taxpayer_name"));
        $email = urlencode($this->get_value("new_taxpayer_email"));
        $url = "http://notification.novajii.com/web/easytaxpayer/development/register?"
            . "msisdn=$msisdn&name=$name&email=$email";
        try {
            $result = json_decode(file_get_contents($url));
            if ($result && (isset($result->status))) {
                if (strtolower($result->status) == "success") {
                    return $this->result('end', "{$this->display('lbl_new_payer_created')}");
                }
                return $this->result('end', "{$this->display('lbl_new_payer_created')}");
                #return $this->result('end', $this->display('registration_failed'));
            }
        } catch (Exception $e) {
            return $this->result('end', $e->getMessage());
        }
        #return $this->result('end', $this->display('registration_failed'));
    }
}
