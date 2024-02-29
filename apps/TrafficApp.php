<?php

use Unirest\Request as Unirest;
use Unirest\Request\Body as Body;
use Carbon\Carbon;


//use lib\classes\ConnectDB;
require_once 'lib/classes/rb.php';

define("CHARGE", '5000');

require_once 'init.php';
include_once 'UssdApplication.php';
//include_once '_init.php';
require_once 'vendor/autoload.php';
include_once 'apps/classes/assotel/Api.php';

class TrafficApp extends UssdApplication
{

    private $action;
    public $mobile;
    private $service = '1';
    private $servicename;
    private $serviceplan;
    private $slot;
    private $notifyplan;
    private $expire;
    private $input_count;
    public $reply;
    private $lastInput;
    private $inputKey;
    private $inputs;
    public $transactionid;
    private $billStatus = false;
    private $response;
    private $transactionCode;
    private $extTransactionId;
    private $contextMsg;
    private $billingInterval;
    private $notifyInterval;
    private $description;
    public $network;
    public $billingCode = "traffic10";
    private $charge = "1000";

    # DB params
    /*
     * @var string
     */

    /*
      private $user = 'novaji';
      private $host = 'localhost';
      private $dbpass = 'Amore23_';
      private $database = 'novaji_introserve';
     */

    public function __construct()
    {
        // Establish connection to database
        //$this->connect();
        UssdApplication::connect();
        // Pull USSD service data
        $result = R::findOne('novaji_vas_services', 'id = ?', [$this->service]);
        $this->servicename = $result->name;
        $this->serviceplan = $result->billingplan;
        $this->slot = $result->freeslots;
        $this->notifyplan = $result->notificationplan;
        $this->expire = $result->expiredate;
        $this->billingInterval = $result->billinginterval;
        $this->notifyInterval = $result->notificationinterval;
    }

    public function getResponse($body)
    {
        #return $this->result("continue", "Trafficinfo");
        $this->initValues($body);

        $val = str_replace(['*', '#'], ['/', ''], $body['content']);
        $var = explode('/', $val);
        array_shift($var);
        $this->action = $this->continueSession();

        # Mobile number
        $this->mobile = "234" . substr($body['msisdn'], -10);

        $userKey = Ussd::getUserKey($body['msisdn']);
        $this->inputKey = Ussd::getInputKey($body['msisdn']);
        $this->inputs = Ussd::getUserInputs($this->inputKey);
        $this->lastInput = $body['content'];
        $this->network = $body['src'];

        $this->input_count = count($this->inputs);

        # Check if msisdn is registered on Traffic info
        $registered = $this->isMsisdnRegeistered($this->mobile);
        if ($registered == null) {
            $this->reply = "Mobile number not registered on Traffic Info. Please press 1 to register";
            $this->action = $this->continueSession();
        } else {
            $this->billStatus = true;
            $this->runApp(); // Call google API
            #$this->reply = $registered->msisdn;
        }


        if ($this->input_count == 2 && $this->lastInput == '1') {

            if ($this->network == "airtel") {
                $pricingNote = $this->getPricingNote($this->network);
                $this->reply = "Enter 1 to confirm registration. $pricingNote";
                $this->action = $this->continueSession();
            } else {
                $pricingNote = $this->getPricingNote($this->network);
                $this->reply = "Enter 1 for Auto-renwal and 2 for One-off subscription. $pricingNote";
                $this->action = $this->continueSession();
            }
        }

        if ($this->input_count == 3) {
            if ($this->lastInput == '1') {
                if ($this->network == "etisalat") {
                    $this->initiateBilling($this->mobile); // Initial billing
                    // Register mobile number
                    $this->addSubscriberToTrafficInfo(); // Add subscriber to traffic info
                    if ($this->transactionid == 200) {
                        $this->logBilling($this->mobile, "registered");
                    }
                    $this->reply = $this->display('register');
                }
                if ($this->network == "airtel") {

                    $result = $this->activateAirtel($this->mobile);
                    #$this->reply = "Success";
                    if ($result) {

                        if ($result->errorCode == "1000") {                            #
                            $this->reply = $this->display('register');
                            $this->sendMessage($this->reply);
                        } elseif ($result->errorCode == "3004") {
                            $this->reply = $this->display('low_balance');
                        } else {
                            $this->reply = $this->process_se_response($result);
                            #$this->reply = "$result->errorMsg ($result->errorCode)";
                            #$this->reply = "";
                        }
                    }
                    #$this->reply = "Airtel Activation successful";
                    $this->action = $this->continueSession();
                }
            }
        }

        if ($this->input_count == 3) {
            if ($this->lastInput == '2') {
                if ($this->network == "etisalat") {
                    $this->initiateBilling($this->mobile); // Initial billing
                    // Register mobile number
                    $this->addSubscriberToTrafficInfo(); // Add subscriber to traffic info
                    if ($this->transactionid == 200) {
                        $this->logBilling($this->mobile, "registered");
                    }
                    $this->reply = $this->display('register');
                }
            }
        }
        return ['action' => $this->action, 'message' => $this->reply];
    }

