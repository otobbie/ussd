<?php

include_once "apps/Lapo.php";
include_once 'apps/UssdApplication.php';
use Symfony\Component\Yaml\Yaml;

class AirtimeDeposit extends Lapo{


    ########################################################################
    public function depositAirtime($body)
    {
        $this->initValues($body);
         # code...
         switch ($this->currentStep) {
             case 2:
                     return $this->result("continue", $this->render("airtime_account"));
             case 3:
                if (!in_array($this->lastInputed, ['1', '2'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('airtime_account'));
                }
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("action", "my_account");
                        return $this->create_menu($body);
                    case 2:
                        $this->set_value("action", "other_account");
                        return $this->create_menu($body);
                    default:
                        return $this->cancelLastAction($this->render("airtime_account"));
                }
                default:
              return $this->create_menu($body);
       
                 
         }
    }

    public function create_menu($body)
    {
        $mode = $this->get_value("action");
            if($mode == "my_account"){
                return $this->myLapoAccount($body);
            }elseif($mode == "other_account"){
                return $this->otherBank($body);
            }
    }

///////////////////////// Press 1 /////////////////////////////////////////////////////
    public function myLapoAccount($body)
    {
        switch ($this->currentStep) {
           case 3:
                $this->set_value("account_option", $this->lastInputed);
                return $this->result("continue", $this->render("airtime_type"));
           case 4:
                $this->set_value("airtime_type", $this->lastInputed);
                if($this->lastInputed == '1'){
                    return $this->result("continue", $this->render('vendor_voucher')); 
                }elseif($this->lastInputed == '2'){
                    return $this->result("continue", $this->airtime_Balance($body)); 
                }
           case 5:
            if (!in_array($this->lastInputed, ['1', '2', '3', '4'])) {
                return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('vendor_voucher'));
            }
                switch ($this->lastInputed) {
                    case 1:
                        return $this->Glo($body);
                    case 2:
                        return $this->Airtel($body);
                    case 3:
                        return $this->Mtn($body);
                    case 4:
                        return $this->Etisalat($body);
                    default:
                        return $this->cancelLastAction($this->render("airtime_for"));
                }
                
        }
    }


    public function airtime_Balance($body)
    {
        # code...
        return $this->result("continue", "Coming Soon.......");
    }

    public function Glo($body)
    {
        # code...
        return $this->result("continue", "Coming Soon.......");
    }

    public function Airtel($body)
    {
        # code...
        return $this->result("continue", "Coming Soon.......");
    }
    public function Mtn($body)
    {
        # code...
        return $this->result("continue", "Coming Soon.......");
    }
    public function Etisalat($body)
    {
        # code...
        return $this->result("continue", "Coming Soon.......");
    }
    

///////////////////////// Press 2 /////////////////////////////////////////////////////

    public function  otherBank($body)
    {
        # code...
        return $this->result("continue", "Coming Soon.......");
    }



    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }

}