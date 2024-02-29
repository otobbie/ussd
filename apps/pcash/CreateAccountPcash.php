<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Pcash.php";
use Symfony\Component\Yaml\Yaml;

 class CreateAccountPcash extends Pcash{

        public function openAccount($body)
        {
            $this->initValues($body);
            $this->set_value("Open", true);
            switch ($this->currentStep) {
                case 1:
                    $this->set_value("menu_option", $this->lastInputed);
                    return $this->result("continue", $this->render("enter_bvn"));
                case 2:
                    $bvn = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                    if (strlen($bvn) <> 11) { 
                        return $this->cancelLastAction("You entered an invalid BVN. \n" . $this->render('invalid'));
                    }
                    $this->set_value("bvn", $this->lastInputed);
                    return $this->ValidateBVN($body);
                case 3:
                    $pin = $bvn = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                    if (strlen($pin) <> 4) { 
                        return $this->cancelLastAction("You entered an invalid pin. \n" . $this->render('enter_pin'));
                    } 
                    $this->set_value("pin", $this->lastInputed);
                    return $this->queryBVN($body);
                
            }
        }

        public function ValidateBVN($body)
        {
            $bvn = $this->get_value('bvn');
            $msisdn = '0' . substr($this->posted["msisdn"], -10);
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/bvn-check?phone=$msisdn&bvn=$bvn",
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
                return  $this->result("continue", "an error occured\n please try again");
            }elseif($response) {
                $request = json_decode($response);
                if($request->description == 'Successful'){
                    $first_name = $request->first_name;
                    $last_name = $request->last_name;
                    return  $this->result("continue", "pCash Nano Lending\nName: $first_name $last_name.\nEnter a 4-digit PIN:");
                }else{ 
                    $description = $request->description;
                    return $this->cancelLastAction("$description\n" . $this->render("enter_bvn_lapo"));
                }
            }else{
                return  $this->result("continue", "Service temporarily unavailable\n Please try again");
            }
        }


        public function queryBVN($body){
            $msisdn = '0' . substr($this->posted["msisdn"], -10);
            $bvn = $this->get_value("bvn");
            $pin = $this->get_value("pin");
                $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/customer-with-bvn",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\"bvn\":\"$bvn\",\"phone\":\"$msisdn\",\"pin\":\"$pin\",\"device\":\"ussd\"}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json"
                ),
                ));
                $err = curl_error($curl);
                $response = curl_exec($curl);
                curl_close($curl);
                if ($err) {
                    return  $this->result("continue","an error occured\n please try again");
                }elseif($response) {
                    $request = json_decode($response);
                    if($request->description == 'Successful'){
                        return $this->result("end", $this->render("p_success"));
                    }elseif($request->description != 'Successful'){
                        $description = $request->description;
                        return $this->result("end", "pCash Nano Lending\n$description");
                    }
                }else{
                    return  $this->result("continue", "Something went wrong\n Please try again");
                }
        }

        public function render($key) {
            $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
            return $arr['pages'][$key];
        }

}

