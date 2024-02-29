<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Pcash.php";
use Symfony\Component\Yaml\Yaml;

class AccountBalance extends Pcash{


    public function Balance($body){
    
        $this->initValues($body);
        # code...
        switch ($this->currentStep) {
            case 2:
                return $this->result("continue", $this->render("pin"));
            case 3:
                $this->set_value("pin", $this->lastInputed);
                $token = $this->getToken($body);
                if($token){
                    return $this->checkBalance($body, $token);
                }else{
                return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
            }
        }
    }


    public function getToken($body){
        $headers = [];
        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $operator = $this->posted["src"];
        if ($operator == "airtel" || $operator == "Airtel") {
            $mno = 'airtel';
        }elseif($operator == "mtn" || $operator == "MTN"){
            $mno = 'mtn';
        }elseif($operator == "9mobile" || $operator == "Etisalat"){
            $mno = '9mobile ';
        }
        $postdata = array(
            "uid" => $msisdn,
            "pin" => $pin,
            "device" => "ussd",
            "mno" => $mno,
        );             
        $url = "http://10.0.0.140:8080/ords/pcash/api/auth";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $headers_head = [
                'Content-Type: application/json',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_head);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            function($curl, $header) use (&$headers)
            {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                return $len;
                $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                return $len;
            }
            );
            $data = curl_exec($ch);
            $request = json_decode($data);
            $head = json_encode($headers);
            $heads = json_decode($head);
            $err = curl_error($ch);
            if ($err) {
                return $err;
            } else {
               $token =  $heads->token['0'];
               return  $token;
            }
    } 

    function checkBalance($body, $token)
    {
        $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/customer",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "token: $token"
            ),
            ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return 'an error occured\n please try again';
        }elseif($response) {
            $request = json_decode($response);
            $account_balance = $request->items[0]->account_balance;
            $amount = number_format($account_balance,2);
            return $this->result("end", "pCash Nano Lending Balance:\nAccount Balance is: NGN$amount");
        }else{
            return $this->result("end", "Service temporarily unavailable\n Please try again");
        }
       
    }

    

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }


}
