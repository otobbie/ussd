<?php
include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
use Symfony\Component\Yaml\Yaml;
class pCashActivate extends UssdApplication{

    public function getResponse($body){
        $this->initValues($body);
        $check =  $this->Check($body);
        switch ($this->currentStep) {
            case 1:
            if($check == "Successful"){
                return $this->result("continue", $this->render("pin"));
            }elseif($check != "Successful"){
                return $this->result("continue", $this->render("not_a_customer"));
            }
            case 2:
                $this->set_value("pin", $this->lastInputed);
                return $this->Activate($body);
        }
    }

    public function Check($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/registered-phone-number?phone=$msisdn",
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
                return "an error occured\n please try again";
            }elseif($response) {
                $request = json_decode($response);
                    $description = $request->description;
                    return $description;
            }else{
                return "Something went wrong\n Please try again";
            }
    }

    
    public function Activate($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $pin = $this->get_value("pin");
        $curl = curl_init();
    
        $postdata = array(
            'phone' => $msisdn,
            "pin" => $pin,  
        );
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://10.0.0.140:8080/ords/pcash/api/activate-customer',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($postdata),
        CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
        ),
        ));
           
        $response = curl_exec($curl);
        $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return "an error occured\n please try again";
            }elseif($response) {
                $request = json_decode($response);
                $description = $request->description;
                return $this->result("End", "pCash Nano Lending\n$description");
            }else{
                return "Something went wrong\n Please try again";
            }
    }
    public function render($key) 
    {
        $arr = Yaml::parse(file_get_contents("apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }

    
}
