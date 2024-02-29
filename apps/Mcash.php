<?php

require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
include_once 'UssdApplication.php';
require_once 'lib/classes/rb.php';

use Unirest\Request as Request;
use Unirest\Request\Body as Body;
use Predis\Client as RedisClient;

/**
 * Description of Mcash
 *
 * @author jo
 */
class Mcash {
    # redis t persist data

    private $redis;
    private $client_id;
    private $secret;
    private $merchant_code;
    private $banks;
    private $endpoint;
    private $active_msisdn;

    function __construct($client_id="0xACEVxi", $secret="REDuBrNXq86OKt4AkwSp7xC5", $merchant_code="77000701") {

        $this->redis = new RedisClient();
        $this->client_id = $client_id;
        $this->secret = $secret;
        $this->merchant_code = $merchant_code;
        $this->endpoint = "http://host.qrios.pw/v1/";
        $this->connect();
        $this->active_msisdn = "2347066192100";
    }
    
    public function set_merchant_code($merchant_code){
        $this->merchant_code = $merchant_code;
    }

    public function prepayment($msisdn, $amount = 0) {
        #$msisdn = $this->t;# use my mth with bvn to test
        $banks_id = "$msisdn:banks";
        $amount_key = "$msisdn:amount";
        $this->redis->set($amount_key, $amount);
        $this->redis->expire($amount_key, 60);
        if ($this->redis->exists($banks_id)) {
            return $this->parse_banks(json_decode($this->redis->get($banks_id)));
        } else {
            $customerMsisdn = "0" . substr($msisdn, -10);
            $headers = ['Content-Type' => 'application/json',
                'X-Client-Id' => $this->client_id,
                'X-Client-Secret' => $this->secret];
            $endpoint = $this->endpoint . "merchant/prepayment/banks";
            $data = ["customerMsisdn" => $customerMsisdn, "merchantCode" => $this->merchant_code,
                "amount" => $amount];
            $body = Body::json($data);
            #print($body);
            #print(PHP_EOL);
            Request::verifyPeer(false); // Disables SSL cert validation
            $response = Request::post($endpoint, $headers, $body);
            if ($response && ($response->code && $response->code == 200)) {
                # store banks if there are
                if (count($response->body) > 0) {
                    $raw = $response->raw_body;
                    $this->redis->set($banks_id, $raw);
                    $this->redis->expire($banks_id, 60);
                    # now parse the banks and lets see
                    return $this->parse_banks($response->body);
                }
            }
        }
        return ["action" => "End", "message" => "No bank is linked to this mobile number: 0".substr($msisdn,-10)];
    }

    private function parse_banks($list) {
        if (count($list) >= 1) {
            for ($i = 0; $i < count($list); $i++) {
                $pos = $i + 1;
                $banks[] = "$pos: " . $list[$i]->bankDescription;
            }
            $str = implode("\n", $banks);
            return ["action" => "Continue", "message" => "Select bank\n$str"];
        }
    }

    public function pay_with_bank($msisdn, $bankId, $notes = null) {
        #$banks_id = "$msisdn:banks";
        #$bankId = $this->get_bank_id($msisdn, $selected_option);
        #$amount = $this->get_amount($msisdn);
        $amount = "50";
        $transId = time();
        $customerMsisdn = "0" . substr($msisdn, -10);
        $headers = ['Content-Type' => 'application/json',
            'X-Client-Id' => $this->client_id,
            'X-Client-Secret' => $this->secret];
        $endpoint = "http://notification.novajii.com/web/mcash/merchant/paymentwithbank";
        $data = ["customerMsisdn" => $customerMsisdn,
            "amount" => $amount,
            "operationId" => "$transId",
            "notes" => $notes,
            "merchantCode" => $this->merchant_code,
            "bankId" => $bankId];
        $post = Body::json($data);
        #print($body);
        #print(PHP_EOL);
        Request::verifyPeer(false); // Disables SSL cert validation
        $response = Request::post($endpoint, $headers, $post);
        try {
            $json_resp = json_decode($response->body);
        } catch (Exception $e) {
            
        }
        #return ["action" => "End", "message" => $json_resp->recoveryShortcode];

        if ($json_resp && $json_resp->recoveryShortcode) {
            # save to db at this point awaiting callback
            #$respj = $response->body;
            #$str = str_replace("*","",$response_shortcode["recoveryShortcode"]);
            #$code = substr($str,3,5);
            $msg = $this->payment_instuction($json_resp->recoveryShortcode);
            #$msg = "{$response_shortcode["recoveryShortcode"]}";
            try {
                $this->send_sms_message("371", $msisdn, $msg);
            } catch (Exception $e) {
                
            }
            return ["action" => "End", "message" => $msg];
        }
        return ["action" => "End", "message" => $this->error_message()];
    }

    public function in_string($match, $str) {
        return preg_match("/$match/", $str);
    }

    public function get_amount($msisdn) {
        $key = "$msisdn:amount";
        if ($this->redis->exists($key)) {
            return $this->redis->get($key);
        }
    }

    public function get_bank_id($msisdn, $selected_option) {
        $banks_id = "$msisdn:banks";
        $key = ((int) $selected_option) - 1;
        if ($this->redis->exists($banks_id)) {
            $list = json_decode($this->redis->get($banks_id));
            $selected = $list[$key];
            return $selected->bankId;
        }
    }

    private function connect() {
        #UssdApplication::connect();
        $isConnected = R::testConnection();
        if (!$isConnected) {
            $host = "54.36.101.235";
            $username = "novaji_introserve";
            $password = "Amore123_";
            return R::setup("mysql:host=$host;dbname=novaji_introserve", $username, $password);
        }
    }

    public function send_sms_message($sender, $receiver, $message) {
        #$sender = "371";
        if ($this->network == "etisalat") {
            file_get_contents("http://sms.novajii.com/send?"
                    . "username=oracle&"
                    . "password=oracle&"
                    . "to=" . $receiver . "&"
                    . "from=$sender&"
                    . "content=" . urlencode($message) . "&"
                    . "dlr=yes&"
                    . "dlr-url=http://portal.novajii.com/smsgw/dlr&dlr-level=3&dlr-method=GET"
            );
        }
        if ($this->network == "airtel") {
            $this->send_airtel_message($sender, $message);
        }
    }

    public function send_airtel_message($sender, $message) {
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

    public function payment_instuction($message) {
        return "A USSD pop-up will be sent shortly or dial $message to authorize your payment";
    }

    public function error_message() {
        return "Payment process unsuccessful. Please try again";
    }

}
