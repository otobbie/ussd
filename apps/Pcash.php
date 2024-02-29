<?php
include_once 'pcash/LoanRequestPcash.php';
include_once 'pcash/SelfServicePcash.php';
include_once 'pcash/CreateAccountPcash.php';
include_once 'pcash/AccountBalance.php';
include_once 'pcash/PTransferFunds.php';
include_once 'pcash/BeneficiaryAccount.php';
include_once 'pcash/PcashData.php';
include_once 'pcash/Airtime.php';
include_once 'pcash/PaybillsPcash.php';
include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
use Symfony\Component\Yaml\Yaml;
class Pcash extends UssdApplication{

    public function getResponse($body){
        $this->initValues($body);
        $check =  $this->Check($body);
        $open = $this->get_value("Open");
        if($open == true){
            return $this->Welcome($body);
        }
        switch ($this->currentStep) {
            case 1:
                if($check == "Successful"){
                    return $this->result("continue", $this->render("pcash_menu"));
                  }elseif($check != "Successful"){
                      return $this->Welcome($body);
                  }
            case 2:
                if (!in_array($this->lastInputed, ['1', '2', '3', '4','5','6','7'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render("pcash_menu"));
                }
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("action", "loan_request");
                        return $this->create_main_menu($body);
                    case 2:
                        $this->set_value("action", "account_balance");
                        return $this->create_main_menu($body);
                    case 3:
                        $this->set_value("action", "transfer");
                        return $this->create_main_menu($body);
                    case 4: 
                        $this->set_value("action", "data");
                        return $this->create_main_menu($body);
                    case 5: 
                        $this->set_value("action", "airtime");
                        return $this->create_main_menu($body);
                    case 6:
                        $this->set_value("action", "paybills");
                        return $this->create_main_menu($body);
                    case 7:
                        $this->set_value("action", "self_service");
                        return $this->create_main_menu($body);
                    default:
                        return $this->cancelLastAction($this->render("pcash_menu"));
                }
                    default:
                        return $this->create_main_menu($body);
            
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

    
    function Welcome($body)
    {
        $open = new CreateAccountPcash();
        return $open->openAccount($body);
    }

    public function create_main_menu($body)
    {
        $mode = $this->get_value("action");
        if($mode == "loan_request"){
            $loan = new LoanRequestPcash();
            return $loan->Loan($body);
        }elseif($mode == "account_balance"){
            $loan = new AccountBalance();
            return $loan->Balance($body);
        }elseif($mode == "transfer"){
            $beneficiary = new PTransferFunds();
            return $beneficiary->Transfer($body);
        }elseif($mode == "data"){
            $data = new PcashData();
            return $data->BuyData($body);
        }elseif($mode == "airtime"){
            $airtime = new Airtime();
            return $airtime->purchaseAirtime($body);
        }elseif($mode == "paybills"){
            $paybills = new PaybillsPcash();
            return $paybills->PayingBill($body);
        }elseif($mode == "self_service"){
            $self = new SelfServicePcash();
            return $self->Service($body);
        }
    }
    

    public function render($key) 
    {
        $arr = Yaml::parse(file_get_contents("apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }

    
}
