<?php
include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
use Symfony\Component\Yaml\Yaml;

class Bet9ja extends UssdApplication{

    public function getResponse($body)
    {
        $this->initValues($body);
        $last_inputed = $this->lastInputed;

        switch ($this->currentStep) {
            case 1:
                return $this->result("continue", $this->render("identity"));
            case 2: 
                 $this->set_value('identity', $this->lastInputed);
                 return $this->getBet9jaApi();
            case 3:
                 $amount = floatval(preg_replace("/[^-0-9\.]/", "", $this->lastInputed));
                 if ($amount <= 0) {
                     return $this->cancelLastAction("You entered an invalid amount. \n" . $this->render("amount"));
                 } else {
                    $this->set_value("amount", $this->lastInputed);
                    return $this->result("continue", $this->render("banks"));
                 }
            case 4: 
                $this->set_value("bank", $this->lastInputed);
                if ($this->lastInputed > 12) {
                    return $this->cancelLastAction("You entered a wrong option. \n" . $this->render("banks"));
                } else {
                    $bankId = $this->get_value("bank");
                    return $this->getBankCodes(strval($bankId));
                 }
            default:
                 return $this->cancelLastAction($this->render("identity"));
        }
    }
    public function getBet9jaApi(){
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $idNum = $this->get_value("identity");
        $username = 'tester99';
        $password = 'T3st3r99';
        $billerSlug = "BET9JA";
        $customerId = $idNum;
        $productName = "BET9JA_WALLET_TOP_UP";
        $body = json_encode([
            "billerSlug" => $billerSlug,
            "customerId" => $customerId,
            "productName" => $productName
        ]);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://204.8.207.124:8080/coralpay-vas/api/transactions/customer-lookup",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic '. base64_encode("$username:$password")
            )
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return $err;
        } else {
            $result = json_decode($response);
             if($result->status === 'success'){
                $status = $result->status;
                $message = $result->message;
                $responseCode = $result->responseCode;
                $billerName = $result->responseData->billerName;
                $customerName = $result->responseData->customer->customerName;
                $accountNumber = $result->responseData->customer->accountNumber;
                $phoneNumber = $result->responseData->customer->phoneNumber;
                $emailAddress = $result->responseData->customer->emailAddress;
                $this->LogCustomerDetails($status, $msisdn, $message, $responseCode, $billerName, $customerName,  $accountNumber, $phoneNumber, $emailAddress);
                return $this->result("continue", "Bet9ja Reward for Passion:\nName: ". $customerName ."\nEnter amount:");
             }elseif($result->status === 'failed'){
                $status = $result->status;
                $message = $result->message;
                $billerName = $result->responseData->billerName;
                return $this->result("continue", "Bet9ja Reward for Passion:\nInfo: ". $message ."\nPlease try again");
             }else{
                 return $this->result("end", "Service Temporary unavailable\nPlease try again later");
             }
        }
    }
    public function getBankCodes($bankId){
        
    $bank_array = array(
                   'accessbank' => '1',
                   'ecobank' => '2',
                   'firstbank' => '3',
                   'fidelitybank' => '4', 
                   'gtb' => '5', 
                   'keystonebank' => '6', 
                   'stanbicbank' => '7', 
                   'sterlingbank' => '8', 
                   'uba' => '9', 
                   'unitybank' => '10', 
                   'wemabank' => '11', 
                   'zenithbank'=> '12'
                );

    if (array_search($bankId, $bank_array)) {

            $band_value = array_search($bankId, $bank_array);
            $sub_merchant = 'Bet9ja_test';
            $channel = 'ussd';
            $amount = $this->get_value("amount");
            $apiKey = urlencode('3af1f9-12c920-46b5ae-3a5c67-4d55d4');
            $merchant_name = 'Bet9ja';
            

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://novajii.com/web/ussd-payment/api/generateRef",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "amount=" . $amount . "&apikey=" . $apiKey . "&channel=" . $channel . "&bankId=" . $band_value . "&product=" . $sub_merchant . "",
                CURLOPT_HTTPHEADER => array(
                    "cache-control: no-cache",
                    "content-type: application/x-www-form-urlencoded",
                ),
            ));
    
            $request = curl_exec($curl);
            $err = curl_error($curl);
    
            curl_close($curl);
    
            if ($err) {
               return $err;
            } else if($request){
                $response = json_decode($request);
                    $bank_message = $response->ResponseMessage;
                    $bank_reference = $response->Reference;
                    $bank_amount = $response->Amount;
                    $bank_transaction_id = $response->TransactionID;
                    $bank_trace_id = $response->TraceID;
                    $description = $response->Description;
                    $this->logCoralPay($bank_message,$bank_reference,$bank_amount,$bank_transaction_id,$bank_trace_id,$description);
                    $msg = "Merchant Name: $merchant_name\nAmount: N$bank_amount\n$description.\nAn SMS with string to dial has also been sent to you";
                    $sms = "Payment Details \nMerchant Name: $merchant_name\nAmount: N$bank_amount\n$description.\nThanks for using this Service.";
                    $this->sendPaymentSms($sms);
                    return $this->result("end", $msg);
            }else{
              return $this->result("end", "Invalid Transaction try again");
              }  
                 
        } 
}

    public function logCoralPay($bank_message,$bank_reference,$bank_amount,$bank_transaction_id,$bank_trace_id,$description){
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $idNum = $this->get_value("identity");
        $query = "INSERT INTO betniaja_pre_payment_logs("
            . "message, customer_id, msisdn, reference, amount, transcation_id, trace_id, description) "
            . "VALUES (?,?,?,?,?,?,?,?)";
          try {
            R::exec($query, [$bank_message, $idNum, $msisdn, $bank_reference, $bank_amount, $bank_transaction_id, $bank_trace_id, $description]);
        } catch (Exception $ex) {
             return $ex->getMessage();
         }
    }
    public function LogCustomerDetails($status, $msisdn, $message, $responseCode, $billerName, $customerName,  $accountNumber, $phoneNumber, $emailAddress){

        $query = "INSERT INTO betniaja_api_logs("
            . "status, msisdn, message, response_code, biller_name, customer_name, account_number, phone_number, email_address)"
            . "VALUES (?,?,?,?,?,?,?,?,?)";
        //   try {
            R::exec($query, [$status, $msisdn, $message, $responseCode, $billerName, $customerName,  $accountNumber, $phoneNumber, $emailAddress]);
        // } catch (Exception $ex) {
        //      return $ex->getMessage();
        //  }
    }


    public function sendPaymentSms($msg) {
        $msisdn = $this->posted["msisdn"];
        $message = urlencode($msg);
            file_get_contents("https://novajii.com/web/bulksms/all/send?network=etisalat&msg=$message&src=371&msisdn=$msisdn");
            file_get_contents("https://novajii.com/web/bulksms/all/send?network=airtel&msg=$message&src=371&msisdn=$msisdn");
            file_get_contents("https://novajii.com/web/bulksms/all/send?network=mtn&msg=$message&src=371&msisdn=$msisdn");
    }
    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("apps/config/bet9ja/config.yml"));
        return $arr['pages'][$key];
    }

}