    public function process_se_response($result)
    {
        if ($result->errorCode == "5021") {
            return "You will receive an SMS from 9001 shortly. Press reply 1 to confirm";
        }
        return "{$result->errorMsg} ({$result->errorCode})";
    }

    public function addSubscriberToTrafficInfo()
    {
        $this->addSubscriberMobiletoDB($this->mobile);
        $this->reply = $this->display('register');
        $this->sendSMS();
        $this->action = $this->endSession();
    }

    /*
      protected function connect() {
      return R::setup('mysql:host=' . $this->host . ';dbname=' . $this->database, $this->user, $this->dbpass);
      }
     * 
     */

    protected function getUSSDService()
    {
        return R::findOne('novaji_vas_services', 'id = ?', [$this->service]);
    }

    public function isMsisdnRegeistered($mobile)
    {
        return R::findOne('novaji_vas_subscriptions', 'msisdn = ?', [$this->mobile]);
    }

    protected function getAllocatedSlot($mobile)
    {
        $query = "select r.* from requests r "
            . "join novaji_vas_subscriptions s "
            . "on r.msisdn = s.msisdn "
            . "where r.requestdate > s.subscriptiondate "
            . "and r.serviceid = " . $this->service . " "
            . "and r.msisdn = " . $mobile;

        return R::getAll($query);
    }

