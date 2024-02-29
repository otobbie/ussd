<?php

use Symfony\Component\Yaml\Yaml;

include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
include_once 'Mcash.php';
include_once 'Rave.php';
require_once 'lib/classes/rb.php';

/**
 * Description Magpora Housing Ussd
 * @author Emmanuel
 **/
class Magpora extends UssdApplication
{

    public $msisdn;
    private $merchant_code = "77000717";
    public function getResponse($body)
    {
        $this->initValues($body);
        $curr = $this->currentStep;
        $last = $this->lastInputed;

        switch ($curr) {
            case 1:
                return $this->result("continue", $this->render("welcome"));
            case 2:
                switch ($last) {
                    case 1:
                        $this->set_value("action", "visitor");
                        return $this->confirm_user($body);
                    case 2:
                        $this->set_value("action", "record");
                        return $this->confirm_user($body);
                    // case 3:
                    //     $this->set_value("action", "statement");
                    //     return $this->flow($body);
                    case 3:
                        $this->set_value("action", "complaint");
                        return $this->flow($body);
                    // case 5:
                    //     $this->set_value("action", "payment");
                    //     return $this->flow($body);
                    case 4:
                        $this->set_value("action", "cancel");
                        return $this->confirm_user($body);
                    default:
                        return $this->cancelLastAction($this->render("welcome"));
                }
            default:
                return $this->confirm_user($body);
        }
    }

    public function confirm_user($body){
        $phone_number = '0' . substr($this->posted["msisdn"], -10);
        $result = json_decode(file_get_contents("https://magpora.com/api/visitor/confirm_msisdn?phone="."{$phone_number}"));
        if($result->message === 'No record with that number'){
            return $this->result("end", $this->render("not_found"));
        }else{
            return $this->flow($body);
        }
    }
    public function flow($body)
    {       
        $mode = $this->get_value("action");
        if ($mode == "visitor") {
            return $this->addVisitor($body);
        }elseif($mode == "record"){
            return  $this->getVisitorRecord();
        } elseif ($mode == "complaint") {
            return $this->getComplaint($body);
        } elseif ($mode == "cancel") {
            return $this->cancelVisitor($body);
        }
       // } elseif ($mode == "statement") {
        //     return $this->getAccountStatement($body);
        // } elseif ($mode == "payment") {
        //     return $this->getMakepayment($body);
        
    }
 //************************** BOOK / GET VISITOR'S INFORMATION *************************//
    public function addVisitor()
    {
        $curr = $this->currentStep;

        switch ($curr) {
            case 2:
                $this->set_value("visitor", $this->lastInputed);
                return $this->result("continue", $this->render("name"));
            case 3:
                $this->set_value("name", $this->lastInputed);
                return $this->result("continue", $this->render("phone"));
            case 4:
                $this->set_value("phone", $this->lastInputed);
                return $this->result("continue", $this->render("destination"));
            case 5:
                $this->set_value("destination", $this->lastInputed);
                return $this->result("continue", $this->render("email"));
            case 6:
                $this->set_value("email", $this->lastInputed);
                return $this->result("continue", $this->render('purpose'));
            case 7:
                $this->set_value("purpose", $this->lastInputed);
                return $this->result("continue", $this->confirmDetails());
            case 8:
            $last = $this->lastInputed;
            if($last == "1"){
                return $this->result("continue", $this->getVisitorInfo());
            }
            
        }
    }

    public function confirmDetails(){
        $visitor_name = $this->get_value("name");
        $visitor_phone = $this->get_value("phone");
        $visitor_address = $this->get_value("destination");
        $visitor_email = $this->get_value("email");
        $date = date("d-M-Y");
        $date1 = strtotime($date);
        $date2 = strtotime("+1 day", $date1);
        $exist_date =  date('m-d-Y', $date2);
        $visitor_passcode = rand(1000, 10000);
            return "Magpora Services" . "\nName: " . $visitor_name . "\nEmail: " .  $visitor_email . "\nPhone Number: " . $visitor_phone . "\nAddress:" . $visitor_address ."\nPress 1 to continue";
    }
    public function getVisitorInfo()
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $visitor_name = $this->get_value("name");
        $visitor_phone = $this->get_value("phone");
        $visitor_address = $this->get_value("destination");
        $visitor_email = $this->get_value("email");
        $purpose = $this->get_value("purpose");
        $date = date('Y-m-d H:i:s');
        $result = array();
        $postdata =  array(
             "name" => $visitor_name, 
             "visitor_email" => "info@magpora.com",
             "phone" => $visitor_phone, 
             "address" => $visitor_address, 
             "tag" => "", 
             "time" => $date, 
             "user_email" => $visitor_email,
             "reason" => $purpose
        );
        
        $url = "https://magpora.com/api/visitor/book";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));  //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer sk_live_a465b458b950ecaf90ff015a712b2c65cc5ecc94',
            'Content-Type: application/json',

        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);

        curl_close($ch);
        if ($request) {
            if ($request) {
                return 'Visitor Successfully Booked';
            } else {
                return 'Process Incomplete Please Try Again';
            }
        }
    }
   
                //********************** GETTING VISITOR'S ALREADY SAVED INFORMATION WITH PHONE NUMBER*******************//

    public function getVisitorRecord(){
        $curr = $this->currentStep;

        switch ($curr) {
            case 2:
                $this->set_value("record", $this->lastInputed);
                return $this->result("continue", $this->render("number"));
            case 3:
                $this->set_value("number", $this->lastInputed);
                return $this->result("continue", $this->getRecord());
    }
    }
    public function getRecord(){
        $phone_number = $this->get_value("number");
        $result = json_decode(file_get_contents("https://magpora.com/api/visitor/get_visitor_record?phone="."{$phone_number}"));
        
        if ($result) {
            $name = $result->name;
            $id_proof_no = $result->id_proof_no;
            $id = $result->id;
            $phone = $result->phone;
            $street = $result->street;
            $email = $result->email;
            return "Magpora Services " . "\nName: " . $name . "\nPasscode: " . $id_proof_no . "\nID: " .  $id . "\nPhone Number"
            .$phone ."\nAddress" . $street ."\nEmail" . $email . "";
        }else{
            return "Visitor Record Does Not Exit";
        }
    }

    public function getComplaint($body)
    {
        $curr = $this->currentStep;
        switch ($curr) {
            case 2:
                $this->set_value("option", $this->lastInputed);
                return $this->result("continue", $this->render("compliant"));
            case 3:
                $this->set_value("compliant", $this->lastInputed);
                return $this->result("continue", $this->render("complianttype"));
            case 4:
                $this->set_value("compliant_type", $this->lastInputed);
                $this->submithCompliant();
                return $this->result("End", $this->render("message"));
        }
    }
    
   
    public function cancelVisitor($body)
    {

        $curr = $this->currentStep;
        switch ($curr) {
            case 2:
                $this->set_value("option", $this->lastInputed);
                return $this->result("continue", $this->render("number"));
            case 3:
                $this->set_value("number", $this->lastInputed);
                return $this->result("continue", $this->deleteVisitor());   
        }
    }

    public function deleteVisitor(){
        $msisdn = $this->get_value("number");
        $result = json_decode(file_get_contents("https://magpora.com/api/visitor/cancel_visit?phone="."{$msisdn}"));
        if($result->state === 'cancelled'){
            return "Mogpora Services \nVisitor Cancelled Succefully";
        }else{
            return "Mogpora Services \nProcess Failed Due to Incorrect Phone Number\nPlease Try Again";
        }
        
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("apps/config/magpora/config.yml"));
        return $arr['pages'][$key];
    }
}