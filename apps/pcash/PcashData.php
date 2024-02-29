<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Pcash.php";
use Symfony\Component\Yaml\Yaml;

class PcashData extends Pcash{


    public function BuyData($body){
        $this->initValues($body);
        switch ($this->currentStep) {
            case 2:
                return $this->result("continue", $this->render("data_for"));
            case 3:
                if (!in_array($this->lastInputed, ['1', '2'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render("data_for"));
                }
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("data_action", "data_for_me");
                        return $this->aritime_menu($body);
                    case 2:
                        $this->set_value("data_action", "data_others");
                        return $this->aritime_menu($body);
                    default:
                        return $this->cancelLastAction($this->render("airtime_for"));
                }
                    default:
                         return $this->aritime_menu($body);
        }
        
    }
    public function aritime_menu($body) {
        $mode = $this->get_value("data_action");
            if($mode == "data_for_me"){
                return $this->Self($body);
            }elseif($mode == "data_others"){
                return $this->Others($body);
            }
    }

    
    public function Self($body){
         $plan = $this->DataPlan($body);
        switch ($this->currentStep) {
            case 3:
                return $this->result("continue", "Select Plan:\n$plan\n0. Next");
            case 4: 
                $amt = $this->get_value($this->lastInputed.'-amount');
                $gig = $this->get_value($this->lastInputed.'-gig');
                $exploded = explode(' ', $gig);
                $gigs = $exploded[1];
                $content = $exploded[2];
                $edited =  str_replace("data", "", $content);
                if($this->lastInputed == '0'){
                    return $this->cancelLastAction("Select Plan:\n$plan\n00. Back");
                }elseif($this->lastInputed == '00'){
                    return $this->cancelLastAction("Select Plan:\n$plan\n0. Next");
                }else{
                    $this->set_value("bill_id", $this->lastInputed);
                    return $this->result("continue", "You are about to purchase\n$gigs$edited @ NGN$amt for self\n" . $this->render("pin"));    
                }
            case 5: 
                $this->set_value("pin", $this->lastInputed);
                $token = $this->getToken($body);
                if($token){
                    return $this->purchaseForMe($body, $token);
                }else{
                return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }                     
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
            }else{
                $token =  $heads->token['0'];
                return  $token;
             }

    }

