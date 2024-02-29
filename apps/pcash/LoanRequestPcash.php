<?php

include_once 'apps/UssdApplication.php';
include_once "apps/Pcash.php";

use Symfony\Component\Yaml\Yaml;
class LoanRequestPcash extends Pcash{


        public function Loan($body){

            $this->initValues($body);
            # code...
            switch ($this->currentStep) {
                case 2:
                    $check =  $this->Check($body);
                    if($check == "Successful"){
                        return $this->result("continue", $this->render("loan_menu"));
                    }elseif($check != "Successful"){
                        return $this->result("continue", $this->render("not_customer"));
                    }
                case 3:
                    if (!in_array($this->lastInputed, ['1', '2','3'])) {
                        return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('loan_menu'));
                    }
                    switch ($this->lastInputed) {
                        // case 1: 
                            // $this->set_value("loan_action", "card");
                            // return $this->loan_menu($body);
                        case 1:
                            $this->set_value("loan_action", "apply");
                            return $this->loan_menu($body);
                        case 2:
                            $this->set_value("loan_action", "balance");
                            return $this->loan_menu($body);
                        case 3: 
                            $this->set_value("loan_action", "repay");
                            return $this->loan_menu($body);
                        default:
                            return $this->cancelLastAction($this->render("loan_menu"));
                    }
                    default:
                        return $this->loan_menu($body);
                        
            }
            
    }

        public function loan_menu($body)
        {
            $mode = $this->get_value("loan_action");
            // if($mode == "card"){
            //     return $this->Card($body);
            // }else
            if($mode == "apply"){
                return $this->AppplyForLoan($body);
            }elseif($mode == "balance"){
                return $this->CheckLoanBalance($body);
            }elseif($mode == "repay"){
                return $this->RepayLoan($body);
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

        // public function Card($body)
        // {
        //     switch ($this->currentStep) {
        //         case 3:
        //             $this->set_value("menu_option", $this->lastInputed);
        //             return $this->result("continue", $this->render("card"));
        //         case 4:
        //             $card = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
        //             if (strlen($card) <> 16) { 
        //                 return $this->cancelLastAction("You entered an invalid card number.\n" . $this->render("card"));
        //             }
        //             return $this->result("continue", $this->render("cvv"));
        //         case 5: 
        //             $cvv = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
        //             if (strlen($cvv) <> 3) { 
        //                 return $this->cancelLastAction("You entered an invalid card number.\n" . $this->render("cvv"));
        //             }
        //             return $this->result("continue", $this->render("exp_expiry"));
        //         case 6:
        //             $date = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
        //             if (strlen($date) <> 3) { 
        //                 return $this->cancelLastAction("You entered an invalid card number.\n" . $this->render("cvv"));
        //             }
        //             return $this->result("continue", $this->render("pin"));
        //         case 7:
        //             $this->set_value("pin", $this->lastInputed);
        //             $token = $this->getToken($body);
        //             if($token){
        //                 return $this->checkBalance($body, $token);
        //             }else{
        //             return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
        //         }
        // }
            
        // }

        public function AppplyForLoan($body)
        {
            $loan = $this->DisplayLoanLimit($body);
            $amount = number_format($loan,2);
            switch ($this->currentStep) {
                case 3:
                    $this->set_value("menu_option", $this->lastInputed);
                    $reason = $this->LoanReason($body);
                    return $this->result("continue", "Select Loan Reasons:\n$reason");
                case 4:
                    if (!in_array($this->lastInputed, ['1', '2', '3','4','5','6'])){ 
                        return $this->cancelLastAction("You entered an invalid option.\nSelect Loan Reasons\n" . $this->LoanReason($body));
                    }else{
                        $this->set_value("loan_reason", $this->lastInputed);
                        return $this->result("continue",  "You current loan limit is:$amount\nEnter Amount");
                    } 
                case 5: 
                    $amount_input = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                    if ($loan == 0) {
                        return $this->result("continue", "Customer with outstanding cannot request new loan");
                    }elseif($amount_input <= 4900){
                        return $this->cancelLastAction("You entered an amount too low.\nLoan starts from N5000\n" . $this->render("principal"));
                    }else{
                        $this->set_value("principal", $this->lastInputed);
                        return $this->result("continue",  $this->render("tenure_day"));
                    }
                case 6:
                    if (!in_array($this->lastInputed, ['1', '2', '3','4'])){ 
                        return $this->cancelLastAction("You entered an invalid option.\nSelect Loan Reasons\n" . $this->render("tenure_day"));
                    }
                    $this->set_value("tenure_day", $this->lastInputed);
                    return $this->ComputeLoan($body);
                case 7: 
                    $this->set_value("pin", $this->lastInputed);
                    $token = $this->getToken($body);
                    if($token){
                        return $this->SendRequest($body, $token);
                    }else{
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }    
            }
        }


        public function CheckLoanBalance($body)
        {
            switch ($this->currentStep) {
                case 3:
                    $this->set_value("menu_option", $this->lastInputed);
                    return $this->result("continue", $this->render("pin"));
                case 4:
                    $this->set_value("pin", $this->lastInputed);
                    $token = $this->getToken($body);
                    if($token){
                        return $this->checkBalance($body, $token);
                    }else{
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }
        }
    }


        public function RepayLoan($body)
        {
            switch ($this->currentStep) {
                case 3:
                    $this->set_value("menu_option", $this->lastInputed);
                    return $this->result("continue", "Coming soon. Please download the pCash mobile app to repay your loans");
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

            public function DisplayLoanLimit()
            {
                $msisdn = '0' . substr($this->posted["msisdn"], -10);
                $curl = curl_init();

                curl_setopt_array($curl, array(
                  CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/profile?phone=$msisdn",
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => "",
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 0,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => "GET",
                  CURLOPT_HTTPHEADER => array(
                  ),
                ));
                
                $response = curl_exec($curl);
    
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
                if ($err) {
                    return ("an error occured\n please try again");
                }elseif($response) {
                    $request = json_decode($response);
                    $firstname = $request->items[0]->firstname;
                    $current_credit_limit = $request->items[0]->current_credit_limit;
                    return $current_credit_limit;
                }else{
                    print_r("You do not have an account\n");
                    
                }
            }
            

            public function ComputeLoan($body)
            {
                $date = date("Y-m-d");
                $msisdn = '0' . substr($this->posted["msisdn"], -10);
                $principal = $this->get_value("principal");
                $tenure_day = $this->get_value("tenure_day");
                if ($tenure_day == 1) {
                    $tenure = "10";
                }elseif ($tenure_day == 2) {
                    $tenure = "15";
                }elseif ($tenure_day == 3) {
                    $tenure = "20";
                }elseif ($tenure_day == 4) {
                    $tenure = "30";
                }
                $curl = curl_init();

                    curl_setopt_array($curl, array(
                    CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/compute-loan",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS =>"{\"principal\":\"$principal\",\"start_date\":\"$date\",\"tenure\":\"$tenure\",\"phone\":\"$msisdn\"}",
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json"
                    ),
                    ));

                    $response = curl_exec($curl);
                    $err = curl_error($curl);
                    curl_close($curl);
                    if ($err) {
                        return 'an error occured\n please try again';
                    }elseif($response) {
                        $request = json_decode($response);
                            $principal_amount = $request->principal;
                            $amount = number_format($principal_amount,2);
                            $start_date = $request->start_date;
                            $tenure = $request->tenure;
                            $end_date = $request->end_date;
                            $interest_rate = $request->interest_rate * 100;
                            $principal_with_interest = $request->principal_with_interest;
                            return $this->result("continue","pCash Nano Lending:\nAmt: NGN$amount\nStart Dt: $start_date\nTenure: $tenure Days\nEnd Dt: $end_date\nInterest: $principal_with_interest\nEnter PIN to proceed");
                    }else{
                        $request = json_decode($response);
                        $description = $request->description;
                        return $this->cancelLastAction("$description\n" . $this->render("principal"));
                    }
            }

            public function LoanReason($body)
            {
                $curl = curl_init();

                curl_setopt_array($curl, array(
                  CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/loan-reasons",
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
                 foreach($yummy->items as $reason){
                    array_push($arr, $count . ". " .   $reason->description);
                    $this->set_value($count, $reason->description);
                        $count++;
                }
        
                 return(implode("\n",array_values($arr)));
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
                    return "an error occured\n please try again";
                }elseif($response) {
                    $request = json_decode($response);
                        $outstanding_loans = $request->items[0]->outstanding_loans;
                        $amount = number_format($outstanding_loans,2);
                        return $this->result("End","pCash Nano Lending Loan Balance:\nOutstanding Loan Balance is: NGN$amount");
                }else{
                    return $this->result("End","Service temporarily unavailable\nPlease try again");
                }
               
            }
    
            public function SendRequest($body, $token){
                $principal = $this->get_value("principal");
                $beneficiary_account = $this->get_value("beneficiary_account");
                $loan_selected = $this->get_value("loan_reason");
                $loan = $this->get_value($loan_selected);
                $date = date("Y-m-d");
                $curl = curl_init();
                $postdata = array(
                    'name' => $loan,
                    "principal" => $principal,
                    'start_date' => $date,
                    "tenure" => 30,
                    'beneficiary_account' => '',
                    'type_id' => 21
        
                );
                    curl_setopt_array($curl, array(
                    CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/loan",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($postdata),
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json",
                        "token: $token"
                    ),
                    ));
                    $response = curl_exec($curl);
                    $err = curl_error($curl);
                    curl_close($curl);
                    if ($err) {
                        return "an error occured\n Please try again";
                    }elseif($response) {
                        $request = json_decode($response);
                        if($request->description == "Successful"){
                            $description = $request->description;
                            return $this->result("End","pCash Nano Lending Loan Request\n$description");
                        }elseif($request->description != 'Successful'){
                            $description = $request->description;
                            return $this->result("End","pCash Nano Lending Loan Request\n$description");
                        }
                    }else{
                        return $this->result("End","Service temporarily unavailable\n Please try again");
                    }
            }

            public function render($key){
                $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
                return $arr['pages'][$key];
            }
    
}