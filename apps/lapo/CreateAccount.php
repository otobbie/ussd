<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Lapo.php";
use Symfony\Component\Yaml\Yaml;

 class CreateAccount extends Lapo{

public function openAccount($body){
    $this->initValues($body);
    $this->set_value("Open", true);
    $this->set_value("menu_option", $this->lastInputed);
    $this->src = $body["msisdn"];
    switch ($this->currentStep) {
        case 1:
            return $this->result("continue", $this->render("open_an_account"));
        case 2:
            $bvn = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
            if (strlen($bvn) <> 11) { 
                return $this->cancelLastAction("You entered an invalid BVN. \n" . $this->render("enter_bvn_lapo"));
            }else{
                $this->set_value("bvn", $this->lastInputed);
                return $this->ValidateBVN($body);
            }
        case 3:
            $bvn = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
            if (strlen($bvn) <> 4) { 
                return $this->cancelLastAction("You entered an invalid pin. \n" . $this->render("enter_pin"));
            } 
            $this->set_value("pin_number", $this->lastInputed);
            return  $this->createLapoAccount($body);                 
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
            return  $this->result("continue", "LAPO Swift Banking\nName $first_name $last_name.\nEnter a 4-digit PIN:");
        }else{ 
            $description = $request->description;
            return $this->cancelLastAction("$description\n" . $this->render("enter_bvn_lapo"));
        }
    }else{
        return  $this->result("continue", "Service temporarily unavailable\n Please try again");
    }
}

public function createLapoAccount($body){
    $pin_number = $this->get_value("pin_number");
    $bvn = $this->get_value("bvn");
    $msisdn = '0' . substr($this->posted["msisdn"], -10);
    $dest_mno = $this->posted["src"];


    $curl = curl_init();
    # Better to contruct the json from array than hard code json string
    curl_setopt_array($curl, array(
        CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/customer-bvn",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS =>"{\"phone\":\"$msisdn\",\"pin\":\"$pin_number\",\"bvn\":\"$bvn\",\"mno\":\"$dest_mno\"}",
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json"
        ),
      ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        return  $this->result("continue", "an error occured\n please try again");
    }elseif($response){
        $request = json_decode($response);
        # better to use the code than description
        if($request->description == 'Successful'){
            $account_number = $request->account_number;
            return  $this->result("End", "Your LAPO INDIVIDUAL SAVINGS ACCOUNT\nhas been created successfully\nAccount number: $account_number. \n Kindly wait 2mins before dialing again.");
        }elseif($request->description != 'Successful'){
        $message  = $request->message;
        $description  = $request->description;
        return  $this->result("End", "LAPO Swift Banking\n$message\n$description");
        }
    else{
        return  $this->result("continue", "Service temporarily unavailable\nPlease try again later");
        }
    }
}

public function render($key){
    $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
    return $arr['pages'][$key];
}

}
