<?php

require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
require_once 'UssdApplication.php';
require_once 'lib/classes/rb.php';
include_once 'apps/classes/easytaxpayer/SendSms.php';

use Unirest\Request as Request;
use Unirest\Request\Body as Body;
use Predis\Client as RedisClient;

/**
 * Description of Rave API
 *
 * @author NOVAJI
 */
class Rave
{

    private $redis;
    private $public_key;
    private $reference;
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

    function __construct($public_key = "FLWPUBK-b40e3c775cce919dd93e972132e6c3ef-X")
    {
        $this->public_key = $public_key;
        $this->redis = new RedisClient();
        $this->email = "ceo@novajii.com";
        $this->country = "NG";
        $this->payment_type = "account";
        $this->currency = "NGN";
        $this->ip = $_SERVER['SERVER_ADDR'];
        $this->reference = time();
    }

    /*
     * return an array of banks from rave, you could store this in a file and read 
     * 1: Zenith
     * 2: Fidelity
     * 3: UBA
     */

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

    /*
     * Get the bank code of the account the user selected in the ussd
     * if selection is 1:Zenith then return 057
     */

    function get_account_bank($number_entered)
    { }

    /*
     * implement flutterwave charge and parse the result for the further processing 
     * Collect the values from your USSD menu
     */

