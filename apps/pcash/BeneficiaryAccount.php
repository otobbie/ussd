<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Pcash.php";
use Symfony\Component\Yaml\Yaml;

class BeneficiaryAccount extends Pcash{


    public function Beneficiary($body){
    
        $this->initValues($body);
        # code...
        switch ($this->currentStep) {
            case 2:
                return $this->result('continue', $this->render('beneficiary_account'));
            case 3: 
                $this->set_value("option", $this->lastInputed);
                $beneficiary = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if (strlen($beneficiary) <> 10) { 
                    return $this->cancelLastAction("You entered an invalid account number. \n" . $this->render('beneficiary_account'));
                }else{
                    return $this->result('continue', $this->render('banks'));
                }
            case 4:
                $this->set_value("bank", $this->lastInputed);
                return $this->result("continue", $this->render('pin'));
            case 5:
                $this->set_value("pin", $this->lastInputed);
                $bvn = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if (strlen($bvn) <> 4) { 
                    return $this->cancelLastAction("You entered an invalid pin. \n" . $this->render('pin'));
                }else{
                    
                    return $this->getToken($body);
                }

            default:
                return $this->cancelLastAction($this->render("beneficiary_account"));
        }
    }


    public function getInstitutionCode($bankId)
    {   
        $bank_codes =
        array(
            "044" => '1',
            "050" => '2',
            "011" => '3',
            "214" => '4',
            "070" => '5',
            "058" => '6',
            "030" => '7',
            "082" => '8',
            "221" => '9',
            "232" => '10',
            "033" => '11',
            "032" => '12',
            "566" => '13',
            "035" => '14',
            "057" => '15',
          
        );


        if (array_search($bankId, $bank_codes)) {

            return array_search($bankId, $bank_codes);

        } else {

            return 'false';

        }

    }

    public function getToken($body){
        $headers = [];
        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);

        if ($this->posted["src"] == 'airtel' || $this->posted["src"] == 'Airtel') {
            $mno = 'airtel';
        }elseif($this->posted["src"] == 'mtn' || $this->posted["src"] == 'MTN'){
            $mno = 'mtn';
        }elseif($this->posted["src"] == '9mobile' || $this->posted["src"] == 'Etisalat'){
            $mno = '9mobile ';
        }

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
                 return $this->createBeneficiary($body, $token);
            }else{
                return $this->result('end', 'Invalid User or Pin');
            }
    } 

    public function createBeneficiary($body, $token)
    {
        $option = $this->get_value("option");
        $bankId = $this->get_value("bank");
        $institutionCode = $this->getInstitutionCode($bankId);
       
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/accounts",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS =>"{\"account_number\":\"$option\",\"bank_code\":\"$institutionCode\"}",
          CURLOPT_HTTPHEADER => array(
            "token: $token",
            "Content-Type: application/json"
          ),
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return 'an error occured\n Please try again';
        }elseif($response) {
            $request = json_decode($response);
            if($request->description == 'Successful'){
                $message = $request->message;
                return $this->result('end', "pCash Benficiary\n$message");
            }else{
                $description = $request->description;
                return $this->result('end',"pCash Benficiary\n$description");
            }
        }else{
            return $this->result('end','Something went wrong\n Please try again');
        }
    }

    

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }


}

