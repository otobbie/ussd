<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Pcash.php";
use Symfony\Component\Yaml\Yaml;

class SelfServicePcash extends Pcash{


    public function Service($body){
    
        $this->initValues($body);
        # code...
        switch ($this->currentStep) {
            case 2:
                return $this->result("continue", $this->render("self_service"));
            case 3:
                if (!in_array($this->lastInputed, ['1', '2', '3', '4'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('self_service'));
                }
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("service_action", "eligibility");
                        return $this->self_menu($body);
                    case 2:
                        $this->set_value("service_action", "reset");
                        return $this->self_menu($body);
                    case 3: 
                        $this->set_value("service_action", "limit");
                        return $this->self_menu($body);
                    case 4: 
                        $this->set_value("service_action", "forgot");
                        return $this->self_menu($body);
                    default:
                        return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('self_service'));
                }
                default:
                      return $this->self_menu($body);
        }
    }




    public function self_menu($body)
    {
        $mode = $this->get_value("service_action");
        if($mode == "eligibility"){
            return $this->Eligibility($body);
        }elseif($mode == "reset"){
            return $this->Reset($body);
        }elseif($mode == "limit"){
            return $this->Credit($body);
        }elseif($mode == "forgot"){
            return $this->Forgot($body);
        }
    }

    public function Eligibility($body)
    {
        switch ($this->currentStep) {
            case 3:
                return $this->result("continue", $this->render("pin"));
            case 4:
                $this->set_value("pin", $this->lastInputed);
                $token = $this->getToken($body);
                if($token){
                    return $this->EligibilityStatus($body, $token);
                }else{
                return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
            }
        }
    }
    public function Credit($body)
    {
        switch ($this->currentStep) {
            case 3:
                return $this->result("continue", $this->render("pin"));
            case 4:
                $this->set_value("pin", $this->lastInputed);
                $token = $this->getToken($body);
                if($token){
                    return $this->CreditLimit($body, $token);
                }else{
                return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
            }
        }
    }

        public function Reset($body)
        {

            switch ($this->currentStep) {
                case 3:
                    return $this->result("continue", $this->render("oldpin"));
                case 4:
                    $pin = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                    if (strlen($pin) <> 4) { 
                        return $this->cancelLastAction("You entered an invalid pin. \n" . $this->render("oldpin"));
                    } 
                    $this->set_value("pin", $this->lastInputed);
                    $token = $this->getToken($body);
                    $this->set_value("token", $token);
                    if($token){
                        return $this->result("continue", $this->render("new_pin")); 
                    }else{
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("oldpin"));
                    }      
                case 5:
                    $pin = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                    if (strlen($pin) <> 4) { 
                        return $this->cancelLastAction("You entered an invalid pin. \n" . $this->render("new_pin"));
                    }
                    $this->set_value("new_pin", $this->lastInputed);
                    return $this->ResetPin($body);
         }
                    
    }


    public function Forgot($body)
    {
        switch ($this->currentStep) {
            case 3:
                return $this->result("End", $this->render("p_forgot"));
      }
    }

    public function getToken($body){
        $headers = [];
        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);

        $postdata = array(
            'uid' => $msisdn,
            "pin" => $pin,
            'device' => 'ussd',
            "mno" => $msisdn,
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
            }else{
                $token =  $heads->token['0'];
                return  $token;
            }

    } 

    public function ResetPin($body)
    {
        $pin = $this->get_value("new_pin");
        $token = $this->get_value("token");
        $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/pin-no-otp",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS =>"{\"pin\" :\"$pin\"}",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "token: $token"
            ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return $this->result("End", "an error occured\n please try again");
            }elseif($response) {
                $request = json_decode($response);
                    if($request->description == "Successful"){
                    $message = $request->message;
                    return $this->result("End", "pCash Nano Lending:\n$message");
                    }else{
                        return  $this->result("continue", "Service temporarily unavailable\nPlease try again later");
                    }
                    
            }else{
                return  $this->result("continue", "Service temporarily unavailable\nPlease try again later");
            }
    }


    public function EligibilityStatus($body, $token)
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
                return ("an error occured\n please try again");
            }elseif($response) {
                $request = json_decode($response);
                $firstname = $request->items[0]->firstname;
                $lastname = $request->items[0]->lastname;
                $status = $request->items[0]->status;
                $credit_rating = $request->items[0]->credit_rating;
                $new_status = ucfirst($status);
                return $this->result("End", "pCash Nano Lending \nnEligibility Status:\nCustomer Name: $firstname $lastname \nStatus: $new_status\nCurrent Credit Rating: $credit_rating%" );
            }else{
                return  $this->result("continue", "Service temporarily unavailable\nPlease try again later");
                
            }
    }

    public function CreditLimit($body, $token)
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
                $firstname = $request->items[0]->firstname;
                $lastname = $request->items[0]->lastname;
                $current_credit_limit = $request->items[0]->current_credit_limit;
                $amount = number_format($current_credit_limit,2);
                return $this->result("End", "pCash Credit Limit:\nCustomer Name:  $firstname $lastname \nCurrent Credit Limit: NGN$amount");
            }else{
                 return  $this->result("continue", "Service temporarily unavailable\nPlease try again later");
            }
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }
}