    function charge($transaction_ref, $msisdn, $amount, $account_number, $account_bank, $bvn = null, $passcode = null)
    {

        if (!$bvn && !$passcode) {

            $bank_id = $this->list_banks($account_bank);
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

            $SecKey = 'FLWSECK-81e765fc0850d5e376b97addda2e179b-X';
            $key = $this->getKey($SecKey);
            $dataReq = json_encode($opt_data);
            $post_enc = $this->encrypt3Des($dataReq, $key);

            $postdata = array(
                'PBFPubKey' => $this->public_key,
                'client' => $post_enc,
                'alg' => '3DES-24'
            );

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://api.ravepay.co/flwv3-pug/getpaidx/api/charge");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 200);
            curl_setopt($ch, CURLOPT_TIMEOUT, 200);


            $headers = array('Content-Type: application/json');

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $request = curl_exec($ch);

            if ($request) {
                $result = json_decode($request, true);

                if ($result['status'] === 'error') {

                    return ["action" => "End", "message" => "Transaction could not be completed bacause the information you entered is incorrect, please check and try again"];
                } else {
                    if ($result['data']['chargeResponseCode'] === '00') {
                        return ["action" => "end", "message" => "Payment Successful"];
                    } else if ($result['data']['chargeResponseCode'] === '02') {
                        $txRef = $result['data']['txRef'];
                        $orderRef = $result['data']['orderRef'];
                        $flwRef = $result['data']['flwRef'];
                        $charge_amount = $result['data']['amount'];
                        $raveRef = $result['data']['raveRef'];
                        $currency = $result['data']['currency'];
                        $customerId = $result['data']['customerId'];
                        $AccountId = $result['data']['AccountId'];
                        $narration = $result['data']['narration'];
                        $status = "pending";
                        $this->logdetails($txRef, $orderRef, $flwRef, $charge_amount, $raveRef, $currency, $customerId, $AccountId, $narration, $msisdn);
                        $this->logpendingdetails($txRef, $orderRef, $flwRef, $charge_amount, $raveRef, $currency, $customerId, $AccountId, $narration, $msisdn, $status);
                        return ["action" => "Continue", "message" => "Please validate with the OTP from SMS or Email (if this fails, dail again and enter OTP)"];
                    } else {
                        return ["action" => "End", "message" => "Transaction could not be completed bacause the information you entered is incorrect or your bank is unavailable, please check or try again"];
                    }
                }
            } else {
                if (curl_error($ch)) {
                    return ["action" => "End", "message" => "Transaction could not be completed bacause of a network error, please try again later"];
                }
            }

            curl_close($ch);
        } else if ($bvn != null) {
            $bank_id = $this->list_banks($account_bank);
            $opt_data = array(
                'PBFPubKey' => $this->public_key,
                'accountbank' => $bank_id,
                'country' => $this->country,
                'accountnumber' => $account_number,
                'amount' => $amount,
                'bvn' => $bvn,
                'email' => $this->email,
                'phonenumber' => $msisdn,
                'txRef' => $transaction_ref,
                'payment_type' => $this->payment_type,
            );

            $SecKey = 'FLWSECK-81e765fc0850d5e376b97addda2e179b-X';
            $key = $this->getKey($SecKey);
            $dataReq = json_encode($opt_data);
            $post_enc = $this->encrypt3Des($dataReq, $key);

            $postdata = array(
                'PBFPubKey' => $this->public_key,
                'client' => $post_enc,
                'alg' => '3DES-24'
            );

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://api.ravepay.co/flwv3-pug/getpaidx/api/charge");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 200);
            curl_setopt($ch, CURLOPT_TIMEOUT, 200);


            $headers = array('Content-Type: application/json');

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $request = curl_exec($ch);

            if ($request) {
                $result = json_decode($request, true);


                if ($result['status'] === 'error') {
                    return ["action" => "End", "message" => "Transaction could not be completed bacause the information you entered is incorrect, please check and try again"];
                } else {
                    if ($result['data']['chargeResponseCode'] === '00') {
                        return ["action" => "end", "message" => "Payment Successful"];
                    } else if ($result['data']['chargeResponseCode'] === '02') {
                        $txRef = $result['data']['txRef'];
                        $orderRef = $result['data']['orderRef'];
                        $flwRef = $result['data']['flwRef'];
                        $charge_amount = $result['data']['amount'];
                        $raveRef = $result['data']['raveRef'];
                        $currency = $result['data']['currency'];
                        $customerId = $result['data']['customerId'];
                        $AccountId = $result['data']['AccountId'];
                        $narration = $result['data']['narration'];
                        $status = "pending";
                        $this->logdetails($txRef, $orderRef, $flwRef, $charge_amount, $raveRef, $currency, $customerId, $AccountId, $narration, $msisdn);
                        $this->logpendingdetails($txRef, $orderRef, $flwRef, $charge_amount, $raveRef, $currency, $customerId, $AccountId, $narration, $msisdn, $status);
                        return ["action" => "Continue", "message" => "Please validate with the OTP from SMS or Email (if this fails, dail again and enter OTP)"];
                    } else {
                        return ["action" => "End", "message" => "Transaction could not be completed bacause the information you entered is incorrect, please check and try again"];
                    }
                }
            } else {
                if (curl_error($ch)) {
                    return ["action" => "End", "message" => "Transaction could not be completed bacause of a network error, please try again later"];
                }
            }

            curl_close($ch);
        } else if ($passcode != null) {

            //$bank_id = $this->list_banks($account_bank);
            $opt_data = array(
                'PBFPubKey' => $this->public_key,
                'accountbank' => '057',
                'country' => $this->country,
                'accountnumber' => $account_number,
                'amount' => $amount,
                "passcode" => $passcode,
                'email' => $this->email,
                'phonenumber' => $msisdn,
                'txRef' => $transaction_ref,
                'payment_type' => $this->payment_type,
            );

            $SecKey = 'FLWSECK-81e765fc0850d5e376b97addda2e179b-X';
            $key = $this->getKey($SecKey);
            $dataReq = json_encode($opt_data);
            $post_enc = $this->encrypt3Des($dataReq, $key);

            $postdata = array(
                'PBFPubKey' => $this->public_key,
                'client' => $post_enc,
                'alg' => '3DES-24'
            );

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://api.ravepay.co/flwv3-pug/getpaidx/api/charge");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 200);
            curl_setopt($ch, CURLOPT_TIMEOUT, 200);


            $headers = array('Content-Type: application/json');

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $request = curl_exec($ch);

            if ($request) {
                $result = json_decode($request, true);

                if ($result['status'] === 'error') {
                    return ["action" => "End", "message" => "Transaction could not be completed bacause the information you entered is incorrect, please check and try again"];
                } else {
                    if ($result['data']['chargeResponseCode'] === '00') {
                        return ["action" => "end", "message" => "Payment Successful"];
                    } else if ($result['data']['chargeResponseCode'] === '02') {

                        $txRef = $result['data']['txRef'];
                        $orderRef = $result['data']['orderRef'];
                        $flwRef = $result['data']['flwRef'];
                        $charge_amount = $result['data']['amount'];
                        $raveRef = $result['data']['raveRef'];
                        $currency = $result['data']['currency'];
                        $customerId = $result['data']['customerId'];
                        $AccountId = $result['data']['AccountId'];
                        $narration = $result['data']['narration'];
                        $status = "pending";
                        $this->logdetails($txRef, $orderRef, $flwRef, $charge_amount, $raveRef, $currency, $customerId, $AccountId, $narration, $msisdn);
                        $this->logpendingdetails($txRef, $orderRef, $flwRef, $charge_amount, $raveRef, $currency, $customerId, $AccountId, $narration, $msisdn, $status);

                        return ["action" => "Continue", "message" => "Please validate with the OTP from SMS or Email (if this fails, dail again and enter OTP)"];
                    } else {
                        return ["action" => "End", "message" => "Transaction could not be completed bacause the information you entered is incorrect, please check and try again"];
                    }
                }
            } else {
                if (curl_error($ch)) {
                    return ["action" => "End", "message" => "Transaction could not be completed because of a network error, please try again later"];
                }
            }

            curl_close($ch);
        } else {

            return ["action" => "End", "message" => "Transaction could not be completed, You did not select the right option"];
        }
    }

