<?php

use Symfony\Component\Yaml\Yaml;

include_once 'UssdApplication.php';
include_once 'Mcash.php';
include_once 'Rave.php';
include_once 'UniversalAgents.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
require_once 'lib/classes/rb.php';
include_once 'classes/easytaxpayer/SendSms.php';

define('USSD_EXTENSION', '*20065#');
define('SMS_USERNAME', 'tony.okafor@universalinsuranceplc.com');
define('SMS_PASSWORD', 'Universal20065_');
define('SMS_TITLE', 'Universal');

class Universal extends UssdApplication
{
    public function getResponse($body)
    {

        $this->initValues($body);

        $shortcode = $this->get_value("isShortCode");
        if ($shortcode) {
            $agents = new UniversalAgents();
            return $agents->getResponse($body);
        }


        switch ($this->currentStep) {
            case 1:
                if ($this->checkForShortString($body)) {
                    $this->set_value("isShortCode", true);
                    $agents = new UniversalAgents();
                    return $agents->getResponse($body);
                }
                return $this->result("continue", $this->render("welcome"));
            case 2:
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("u3p_action", "new");
                        return $this->flow($body);
                    case 2:
                        $this->set_value("u3p_action", "renew");
                        return $this->flow($body);
                    case 3:
                        $this->set_value("u3p_action", "check");
                        return $this->flow($body);
                    default:
                        return $this->cancelLastAction($this->render("welcome"));
                }
            default:
                return $this->flow($body);
        }
    }

    public function flow($body)
    {
        $mode = $this->get_value("u3p_action");
        if ($mode == "new") {
            return $this->getNewResponse($body);
        } elseif ($mode == "renew") {
            return $this->getRenewResponse($body);
        } elseif ($mode == "check") {
            return $this->displayPolicy($body);
        }
    }

    public function checkForShortString($body)
    {

        $content = $this->posted['content'];
        $exploded = explode('*', $content);
        $remove = preg_replace("/[^-0-9\.]/", "", $exploded);

        if (count($remove) > 2) {
            return true;
        }

        return false;
    }

    public function displayPolicy()
    {
        $curr = $this->currentStep;
        switch ($curr) {
            case 2:
                $this->set_value("option", $this->lastInputed);
                return $this->result("continue", $this->render("renewal"));
            case 3:
                $this->set_value("registration_no", $this->lastInputed);
                $plate_number = $this->get_value("registration_no");
                return $this->result("continue", $this->displayPolicyInfo($plate_number));
        }
    }
    public function displayPolicyInfo($plate_number)
    {
        # check policy from Universal

        $result = json_decode(file_get_contents("https://3rdparty.universalinsuranceonline.com/Universal_URL_API/USSD/Verify_RegNumber.aspx?UserID=USSD_2108&Password=LG34YXG57&APIKEY=KG69034WRVFPL987ZB&RegistrationNo=" . $plate_number));
        # too many if else makes code complex, you need to rewrite better if conditions
        if ($result->Status === 'Successful') {
            if ($result->Policy_Status === 'Active') {
                $status = $result->Status;
                $Policy_Number = $result->Policy_Number;
                $Registration_No = $result->Registration_No;
                $Expiration_Date = $result->Expiration_Date;
                $Policy_Status = $result->Policy_Status;
                return ("Universal Insurance Info" . "\nStatus: " . $status . "\nPolicy Number: " . $Policy_Number . "\nRegistration NO: " . $Registration_No . "\nExpiration Date: " . $Expiration_Date . "\nPolicy Status: " . $Policy_Status);
            } else if ($result->Policy_Status === 'Expired') {
                $status = $result->Status;

                $Policy_Number = $result->Policy_Number;
                $Registration_No = $result->Registration_No;
                $Expiration_Date = $result->Expiration_Date;
                $Policy_Status = $result->Policy_Status;
                return ("Universal Insurance Info" . "\nStatus:" . $status . "\nPolicy Number :" . $Policy_Number . "\nRegistration NO: " . $Registration_No . "\nExpiration Date: " . $Expiration_Date . "\nPolicy Status: " . $Policy_Status . "Dial *371*1# to renew policy");
            }
        } else if ($result->Status === 'Failed') {
            $status = $result->Status;
            $message = $result->Message;
            # Dont hard code details that could change
            return ("Status: $status\nMessage: $message\nDial " . USSD_EXTENSION . " to begin registration");
        }
    }

    public function getNewResponse($body)
    {
        $curr = $this->currentStep;
        $option = $this->lastInputed;
        switch ($curr) {
            case 2:
                $this->set_value("option", $this->lastInputed);
                return $this->result("continue", $this->render("new"));
            case 3:
                $this->set_value("name", $this->lastInputed);
                return $this->result("continue", $this->render("enter_vehicle_plate_number"));
            case 4:
                $this->set_value("registration", trim($this->lastInputed));
                return $this->result("continue", $this->get_vehicle_details());
            case 5:
                if ($option == "1") {
                    return $this->result("continue", $this->render("add_bank"));
                }
            case 6:
                $this->set_value("bank", $this->lastInputed);
                if ($this->lastInputed > 12) {
                    return $this->cancelLastAction("You entered a wrong option. \n" . $this->render("banks"));
                } else {
                    $bankId = $this->get_value("bank");
                    return $this->initiate_coral_pay(strval($bankId));
                }
        }
    }

    public function get_vehicle_details()
    {
        $registration_number = $this->get_value("registration");
        $result = json_decode(file_get_contents("https://novajii.com/web/vehicle/api/v1/verify-plate-number?plate_number=" . $registration_number));

        if ($result->status === 'success') {
            $status = $result->status;
            $make = $result->make;
            $color = $result->color;
            $plate_number = $result->plate_number;
            $this->log_new_policy($make, $color, $plate_number);
            return "Universal Insurance Info \nVehicle Information" . "\nVehicle Make: " . $make . "\nVehicle Color: " . $color . "\nRegistration Number: " . $plate_number . "\nPress 1 To Continue ";
        } else if ($result->status === 'failed') {
            $status = $result->status;
            $message = $result->message;
            return "Universal Insurance Info" . "\nStatus: " . $status . "\nMessage: " . $message . "\nPlease enter a registered vehicle Plate Number";
        } else {
            return "Result is null";
        }
    }

    public function log_new_policy($make, $color)
    {
        $opts = $this->get_value("option");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $name = $this->get_value("name");
        $plate_num = $this->get_value("registration");
        # handle empty
        $vehicle_model = isset($make) ? $make : "Unknown";
        $vehicle_make = isset($make) ? $make : "Unknown";
        $inital = (explode(" ", $make));
        # we can have problems here: Honda Civic will work but Matrix will not work
        # we only use the array if we have more than 1 value
        if (count($inital) > 1) {
            $vehicle_model = $inital['0'];
            $vehicle_make = $inital['1'];
        }

        $str = rand();
        $str_result = md5($str);
        $chassis = strtoupper($str_result);

        $query = "INSERT INTO universal_new_insurance_policy("
            . "payment_option, msisdn, names, registration, make, model, color, chassis)"
            . "VALUES(?,?,?,?,?,?,?,?)";
        try {
            R::exec($query, [$opts, $msisdn, $name, $plate_num, $vehicle_make, $vehicle_model, $color, $chassis]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function getRenewResponse($body)
    {
        $curr = $this->currentStep;
        $option = $this->lastInputed;
        switch ($curr) {
            case 2:
                $this->set_value("option", $this->lastInputed);
                return $this->result("continue", $this->render("renewal"));
            case 3:
                $this->set_value("registration", $this->lastInputed);
                $plate_num = $this->get_value("registration");
                return $this->result("continue", $this->getRenNewPolicyInfo($body, $plate_num));
            case 4:
                $last = $this->lastInputed;
                if ($last == "1") {
                    return $this->result("continue", $this->render("add_bank"));
                } else {
                    return $this->result('End', $this->render('error'));
                }
            case 5:
                $this->set_value("bank", $this->lastInputed);
                if ($this->lastInputed > 12) {
                    return $this->cancelLastAction("You entered a wrong option. \n" . $this->render("banks"));
                } else {
                    $bankId = $this->get_value("bank");
                    return $this->initiate_coral_pay(strval($bankId));
                }
        }
    }

    public function getRenNewPolicyInfo()
    {
        $plate_num = $this->get_value("registration");
        $result = json_decode(file_get_contents("https://3rdparty.universalinsuranceonline.com/Universal_URL_API/USSD/Verify_RegNumber.aspx?UserID=USSD_2108&Password=LG34YXG57&APIKEY=KG69034WRVFPL987ZB&RegistrationNo=" . $plate_num));
        if ($result->Status === 'Successful') {
            if ($result->Policy_Status === 'Active') {
                $status = $result->Status;
                $Policy_Number = $result->Policy_Number;
                $Registration_No = $result->Registration_No;
                $Expiration_Date = $result->Expiration_Date;
                $Policy_Status = $result->Policy_Status;
                return ("Status: " . $status . "\nPolicy Number: " . $Policy_Number . "\nRegistration NO: " . $Registration_No . "\nExpiration Date: " . $Expiration_Date . "\nPolicy Status: " . $Policy_Status);
            } else if ($result->Policy_Status === 'Expired') {
                $status = $result->Status;
                $Policy_Number = $result->Policy_Number;
                $Registration_No = $result->Registration_No;
                $Expiration_Date = $result->Expiration_Date;
                $Policy_Status = $result->Policy_Status;
                $call = $this->logRenewPolicy($Policy_Number);
                return ($call . "Status:" . $status . "\nPolicy Number :" . $Policy_Number . "\nRegistration NO: " . $Registration_No . "\nExpiration Date: " . $Expiration_Date . "\nPolicy Status: " . $Policy_Status . "\nPress 1 To Continue");
            }
        } else if ($result->Status === 'Failed') {
            $status = $result->Status;
            $message = $result->Message;
            return ("Status: $status\nMessage: $message");
        }
    }

    public function logRenewPolicy($Policy_Number)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $opts = $this->get_value("option");
        $registration_no = $this->get_value("registration");
        $query = "INSERT INTO universal_renew_insurance_policy("
            . "payment_option, msisdn, policy_num, plate_num) "
            . "VALUES (?,?,?,?)";
        try {
            R::exec($query, [$opts, $msisdn, $Policy_Number, $registration_no]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function initiate_coral_pay($bankId)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        # get the amount from a web service, so that we can update dynamically
        $amount = trim(file_get_contents("http://localhost:8080/ords/universal/universal/price?phone=" . $msisdn));
        $bank_codes = array(
            "zenithbank" => '1',
            "gtb" => '2',
            "uba" => '3',
            "stanbicbank" => '4',
            "sterlingbank" => '5',
            "unitybank" => '6',
            "keystonebank" => '7',
            "fidelitybank" => '8',
            "ecobank" => '9',
            "wemabank" => '10',
            "accessbank" => '11',
            "firstbank" => '12',
        );
        #return $this->result("end", "$msisdn:$amount");
        if (array_search($bankId, $bank_codes)) {

            $institutionCode = array_search($bankId, $bank_codes);
            $curl = curl_init();
            # dont hard code values, use variables
            #$url = "https://novajii.com/web/ussd-payment/api/generateRef?apikey=d7464c-107251-08d16e-fc5b31-761e22&bankId=$institutionCode&amount=10&channel=ussd&product=3rd_pty_insurance";
            # better way

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://novajii.com/web/ussd-payment/api/generateRef?apikey=d7464c-107251-08d16e-fc5b31-761e22&bankId=$institutionCode&amount=" . $amount . "&channel=ussd&product=3rd_pty_insurance",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return "an error occured\n please try again";
            } elseif ($response) {
                $request = json_decode($response);
                if ($request->ResponseCode == "000") {
                    $description = $request->Description;
                    $bank_message = $request->ResponseMessage;
                    $bank_reference = $request->Reference;
                    $bank_amount = $request->Amount;
                    $bank_transaction_id = $request->TransactionID;
                    $bank_trace_id = $request->TraceID;
                    $this->logCoralPay($bank_message, $bank_reference, $bank_amount, $bank_transaction_id, $bank_trace_id);
                    # avoid hard coding messages. This should be in a config file
                    $msg = $description . " to Universal Insurance Plc";
                    $this->sendPaymentSms($msg);
                    return $this->result("end", $msg);
                }
            } else {
                return "Something went wrong\nPlease try again";
            }
        } else {

            return 'false';
        }
    }

    // public function Message($institutionCode){

    // }

    public function sendPaymentSms($msg)
    {

        $msisdn = $this->posted["msisdn"];
        $message = urlencode($msg);
        file_get_contents('https://novajii.com/ords/sms/api/sms?username=' . SMS_USERNAME . '&password=' . SMS_PASSWORD . '&message=' . $message . '&sender=' . SMS_TITLE . '&destination=' . $msisdn);
        /*
        file_get_contents("https://novajii.com/web/bulksms/all/send?network=etisalat&msg=$message&src=371&msisdn=$msisdn");
        file_get_contents("https://novajii.com/web/bulksms/all/send?network=airtel&msg=$message&src=371&msisdn=$msisdn");
        file_get_contents("https://novajii.com/web/bulksms/all/send?network=mtn&msg=$message&src=371&msisdn=$msisdn");
         */
        # use bulk SMS to send SMS alert
    }

    public function logCoralPay($bank_message, $bank_reference, $bank_amount, $bank_transaction_id, $bank_trace_id)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $option = $this->get_value("option");

        $query = "INSERT INTO universal_pre_payment_logs("
            . "message, msisdn, payment_option, reference, amount, transcation_id, trace_id) "
            . "VALUES (?,?,?,?,?,?,?)";
        try {
            R::exec($query, [$bank_message, $msisdn, $option, $bank_reference, $bank_amount, $bank_transaction_id, $bank_trace_id]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }
    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("apps/config/universal/config.yml"));
        return $arr['pages'][$key];
    }
}
