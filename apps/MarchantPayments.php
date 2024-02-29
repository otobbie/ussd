<?php

require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
require_once 'UssdApplication.php';
require_once 'lib/classes/rb.php';
include_once 'apps/classes/easytaxpayer/SendSms.php';

use Unirest\Request as Request;
use Unirest\Request\Body as Body;
use Predis\Client as RedisClient;

class MarchantPayments
{


    private $redis;
    private $public_key;
    private $reference;
    private $currency;
    private $payment_type;
    private $country;
    private $email;
    private $rave_url;
    private $passcode;
    private $bvn;
    private $phone_number;
    private $ip;
    private $firstname;
    private $lastname;

    #Responses
    private $incorrect_response;
    private $invalid_otp;
    private $network_error;
    private $otp;
    private $bank_unavailable;
    private $success;

    function __construct($public_key = "FLWPUBK-b40e3c775cce919dd93e972132e6c3ef-X", $sec_key = 'FLWSECK-81e765fc0850d5e376b97addda2e179b-X')
    {
        $this->public_key = $public_key;
        $this->sec_key = $sec_key;
        $this->redis = new RedisClient();
        $this->email = "ceo@novajii.com";
        $this->country = "NG";
        $this->payment_type = "account";
        $this->currency = "NGN";
        $this->ip = $_SERVER['SERVER_ADDR'];
        $this->rave_url = "https://api.ravepay.co/flwv3-pug/getpaidx/api/charge";
        $this->reference = time();

        #Responses
        $this->incorrect_response = ["action" => "End", "message" => "Transaction could not be completed bacause the information you entered is incorrect, please check and try again"];
        $this->invalid_otp = ["action" => "End", "message" => "Transaction failed because of an invalid OTP"];
        $this->network_error = ["action" => "End", "message" => "Transaction could not be completed due to network error, please try again later"];
        $this->otp = ["action" => "Continue", "message" => "Please validate with the OTP from SMS or Email (if this fails, dail again and enter OTP)"];
        $this->bank_unavailable = ["action" => "End", "message" => "Sorry, your transaction was seclined by your bank or your bank is unavailable"];
        $this->success = ["action" => "end", "message" => "Payment Successful"];
    }

    function list_banks($selected)
    {
        $banks =  array(
            '1' => "044",
            '2' => "232",
            '3' => "101",
            '4' => "215",
            '5' => "057",
        );
        return $banks[$selected];
    }

    function send_request($account_bank, $account_number, $amount, $msisdn, $transaction_ref)
    {

        $bank_id = $account_bank;
        $opt_data = array(
            'PBFPubKey' => $this->public_key,
            'accountbank' => $bank_id,
            'country' => $this->country,
            'accountnumber' => $account_number,
            'amount' => $amount,
            'email' => $this->email,
            'phonenumber' => $msisdn,
            'txRef' => $transaction_ref,
            'payment_type' => $this->payment_type,
        );

        
        $key = $this->getKey($this->sec_key);
        $dataReq = json_encode($opt_data);
        $post_enc = $this->encrypt3Des($dataReq, $key);

        $postdata = array(
            'PBFPubKey' => $this->public_key,
            'client' => $post_enc,
            'alg' => '3DES-24'
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->rave_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 200);
        curl_setopt($ch, CURLOPT_TIMEOUT, 200);


        $headers = array('Content-Type: application/json');

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);
        $status = $request['status'];
        $charge_code = $request['data']['chargeResponseCode'];
        $data = $request['data'];

    }

    function getKey($seckey)
    {
        $hashedkey = md5($seckey);
        $hashedkeylast12 = substr($hashedkey, -12);

        $seckeyadjusted = str_replace("FLWSECK-", "", $seckey);
        $seckeyadjustedfirst12 = substr($seckeyadjusted, 0, 12);

        $encryptionkey = $seckeyadjustedfirst12 . $hashedkeylast12;
        return $encryptionkey;
    }

    function encrypt3Des($data, $key)
    {
        $encData = openssl_encrypt($data, 'DES-EDE3', $key, OPENSSL_RAW_DATA);
        return base64_encode($encData);
    }
}