    protected function logUSSDRequest($from, $to)
    {
        $query = "INSERT INTO requests("
            . "serviceid, msisdn, requestdate, location, destination) "
            . "VALUES (?,?,?,?,?)";

        try {
            R::exec($query, [$this->service, $this->mobile, Carbon::now(), $from, $to]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function getSubcribersDatePlan()
    {
        switch ($this->serviceplan) {
            case "hourly":
                return Carbon::now()->addHours($this->billingInterval);
                break;
            case "weekly":
                return Carbon::now()->addWeekdays($this->billingInterval);
                break;
            case "daily":
                return Carbon::now()->addDays($this->billingInterval);
                break;
            case "monthly":
                return Carbon::now()->addMonths($this->billingInterval);
                break;
        }
    }

    public function getNotifyDatePlan()
    {
        switch ($this->notifyplan) {
            case "hourly":
                return Carbon::now()->addHours($this->billingInterval)->subHours($this->notifyInterval);
                break;
            case "weekly":
                return Carbon::now()->addWeekdays($this->billingInterval)->subWeekdays($this->notifyInterval);
                break;
            case "daily":
                return Carbon::now()->addDays($this->billingInterval)->subDays($this->notifyInterval);
                break;
            case "monthly":
                return Carbon::now()->addMonths($this->billingInterval)->subMonths($this->notifyInterval);
                break;
        }
    }

    public function addSubscriberMobiletoDB($mobile)
    {
        $query = "INSERT INTO novaji_vas_subscriptions("
            . "msisdn, subscriptiondate, expiredate, notificationdate, service_id, mno, created_at, updated_at) "
            . "VALUES (?,?,?,?,?,?,?,?)";

        $date = date("Y-m-d H:i:s");
        // Subscribers Date Plan
        $sub = $this->getSubcribersDatePlan();
        //		$subplan = date("'". $sub->year .'-'. $sub->month .'-'. $sub->day ." ". $sub->hour .":". $sub->minute .":". $sub->second ."'");
        $subplan = date("Y-m-d H:i:s", strtotime($sub));

        // Subscriber's notify date plan
        $notify = $this->getNotifyDatePlan();
        //		$notifyplan = date("'". $notify->year .'-'. $notify->month .'-'. $notify->day ." ". $notify->hour .":". $notify->minute .":". $notify->second ."'");
        $notifyplan = date("Y-m-d H:i:s", strtotime($notify));

        try {
            //			R::exec($query, [$mobile, Carbon::now(), $this->getSubcribersDatePlan(), $this->getNotifyDatePlan(), $this->service, $this->network, Carbon::now(), Carbon::now()]);
            R::exec($query, [$mobile, $date, $subplan, $notifyplan, $this->service, $this->network, $date, $date]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function logBilling($mobile, $action)
    {
        $strMobile = '0' . substr($mobile, -10);

        $query = "INSERT INTO novaji_etisalat_direct_bills("
            . "ext_transid, transid, msisdn, code, amount, description, contextmsg, service_name,tag,status_code) "
            . "VALUES(?,?,?,?,?,?,?,?,?,?)";

        try {
            R::exec($query, [$this->extTransactionId, $this->transactionCode, $strMobile, 200, 1000, $action, $this->contextMsg, "traffic10", $action, 200]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function setBillingStatus($param)
    {
        $str = '234' . substr($param, -10);

        try {
            R::exec('UPDATE novaji_vas_subscriptions SET enable_billing="yes" WHERE msisdn = ?', [$str]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    protected function getLocationViaGoogle($currLocation, $destination)
    {
        $data = [
            "origination" => $currLocation,
            "destination" => $destination,
            "msisdn" => $this->mobile,
            "requesttype" => "3",
            "charged" => "0",
            "transactionid" => time()
        ];

        $headers = array('Accept' => 'application/json', 'x-api-key' => '43212');
        $body = Body::Form($data);
        $response = Unirest::post("http://trafficinfo.com.ng/TrafficApi/api/trafficinforequest?format=json", $headers, $body);
        $this->logResult($response->raw_body);
        return json_decode($response->raw_body);
    }

    public function getLocationViaGoogle2($currLocation, $destination)
    {

        $service_url = 'http://trafficinfo.com.ng/TrafficApi/api/trafficinforequest?format=json';

        $curl = curl_init($service_url);

        $data = [
            "transactionid" => time(),
            "origination" => $currLocation,
            "destination" => $destination,
            "msisdn" => $this->mobile,
            "requesttype" => "3",
            "charged" => "0"
        ];

        curl_setopt($curl, CURLOPT_PROXY, "157.130.138.178");
        curl_setopt($curl, CURLOPT_PROXYPORT, "53281");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-API-KEY: 43212'));

        $response = curl_exec($curl);

        if ($response === false) {
            $info = curl_getinfo($curl);
            curl_close($curl);
            die('error occured during curl exec. Additioanl info: ' . var_export($info));
        }
        curl_close($curl);
        $decoded = json_decode($response);

        if (isset($decoded->response->status) && $decoded->response->status == 'ERROR') {
            die('error occured: ' . $decoded->response->errormessage);
        }


        $this->logResult($response);

        return $decoded;
    }

    public function display($key)
    {
        $options = array(
            'intro' => "Welcome to Traffic-Info.\nFrom where, (Example format: Ikeja, Lagos).",
            'destination' => "To where, (Example format: Eko hotel VI, Lagos)",
            'directions' => "Send me \n1. Time & distance info \n2. Full direction",
            'exit' => "Dear Customer, your request was successfully cancelled. Dial *371#",
            'register' => $this->getSubscriptionNote(),
            'try_again' => "A problem occured. Please try again later",
            'low_balance' => "Your balance is insufficient to subscribe to this service. Kindly top up and try again",
        );

        return $options[$key];
    }

    public function runApp()
    {
        # step 1 - Current location
        if ($this->input_count == 1) {
            $this->reply = $this->display('intro');
            $this->action = $this->continueSession();
        }

        if ($this->lastInput == '0') {
            $this->reply = $this->display('exit');
            $this->action = $this->endSession();
        }

        #step 2 - Destination
        if ($this->input_count == 2) {
            $this->reply = $this->display('destination');
            $this->action = $this->continueSession();
        }

        #step 3 - Returned description
        if ($this->input_count == 3) {
            $this->reply = $this->display('directions');
            $this->action = $this->continueSession();
        }

        #step 4 - Result
        if ($this->input_count == 4) {
            if ($this->network == "etisalat") {
                $this->runFor9Mobile();
            }
            if ($this->network == "airtel") {
                $this->runForAirtel();
            }
            /*
              if ($this->lastInput == 2) {

              } else {
              $this->reply = "You will receive your request by SMS shortly";
              }
             */
            $this->action = $this->endSession();
        }
    }

    protected function sendSMS()
    {
        try {
            file_get_contents(
                "http://sms.novajii.com/send?"
                    . "username=oracle&"
                    . "password=oracle&"
                    . "to=" . $this->mobile . "&"
                    . "from=371&"
                    . "content=" . urlencode($this->display('register')) . "&"
                    . "dlr=yes&"
                    . "dlr-url=http://portal.novajii.com/smsgw/dlr&dlr-level=3&dlr-method=GET"
            );
        } catch (Exception $ex) {
            $ex->getMessage();
        }
    }

    protected function buildLocationRequest()
    {
        $info = $this->getLocationViaGoogle($this->inputs[1], $this->inputs[2]);
        $opts = [1, 2];

        $headers = array('Accept' => 'multipart/form-data');
        $message = '';

        if (in_array($this->lastInput, $opts)) {
            switch ($this->lastInput) {
                case 1:
                    $this->reply = "From: " . $this->inputs[1] . "\nTo: " . $this->inputs[2] . "\nDuration: " . $info->duration . "\nDistance: " . $info->distance;
                    break;
                case 2:
                    $this->reply = "From: " . $this->inputs[1] . "\nTo: " . $this->inputs[2] . "\nDuration: " . $info->duration . "\nDistance: " . $info->distance;
                    // SMS call.

                    if ($info->responsecode == "1002") {
                        $message = "Oops your origination and destination is ambiguous. Kindly make your request less ambiguous. For example: "
                            . "origination: ikeja. destination: surulere.";
                    } else {
                        $ret = preg_replace("/\([^)]+\)/", "", $info->direction);
                        $message = str_replace(' ->', '.', $ret);
                    }

                    $this->trimMessage($message);
                    break;
                default:
                    $this->reply = $this->display('intro');
                    Ussd::deleteLastInput($this->inputKey);
                    break;
            }
        } else {
            $this->reply = $this->display('intro');
            Ussd::deleteLastInput($this->inputKey);
        }
    }

    public function initiateBilling($mobile)
    {
        $headers = array(
            'cache-control' => 'no-cache',
            'username' => 'novaji',
            'authorization' => '3C5063779C81C6A11188BD8C55D0B85C3DF196622A4AF0DAC6DA1800916E89E6',
            //'ocp-apim-subscription-key' => '2a998b87f8a843909915fdd3ef5aa662',
            'ocp-apim-subscription-key' => 'b36285d9e7ae43e4aef5ef53bd0b7247',
            'content-type' => 'application/json'
        );

        $body = Body::json(array(
            "serviceName" => $this->billingCode,
            "msisdn" => '0' . substr($mobile, -10),
            "id" => time(),
            "amount" => $this->charge
        ));

        //		$response = Unirest::post("https://directbill.etisalat.com.ng/sync", $headers, $body);
        $response = Unirest::post("https://directbill.9mobile.com.ng/sync", $headers, $body);
        $result = json_decode($response->raw_body);

        // Transaction responses
        $this->transactionCode = $result->tranxId;
        $this->extTransactionId = $result->extTxnId;
        $this->contextMsg = $result->contextMsg;
        $this->transactionid = $result->code;
        $this->description = $result->description; // Store transaction ID
    }

    public function activateAirtel($mobile)
    {
        #$curl = curl_init();
        # the product id as configured on Airtel SE for this service
        $product_id = "7133";
        $headers = array(
            'content-type' => 'application/json'
        );

        $body = Body::json(array(
            "channel" => "USSD",
            "msisdn" => '234' . substr($mobile, -10),
            "input" => "1",
            "product_id" => $product_id
        ));

        $response = Unirest::post("http://notification.novajii.com/web/airtel/se/activate", $headers, $body);
        $result = json_decode($response->raw_body);
        return $result;
    }

    public function trimMessage($message)
    {
        $strs = $message;
        $word = strlen($strs);
        $cnt = 450;
        $pos = 0;

        if ($word < $cnt) {
            $this->sendMessage($message);
        } else if ($word > $cnt) {
            $loop = round($word / $cnt);

            for ($x = 0; $x < $loop; $x++) {
                if ($x == 0) {
                    $text = substr($strs, $pos);
                    $smsStr = $this->truncateStringWords($text, $cnt);
                    $this->sendMessage($smsStr);
                } else {
                    $text = substr($strs, $pos);
                    $smsStr = $this->truncateStringWords($text, $cnt);
                    $this->sendMessage($smsStr);
                }
                $pos += 442;
            }
        }
    }

    public function truncateStringWords($str, $maxlen)
    {
        if (strlen($str) <= $maxlen) {
            return $str;
        }

        $newstr = substr($str, 0, $maxlen);
        if (substr($newstr, -1, 1) != ' ')
            $newstr = substr($newstr, 0, strrpos($newstr, " "));

        return $newstr;
    }

    public function sendMessage($params)
    {
        $sender = "371";
        if ($this->network == "etisalat") {
            file_get_contents(
                "http://sms.novajii.com/send?"
                    . "username=oracle&"
                    . "password=oracle&"
                    . "to=" . $this->mobile . "&"
                    . "from=$sender&"
                    . "content=" . urlencode($params) . "&"
                    . "dlr=yes&"
                    . "dlr-url=http://portal.novajii.com/smsgw/dlr&dlr-level=3&dlr-method=GET"
            );
        }
        if ($this->network == "airtel") {
            $this->airtelSMS($sender, $params);
        }
    }

    public function airtimeTopupSelf($var)
    {
        if (!empty($var)) {
            if ($var[1] == 0) {
                $this->removeSubscriber('234' . substr($this->getMsisdn(), -10));
                $this->sendSMSMessage("Dear customer, you have successfully unsubscribed from Traffic Info. To subscribe again, Dial *371#");
                # Terminate process
                return $this->result("end", "Dear customer, you have successfully unsubscribed from Traffic Info. To subscribe again, Dial *371#");
            } else {
                $ret = Api::cashEnvoyApi(number_format($var[1], 2), 'MTN', '0' . substr($this->getMsisdn(), -10), time());
                $res = json_decode($ret);

                if ($res->responseCode == '00') {
                    return $this->result("end", "Assotel top up. Airtime credited with NGN" . number_format($var[1], 2));
                }
            }
        }
        return $this->result("end", "Failed action. Please try again");
    }

    /**
     * Airtime top-up - Others
     * 
     * @param array $var
     * $var[2] mobile number
     * $var[3] airtime amount
     * @return type
     */
    public function airtimeTopupOthers($var)
    {
        if (!empty($var)) {
            $ret = Api::cashEnvoyApi(number_format($var[2], 2), 'MTN', '0' . substr($var[1], -10), time());
            $res = json_decode($ret);

            if ($res->responseCode == '00') {
                return $this->result("end", "Assotel top up " . '0' . substr($var[1], -10) . " credited with NGN" . number_format($var[2], 2));
            }
        }
        return $this->result("end", "Failed transaction. Please try again");
    }

    protected function removeSubscriber($mobile)
    {
        try {
            $ret = R::findOne('novaji_vas_subscriptions', 'msisdn = ?', [$mobile]);
            $res = R::findOne('requests', 'msisdn = ?', [$mobile]);
            R::trash($ret);
            R::trash($res);

            // Add subscriber to opt-out table
            $query = "INSERT INTO novaji_vas_opt_out(msisdn, service_id, network) VALUES(?,?,?)";
            R::exec($query, [$mobile, 1, 'etisalat']);
        } catch (Exception $e) {
            $e->getMessage();
        }
    }

    protected function sendSMSMessage($message)
    {
        file_get_contents(
            "http://sms.novajii.com/send?"
                . "username=oracle&"
                . "password=oracle&"
                . "to=" . '0' . substr($this->getMsisdn(), -10) . "&"
                . "from=371&"
                . "content=" . urlencode($message) . "&"
                . "dlr=yes&"
                . "dlr-url=http://portal.novajii.com/smsgw/dlr&dlr-level=3&dlr-method=GET"
        );
    }

    protected function airtelSMS($sender, $message)
    {
        $data = [
            "sender" => $sender,
            "destination" => "234" . substr($this->mobile, -10),
            "message" => $message
        ];

        $headers = array('Accept' => 'application/json');
        $body = Body::Form($data);
        $response = Unirest::post("http://notification.novajii.com/web/airtel/sms", $headers, $body);
        return $response;
    }

    public function logResult($result)
    {
        file_put_contents('/var/www/html/ussd/traffic_info_query_log_' . date("j.n.Y") . '.log', $result, FILE_APPEND);
    }

    public function getPricingNote($network)
    {
        $notes = [
            "airtel" => "Service costs N50/week",
            "etisalat" => "Service costs N10/day"
        ];
        return $notes[$network];
    }

    public function getSubscriptionNote()
    {
        $notes = [
            "airtel" => "Hello, you have successfully subscribed to Traffic Info. Dial 371# to continue. Dial *902# to opt out",
            "etisalat" => "Hello, you have successfully subscribed to Traffic Info. You have 2 requests @N10/day. Extra request cost N10.  Dial 371# to continue. SMS Stop to 371 to opt out or Text 2 to 371 to switch to one-off subscription"
        ];
        return $notes[$this->network];
    }

    public function runFor9Mobile()
    {
        if ($this->billStatus == true) {
            $this->initiateBilling($this->mobile); // Initiate billing

            if ($this->transactionid == 200) {
                $this->logBilling($this->mobile, "request"); // Log billing for request on Traffic Info
                $this->logUSSDRequest($this->inputs[1], $this->inputs[2]); // Log request
                $this->buildLocationRequest(); // No free slot, bill msisdn
                // Enable billing on msisdn
                $this->setBillingStatus($this->mobile);
            } else if ($this->transactionid == 202) {
                $this->reply = "Dear Customer, your balance is low. Kindly recharge or borrow airtime by dialing *655#"; // No free slot, no credit, terminate process
            } else {
                $this->reply = $this->description;
            }
        }
    }

    public function runForAirtel()
    {
        $this->buildLocationRequest(); // 
    }
}
