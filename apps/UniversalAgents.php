<?php

use Symfony\Component\Yaml\Yaml;

include_once 'UssdApplication.php';
include_once 'Mcash.php';
include_once 'Rave.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
require_once 'lib/classes/rb.php';
include_once 'classes/easytaxpayer/SendSms.php';

define('USSD_EXTENSION', '*20065*1#');
define('SMS_USERNAME', 'tony.okafor@universalinsuranceplc.com');
define('SMS_PASSWORD', 'Universal20065_');
define('SMS_TITLE', 'Universal');

/**
 *  Description Universal Insurance
 *
 * @author Emmanuel
 */

class UniversalAgents extends UssdApplication
{

    public $msisdn;
    private $merchant_code = "77000717";

    public function getResponse($body)
    {

        $this->initValues($body);

        switch ($this->currentStep) {
            case 1:

                
                $this->initShortCodeValues($body);

                $msisdn = '0' . substr($this->posted["msisdn"], -10);
                $amount = intval(trim(file_get_contents("http://localhost:8080/ords/universal/universal/price?phone=" . $msisdn)));

                if($amount > 1000){

                    $this->delete_value("isShortCode");
                    return $this->result("end", "Number not registerd as agent.\nKindly Dial *20065# to continue");

                }

                return $this->result("continue", $this->render("new"));


            case 2:
                $this->set_value("customer_name", trim($this->lastInputed));
                return $this->result("continue", $this->render("enter_vehicle_plate_number"));
            case 3:
                $this->set_value("customer_plate", trim($this->lastInputed));
                return $this->result("continue", $this->get_vehicle_details());
            case 4:
        
                if ($this->lastInputed == "1") {
                    return $this->result("continue", $this->render("add_bank"));
                }
                return $this->cancelLastAction("You entered a wrong option. \n" . $this->render("add_bank"));
                
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

    public function get_vehicle_details()
    {
        $registration_number = $this->get_value("customer_plate");
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

    public function initShortCodeValues($body)
    {

        $content = $this->posted['content'];
        $exploded = explode('*', $content);
        $remove = preg_replace("/[^A-Za-z0-9\-]/", "", $exploded);

        $cust_phone = $remove[3];
        $this->set_value("customer_phone", $cust_phone);
    }

    public function log_new_policy($make, $color)
    {
        $opts = $this->get_value("option");
        $msisdn = $this->get_value("customer_phone");

        $name = $this->get_value("customer_name");
        $plate_num = $this->get_value("customer_plate");
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
            R::exec($query, [1, $msisdn, $name, $plate_num, $vehicle_make, $vehicle_model, $color, $chassis]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function initiate_coral_pay($bankId)
    {
        $msisdn = '234' . substr($this->posted["msisdn"], -10);
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
        

        if (array_search($bankId, $bank_codes)) {

            $institutionCode = array_search($bankId, $bank_codes);
            $curl = curl_init();
            

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

    public function sendPaymentSms($msg)
    {

        $msisdn = '234' . substr($this->get_value("content_three"), -10);

        $message = urlencode($msg);
        file_get_contents('https://novajii.com/ords/sms/api/sms?username=' . SMS_USERNAME . '&password=' . SMS_PASSWORD . '&message=' . $message . '&sender=' . SMS_TITLE . '&destination=' . $msisdn);
        
        
    }

    public function logCoralPay($bank_message, $bank_reference, $bank_amount, $bank_transaction_id, $bank_trace_id)
    {
        $customer_phone = $this->get_value("customer_phone");
        $agent_phone = '0' . substr($this->posted["msisdn"], -10);
        $msisdn = "$customer_phone" ."+". "$agent_phone";

        //TODO: Setup renewal option for payments
        $option = 1;

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
        $arr = Yaml::parse(file_get_contents("apps/config/universal/agent_config.yml"));
        return $arr['pages'][$key];
    }
}
