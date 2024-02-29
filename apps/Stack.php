<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Novaji\Ussd;

require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
include_once 'UssdApplication.php';
require_once 'lib/classes/rb.php';

use Unirest\Request as Request;
use Unirest\Request\Body as Body;
use Predis\Client as RedisClient;

/**
 * Description of Stack API
 *
 * @author NOVAJI
 */
class Stack
{

    private $redis;
    private $public_key;
    private $reference;
    private $account_bank;
    private $currency;
    private $payment_type;
    private $country;
    private $email;
    private $passcode;
    private $bvn;
    private $phone_number;
    private $ip;
    private $firstname;
    private $lastname;

    function __construct()
    {
        $this->secrete_key = "sk_test_15c4db313163dfde402418e7daf3939e2d423189";
        $this->redis = new RedisClient();
        $this->email = "ceo@novajii.com";
        $this->country = "NG";
        $this->payment_type = "account";
        $this->currency = "NGN";
        $this->ip = $_SERVER['SERVER_ADDR'];
        $this->reference = time();
    }

    /*
     * return an array of banks from Stack, you could store this in a file and read 
     * 1: Zenith
     * 2: Fidelity
     * 3: UBA
     */

    function list_banks($selected)
    {

        $banks =  array(
            '1' => 044,
            '2' => 232,
            '3' => 101,
            '4' => 215,
            '5' => 057,
        );
        return $banks[$selected];
    }

    /*
     * Get the bank code of the account the user selected in the ussd
     * if selection is 1:Zenith then return 057
     */

    function get_account_bank($number_entered)
    { }

    /*
     * implement paystack charge and parse the result for the further processing 
     * Collect the values from your USSD menu
     */

    function charge($amount, $account_number, $account_bank)
    {
        $result = array();
        //Set other parameters as keys in the $postdata array
        $bank_code = $this->list_banks($account_bank);
        $postdata =  array(
            "email" => $this->email,
            "amount" => $amount,
            "reference" => $this->reference,
            "bank" => array(
                "code" => $bank_code,
                "account_number" => $account_number
            ),

        );
        $url = "https://api.paystack.co/charge";

        $response = $this->call_endpoint($url, $postdata);
        if ($response != 'false') {
            $result = json_decode($response, true);
            if ($result["data"]["status"] === "send_birthday") {

                
            } else if ($result["data"]["status"] === "send_otp"){
               

            }elseif ($result["data"]["status"] === "send_otp" && strstr($result["data"]["display_text"], "bvn")) {
                # code...
                return ["action" => "Continue", "message" => "Please enter your BVN(Required by your bank."];
            }
            else if ($result["data"]["status"] === "send_otp" && strstr($result["data"]["display_text"], "phone")) {

                # code...
            } else {
                # code...
            }
        } else {
            # code...
        }


        return ["action" => "Continue", "message" => "Please validate with the OTP from hardware token,SMS or email"];
    }

    public function call_endpoint($url, $postdata)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));  //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer sk_live_0d4a1650b249168a5d66d45e143c32ab48f35c8d',
            'Content-Type: application/json',

        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);

        curl_close($ch);

        if ($request) {
            return json_decode($request, true);
        } else {
            return 'false';
        }
    }

    /*
     * validate the payment with the OTP
     */

    function validate_bvn($bvn)
    {
        
        $postdata =  array(
            'otp' => $bvn,
            "reference" => $this->reference,
        );
        $url = "https://api.paystack.co/charge/submit_otp";

        $result = $this->call_endpoint($url,$postdata);
        if ($result != 'false') {

            if ($result["data"]["status"] === "send_birthday") {

                $this->va;
            }            
            # code...
        } else {
            # code...
        }
        
        # return ussd message when done
        return ["action" => "Continue", "message" => "Transaction Successful\n$str"];
    }

    function validate($otp)
    {

        # return ussd message when done
        return ["action" => "Continue", "message" => "Transaction Successful\n$str"];
    }


    function validate_birthday($birthday)
    {
        $postdata =  array(
            'birthday' => $birthday,
            "reference" => $this->reference,
        );
        $url = "https://api.paystack.co/charge/submit_birthday";

        $result = $this->call_endpoint($url,$postdata);

        if ($result != 'false') {
            
        } else {
            # code...
        }
        
        # return ussd message when done
        return ["action" => "Continue", "message" => "Transaction Successful\n$str"];
    }
    /*
     * Stack encryption functions
     */
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