    public function call_paystack_ussd($msisdn, $bank_id, $account_number,$amount)
    {
        $result = array();
        $url = "https://api.paystack.co/charge";

        if ($bank_id == '033') {
            $postdata =  array(
                'email' => "ceo@novajii.com",
                'amount' => $amount,
                "bank" => array(
                    "code" => "033",
                    "account_number" => $account_number
                ),
            );
        } elseif ($bank_id == '035A') {
            $postdata =  array(
                'email' => "ceo@novajii.com",
                'amount' => $amount,
                "bank" => array(
                    "code" => "035A",
                    "account_number" => $account_number
                ),
            );
        } else {
            $postdata =  array(
                'email' => "ceo@novajii.com",
                'amount' => $amount,
                "ussd" => array(
                    "type" => "737"
                ),
                'metadata' => array(
                    'custom_fields' => array(
                        'phone' => $msisdn,
                        'merchant' => "universal"
                    ),
                )
            );
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));  //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer sk_live_a465b458b950ecaf90ff015a712b2c65cc5ecc94',
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);

        curl_close($ch);
        if ($request) {
            $result = json_decode($request, true);
            if ($result['data']['status'] == 'pay_offline') {
                $msg = $result['data']['display_text'];
                $reference = $result['data']['reference'];
                $this->sms($msg, $msisdn);
                $this->logpaystackussdpayment($reference, $msisdn);
                return ["action" => "End", "message" => $result['data']['display_text']];
            } else if ($result['data']['status'] == 'send_otp') {
                $reference = $result['data']['reference'];
                $this->logpendingdetails($reference,$reference,$reference,"500",$reference,"NGN","paystack","paystack","paystack",$msisdn,"pending");
                $this->logpaystackussdpayment($reference, $msisdn);
                return  array(
                    'status' => 'send_otp',
                    'ref' => $result['data']['reference'],
                    'text' => $result['data']['display_text']
                );
            } else if ($result['data']['status'] == 'send_birthday') {
                $reference = $result['data']['reference'];
                $this->logpaystackussdpayment($reference, $msisdn);
                return array(
                    'status' => 'send_birthday',
                    'ref' => $result['data']['reference'],
                    'text' => $result['data']['display_text']
                );
            } else if ($result['data']['display_text'] == 'Please enter your bvn') {
                $reference = $result['data']['reference'];
                $this->logpaystackussdpayment($reference, $msisdn);
                return
                    array(
                        'status' => 'send_bvn',
                        'ref' => $result['data']['reference'],
                        'text' => $result['data']['display_text']
                    );
            } else {
                return ["action" => "End", "message" => "Transaction could not be completed because of a network error, please try again later"];
            }
        }
    }

    public function stack_validate_bvn($bvn, $reference)
    {
        $result = array();
        $postdata =  array(
            'otp' => $bvn,
            "reference" => $reference,
        );
        $url = "https://api.paystack.co/charge/submit_otp";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));  //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer sk_live_a465b458b950ecaf90ff015a712b2c65cc5ecc94',
            'Content-Type: application/json',

        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);

        curl_close($ch);
        if ($request) {

            $result = json_decode($request, true);
            if ($result['data']['status'] == 'send_birthday') {
                return
                    array(
                        'status' => 'send_birthday',
                        'ref' => $result['data']['reference'],
                        'text' => $result['data']['display_text']
                    );
            } else {
                return 'error';
            }
        }
    }

    public function stack_validate_birthday($birthday, $reference, $msisdn)
    {

        $result = array();
        $postdata =  array(
            'birthday' => $birthday,
            "reference" => $reference,
        );
        $url = "https://api.paystack.co/charge/submit_birthday";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));  //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer sk_live_a465b458b950ecaf90ff015a712b2c65cc5ecc94',
            'Content-Type: application/json',

        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);

        curl_close($ch);
        if ($request) {
            $result = json_decode($request, true);
            if ($result['data']['status'] == 'send_otp') {
                $this->logpendingdetails($reference,$reference,$reference,"500",$reference,"NGN","paystack","paystack","paystack",$msisdn,"pending");
                return
                    array(
                        'status' => 'send_otp',
                        'ref' => $result['data']['reference'],
                        'text' => $result['data']['display_text']
                    );
            } else {
                return 'error';
            }
        }
    }

    public function stack_validate_otp($otp, $ref,$msisdn)
    {
        $result = array();
        $postdata =  array(
            'otp' => $otp,
            "reference" => $ref,
        );
        $url = "https://api.paystack.co/charge/submit_otp";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));  //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer sk_live_a465b458b950ecaf90ff015a712b2c65cc5ecc94',
            'Content-Type: application/json',

        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);

        curl_close($ch);

        if ($request) {
            $this->deleteTransaction($msisdn);
            $result = json_decode($request, true);
            if ($result['data']['status'] == 'success') {
                return
                    array(
                        'status' => 'success',
                        'ref' => $result['data']['reference'],
                        'text' => 'Payment Successful, your payment will be confimed via SMS'
                    );
            } else {
                return 'error';
            }
        }
    }

    /*
     * validate the payment with the OTP
     */

    function logdetails($txRef, $orderRef, $flwRef, $amount, $raveRef, $currency, $customerId, $AccountId, $narration, $phone_number)
    {
        $query = "INSERT INTO rave_pre_payments("
            .  "txRef,orderRef,flwRef,amount,raveRef,currency,customerId,accountId,narration,msisdn)"
            .  "VALUES(?,?,?,?,?,?,?,?,?,?)";


        UssdApplication::connect();
        R::exec($query, [$txRef, $orderRef, $flwRef, $amount, $raveRef, $currency, $customerId, $AccountId, $narration, $phone_number]);
    }

    function logpendingdetails($txRef, $orderRef, $flwRef, $amount, $raveRef, $currency, $customerId, $AccountId, $narration, $phone_number, $status)
    {
        $query = "INSERT INTO rave_pending_payments("
            .  "txRef,orderRef,flwRef,amount,raveRef,currency,customerId,accountId,narration,msisdn,status)"
            .  "VALUES(?,?,?,?,?,?,?,?,?,?,?)";


        UssdApplication::connect();
        R::exec($query, [$txRef, $orderRef, $flwRef, $amount, $raveRef, $currency, $customerId, $AccountId, $narration, $phone_number, $status]);
    }

    function logpaystackussdpayment($reference, $msisdn)
    {
        $query = "INSERT INTO novaji_paystack_ussd_payment("
            .  "reference,msisdn)"
            .  "VALUES(?,?)";


        UssdApplication::connect();
        R::exec($query, [$reference, $msisdn]);
    }

    function deleteTransaction($msisdn)
    {
        $ret = R::findOne("rave_pending_payments", "status = ? and msisdn = ?", ["pending", $msisdn]);
        if ($ret) {
            R::trash($ret);
        } else {
            # code...
        }
    }

    function validate($otp, $msisdn)
    {
        $flash = R::getRow('SELECT * FROM rave_pre_payments WHERE msisdn = ? order by entry_date DESC LIMIT 1', [$msisdn]);
        if ($flash) {
            $reference = $flash['flwRef'];
            $payment_ref = $flash['txRef'];
            $company = "Universal Insurance PLC";
            $amount = $flash['amount'];
            $opt_data = array(
                'PBFPubKey' => $this->public_key,
                'transactionreference' => $reference,
                'otp' => $otp,
            );

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://api.ravepay.co/flwv3-pug/getpaidx/api/validate");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opt_data)); //Post Fields
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 200);
            curl_setopt($ch, CURLOPT_TIMEOUT, 200);


            $headers = array('Content-Type: application/json');

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $request = curl_exec($ch);
            if ($request) {
                $this->deleteTransaction($msisdn);
                $result = json_decode($request, true);
                if ($result['status'] === 'error') {
                    return ["action" => "End", "message" => "Your last payment has expired after 3 mins. Please initiate a new payment"];
                } else {
                    if ($result['data']['chargeResponseCode'] === '00') {
                        $message = "Your payment of " . $amount . " to " . $company . " was successful.\n Your transaction reference is: " . $payment_ref;
                        $this->sms($message, $msisdn);
                        return ["action" => "end", "message" => "Payment Successful"];
                    } else if ($result['data']['chargeResponseCode'] === '02') {
                        return ["action" => "End", "message" => "Transaction failed because of an invalid OTP"];
                    } else {
                        return ["action" => "End", "message" => "Your transaction was declined by your bank. Please try again later"];
                    }
                }
            } else {
                if (curl_error($ch)) {
                    return ["action" => "End", "message" => "Transaction could not be completed due to network error, please try again later"];
                }
            }

            curl_close($ch);
        } else {
            return ["action" => "End", "message" => "Transaction could not be completed bacause you have not initiaited a payment."];
        }
    }

    public function sms($msg, $msisdn)
    {
        $usr_msg = urlencode($msg);
        $message = file_get_contents("http://notification.novajii.com/sms/api/send?username=david.f@novajii.com&password=Olubukola123_&sender=371&destination=" . "{$msisdn}&message={$usr_msg}");
    }

    /*
     * Rave encryption functions
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