        public function DataPlan($body)
        {
            $dest_mno = $this->posted["src"];
            $curl = curl_init();

                curl_setopt_array($curl, array(
                CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/data-plan?mno=$dest_mno",
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

                foreach($yummy->items as $mno){
                $exploded = explode(' ', $mno->short_name);
                $gig = $exploded[1];
                $content = $exploded[2];
                $amount = $mno->amount;
                $edited =  str_replace("data", "", $content);
                array_push($arr, $count.". ".$gig ." ".$edited." @ N" . $amount."");
                $this->set_value($count.'-bill_id', $mno->bill_id);
                $this->set_value($count.'-amount', $mno->amount);
                $this->set_value($count.'-gig', $mno->short_name);
                $count++;
                }

                if($this->lastInputed === '0'){
                    return(implode("\n",array_values(array_slice($arr,9))));
                }else{
                    return(implode("\n",array_values(array_slice($arr,0,9))));
                }
        }

        
    public function purchaseForMe($body, $token){
        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $bill = $this->get_value("bill_id");
        $bill_id = $this->get_value($bill.'-bill_id');

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/data",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS =>" {\"phone\":\"$msisdn\",\"pin\":\"$pin\",\"dest_number\":\"$msisdn\",\"bill_id\":$bill_id}",
          CURLOPT_HTTPHEADER => array(
            "token: $token",
            "Content-Type: application/json"
          ),
        ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return "an error occured\n Please try again";
            }elseif($response) {
                $request = json_decode($response);
                    $message = $request->message;
                    return $this->result("end", "pCash Nano Lending\n$message");
            }else{
                return $this->result("end","Service temporarily unavailable\n Please try again");
            }
    }

    public function Others($body){
        switch ($this->currentStep) {
            case 3:
                return $this->result("continue", $this->render("beneficiary_number"));
            case 4:
                $beneficiary = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if (strlen($beneficiary) <> 11) { 
                    return $this->cancelLastAction("You entered an invalid phone number. \n" . $this->render("beneficiary_number"));
                }
                $this->set_value("dest_number", $this->lastInputed);
                return $this->result("continue", $this->render("vendor_voucher"));
            case 5:
                $this->set_value("dest_mno", $this->lastInputed);
                if (!in_array($this->lastInputed, ['1', '2', '3','4'])){ 
                    return $this->cancelLastAction("You enter an invalid option. \n" . $this->render("vendor_voucher"));
                } 
                $plan = $this->DataPlanOther($body);
                return $this->result("continue", "Select Plan:\n$plan");
            case 6: 
                $plan = $this->DataPlanOther($body);
                $msisdn = '234' . substr($this->get_value("dest_number"), -10);
                $amt = $this->get_value($this->lastInputed.'-amount');
                $gig = $this->get_value($this->lastInputed.'-gig');
                $exploded = explode(' ', $gig);
                $gigs = $exploded[1];
                $content = $exploded[2];
                $edited =  str_replace("data", "", $content);
                if($this->lastInputed == '0'){
                    return $this->cancelLastAction("Select Plan:\n$plan\n00. Back");
                }elseif($this->lastInputed == '00'){
                    return $this->cancelLastAction("Select Plan:\n$plan\n0. Next");
                }else{
                    $this->set_value("bill_id", $this->lastInputed);
                    return $this->result("continue", "You are about to purchase\n$gigs$edited @ NGN$amt for $msisdn\n" . $this->render("pin"));    
                }
            case 7: 
                $this->set_value("pin", $this->lastInputed);
                $token = $this->getToken($body);
                if($token){
                    return $this->purchaseForOther($body, $token);
                }else{
                return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }       
        }
    }

    public function DataPlanOther($body)
    {
        $mno = $this->get_value("dest_mno");
        if ($mno == "1") {
            $dest_mno = 'mtn';
        }elseif($mno == "2"){
            $dest_mno = 'glo';
        }elseif($mno == "3"){
            $dest_mno = 'airtel';
        }else{
            $dest_mno = '9mobile ';
        }

        $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/data-plan?mno=$dest_mno",
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

            // print_r($yummy);
            foreach($yummy->items as $mno){
                $exploded = explode(' ', $mno->short_name);
                $gig = $exploded[1];
                $content = $exploded[2];
                $amount = $mno->amount;
                $edited =  str_replace("data", "", $content);
                array_push($arr, $count.". ".$gig ." ".$edited." @ N" . $amount."");
                $this->set_value($count.'-bill_id', $mno->bill_id);
                $this->set_value($count.'-amount', $mno->amount);
                $this->set_value($count.'-gig', $mno->short_name);
                $count++;
                }

                if($this->lastInputed === '0'){
                    return(implode("\n",array_values(array_slice($arr,9))));
                }else{
                    return(implode("\n",array_values(array_slice($arr,0,9))));
                }
    }


    public function purchaseForOther($body,$token){
   
        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $dest_number = $this->get_value("dest_number");
        $bill = $this->get_value("bill_id");
        $bill_id = $this->get_value($bill.'-bill_id');

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://pcash.novajii.commsisdn/ords/pcash/api/data",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS =>" {\"phone\":\"$msisdn\",\"pin\":\"$pin\",\"dest_number\":\"$dest_number\",\"bill_id\":$bill_id}",
          CURLOPT_HTTPHEADER => array(
            "token: $token",
            "Content-Type: application/json"
          ),
        ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return "an error occured\n Please try again";
            }elseif($response) {
                $request = json_decode($response);
                    $message = $request->message;
                    return $this->result("end", "pCash Nano Lending\n$message");
            }else{
                return $this->result("end","Service temporarily unavailable\n Please try again");
            }
       
    }


    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }


}

