<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Pcash.php";
use Symfony\Component\Yaml\Yaml;

class PTransferFunds extends Pcash{


    public function Transfer($body){
    
        $this->initValues($body);
        # code...
        switch ($this->currentStep) {
            case 2:
                return $this->result("continue", $this->render("beneficiary_account"));
            case 3: 
                $this->set_value("acc_num", $this->lastInputed);
                $beneficiary = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if (strlen($beneficiary) <> 10) { 
                    return $this->cancelLastAction("You entered an invalid account number. \n" . $this->render("beneficiary_account"));
                }
                return $this->result("continue", $this->render("principal"));
            case 4: 
                $this->set_value("amount", $this->lastInputed);
                return $this->result("continue", $this->render("banks"));
            case 5:
                if($this->lastInputed == 0){
                    return $this->cancelLastAction("You entered an invalid account number. \n" . $this->render("banks"));
                }
                $this->set_value("banks", $this->lastInputed);
                return $this->SelectBank();
            case 6: 
                $this->set_value("bank_code", $this->lastInputed);
                $institutionCode = $this->getInstitutionCode($this->lastInputed);
                $this->set_value("institutionCode", $institutionCode);
                return $this->QueryName($institutionCode);
            case 7:
                $this->set_value("pin", $this->lastInputed);
                $bvn = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if (strlen($bvn) <> 4) { 
                    return $this->cancelLastAction("You entered an invalid pin. \n" . $this->render("pin"));
                }else{
                    return $this->getToken($body);
                }

            default:
                return $this->cancelLastAction($this->render("beneficiary_account"));
        }
    }


    public function SelectBank()
    {

        $banks = $this->get_value("banks");
        if($banks == 1){
            return $this->result("continue", $this->render("pbank_one"));
        }elseif($banks == 2){
            return $this->result("continue", $this->render("pbank_two"));
        }elseif($banks == 3){
            return $this->result("continue", $this->render("pbank_three"));
        }elseif($banks == 4){
            return $this->result("continue", $this->render("pbank_four"));
        }else{
            return $this->cancelLastAction("You entered an invalid option.\n" . $this->render("banks"));
        }
    }

    public function getInstitutionCode($body)
    {
        $options = $this->get_value("bank_code");
        $banks = $this->get_value("banks");
        if($banks == 1){
            return $this->Bank_One($options);
        }elseif($banks == 2){
            return  $this->Bank_Two($options);
        }elseif($banks == 3){
            return  $this->Bank_Three($options);
        }elseif($banks == 4){
            return  $this->Bank_Four($options);
        }else{
            return $this->cancelLastAction("You entered an invalid option.\n" . $this->render("banks"));
        }
    }

    
    public function Bank_One($option) { 
        $bank_codes =
        array(
            "044" => '1',
            "023" => '2',
            "063" => '3',
            "050" => '4',
            "084" => '5',
            "070" => '6',
            "011" => '7',
            "214" => '8',
        );
        if (array_search($option, $bank_codes)) {
            return array_search($option, $bank_codes);
        } else {
            return 'false';
        }
    }

    public function Bank_Two($option){
        $bank_codes =
        array(
            "058" => '1',
            "030" => '2',
            "301" => '3',
            "082" => '4',
            "014" => '5',
            "526" => '6',
            "010" => '7',
        );
        if (array_search($option, $bank_codes)) {
            return array_search($option, $bank_codes);
        } else {
            return 'false';
        }
    }

    public function Bank_Three($option){
        $bank_codes =
        array(
            "076" => '1',
            "221" => '2',
            "068" => '3',
            "232" => '4',
            "100" => '5',
            "032" => '6',
            "033" => '7',
            "215" => '8',

        );
        if (array_search($option, $bank_codes)) {
            return array_search($option, $bank_codes);
        } else {
            return 'false';
        }
    }

    public function Bank_Four($option){
        $bank_codes =
        array(
            "035" => '1',
            "057" => '2',   
        );
        if (array_search($option, $bank_codes)) {
            return array_search($option, $bank_codes);
        } else {
            return 'false';
        }
    }


    public function QueryName($institutionCode)
    {
        $acc_num = $this->get_value("acc_num");
        $amount = $this->get_value("amount");
        $amount_formated = number_format($amount, 2);
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/resolve-account?account_number=$acc_num&bank_code=$institutionCode",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return 'an error occured\n please try again';
            }elseif($response) {
                $request = json_decode($response);
                    $account_name = $request->account_name;
                    return $this->result("continue","Transfer of NGN$amount_formated To $acc_num\n$account_name\nEnter Your 4-digit PIN to confirm");
            }else{
                $request = json_decode($response);
                $description = $request->description;
                return $this->cancelLastAction("$description\n" . $this->SelectBank());
            } 
    }

    public function getToken($body){
        $headers = [];
        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $mno = $this->posted["src"];

        $postdata = array(
            'uid' => $msisdn,
            "pin" => $pin,
            'device' => 'ussd',
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
            } elseif($request->description == 'Successful') {
               $token =  $heads->token['0'];
                 return $this->QueryTransfer($body, $token);
            }else{
                return $this->cancelLastAction("You entered an invalid pin. \n" . $this->render("pin"));
            }
    } 


    public function QueryTransfer($body,$token)
    {
        $acc_num = $this->get_value("acc_num");
        $amount = $this->get_value("amount");
        $pin = $this->get_value("pin");
        $institutionCode = $this->get_value("institutionCode");

        $curl = curl_init();
        
        curl_setopt_array($curl, array(
        CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/transfers",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "{\"account_number\":\"$acc_num\",\"bank_code\":\"$institutionCode\",\"amount\":\"$amount\",\"reason\":\"Cash out loan\",\"pin\":\"$pin\"}",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "token: $token"
        ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
            if($err){
            }elseif($response){
                $request = json_decode($response);
                if($request->result){
                    return $this->result("End", "pCash Nano Lending:\nTransaction Successful");
                }else{
                    $description = $request->description;
                    return $this->result("End", "pCash Nano Lending:\n$description");
                }
            }else{
                 return $this->result("continue", "Service temporarily unavailable\nPlease try again later");
            }
    }
    

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }


}

