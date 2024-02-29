<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Lapo.php";
use Symfony\Component\Yaml\Yaml;
class CheckAccountBalance extends Lapo{
    public function balanceInAccount($body){
        $this->initValues($body);
        $res;
         switch ($this->currentStep) {
            case 2:
                return $this->getAccount($body);
            case 3:
                if (!in_array($this->lastInputed, ['1', '2', '3','4'])){ 
                    //Refactor this code based on response
                    return $this->cancelLastAction("You entered an invalid account. \n" . $this->getAccount($body));
                } else {
                $this->set_value("account_number", $this->lastInputed);
                return $this->result("continue", $this->render("check_balance"));
                }
            case 4:
                $this->set_value("check_balance", $this->lastInputed);
                return  $this->checkBalance($body);
            default:
                return $this->cancelLastAction($this->render("account_number"));
        }
    }


    

    public function getAccount($body)
    {
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

        if ($err) {
            $this->result("end", "An error occured. Please try again after some time.");
        }

        if ($yummy->code != 0) {

            return $this->result("end", "Unable to fetch account\n" . $yummy->message);

        }

        $count = 1;
        $arr = [];
        foreach ($yummy->items as $account) {
            array_push($arr, $count . ". " . $account->ACCOUNT_NUMBER . " ");
            $this->set_value($count, $account->ACCOUNT_NUMBER);
            $count++;
        }

        $res = implode("\n", array_values($arr));
        return $this->result("continue", "Select Account:\n$res\n00. Back");

    }

    public function checkBalance($body){
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $account_selected = $this->get_value("account_number");
        $debit_account = $this->get_value($account_selected);
        $pin_number = $this->get_value("check_balance");
        $content = date('d-M-Y h:i:s: ');
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/balance?phone=$msisdn&pin=$pin_number&account_number=$debit_account",
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
                return 'An error occured\n please try again';
            }elseif($response) {
                $request = json_decode($response);
                if($request->description == 'Successful'){
                    //$amount = $request->available_balance;
                    $account = $request->account_number;
                    $amount = number_format($request->available_balance, 2);
                    $cur_amount = number_format($request->current_balance, 2);
                    #$description = $request->message;
                    #return $this->result("End", "LAPO Swift Banking\n$description");
                    #$content = date('d-M-Y: ');
                    return $this->result('end', "LAPO Swift Banking\nAccount Number: $account \nAvailable Balance: NGN$amount\nCurrent Balance: NGN$cur_amount");
                }elseif($request->description != 'Successful'){
                    $description = $request->message;
                    // return $this->cancelLastAction("$description\n");
                    return  $this->result("end", "$description");
                }else{
                    return  $this->result("end", "Service temporarily unavailable\n Please try again later");
                }
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