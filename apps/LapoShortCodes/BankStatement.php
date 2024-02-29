<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Lapo.php";
use Symfony\Component\Yaml\Yaml;
class BankStatement extends Lapo{

    #######################################################################################
    public function Statement($body){
        $this->initValues($body);
        $this->set_value("isShortCode", true);
        $this->set_value("lapo_type", "bankstatement");

        switch ($this->currentStep) {
            case 1: 
                return $this->result("continue", $this->render("pin"));                                              
            case 2:
                $this->set_value("pin", $this->lastInputed);
                $pin = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                $validate = $this->PIN($body);
                if (strlen($pin) <> 4) { 
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }elseif($validate == "Successful"){
                    return $this->QueryStatement($body);
                }else{
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }      
        }
    }


    public function PIN($body){

        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);

        $curl = curl_init();

       curl_setopt_array($curl, array(
       CURLOPT_URL => " http://10.0.0.140:8080/ords/pcash/fcubs/auth?phone=$msisdn&pin=$pin",
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
           return "Service temporarily unavailable\n Please try again";
       }
}

    public function getAccount($body){
        $curl = curl_init();
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        curl_setopt_array($curl, array(
        CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/accounts-by-phone?phone=$msisdn",
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
        $yummy = json_decode($response);
        $count = 1;
        $arr = [];
        foreach($yummy->items as $account){
               array_push($arr, $count.". ".$account->ACCOUNT_NUMBER." ");
               $this->set_value($count, $account->ACCOUNT_NUMBER);
                $count++;
        }
        return  $this->result("continue", "Select Account:\n". implode("\n",array_values($arr)));
        // return(implode("\n",array_values($arr)));
    }

    public function QueryStatement($body)
    {
        $content_three = $this->get_value("content_three");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $pin = $this->get_value("pin");
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/transactions?phone=$msisdn&pin=$pin&account_number=$content_four",
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
        $yummy = json_decode($response);

        if ($err) {
            return "An error occured\n Please try again";
        }

        if ($yummy->code != 0) {
            
            return  $this->result("end", "Unable to fetch statements\n".$yummy->message);

        }

        $count = 1;
        $arr = [];
         foreach($yummy->items as $account){
            array_push($arr, $count . ".)" . $account->ADDTEXT. " Amt: NGN " . number_format($account->LCYAMT,2) . " Ref: " . $account->TRNREF);
                $count++;
        }
        return(implode("\n",array_values($arr)));
    }



    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }
}