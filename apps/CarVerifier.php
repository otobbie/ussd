<?php

include_once 'UssdApplication.php';
include_once '_init.php';
require 'vendor/autoload.php';

class CarVerifier extends UssdApplication {

    private $inputKey;
    private $userKey;
    private $body;
    private $lastInput;

    public function getResponse($body) {

        //global $redis;
        $action = $this->continueSession();
        $reply = 'Car Verifier';
        $userKey = Ussd::getUserKey($body['msisdn']);
        $inputKey = Ussd::getInputKey($body['msisdn']);
        $inputs = Ussd::getUserInputs($inputKey);
        $lastInput = $body['content'];
        $this->inputKey = $inputKey;
        $this->userKey = $userKey;
        $this->body = $body;
        $this->lastInput = $lastInput;
        # main application service flow
        $input_count = count($inputs);
		
        # Step 1: Welcome
        if ($input_count == 1) {
            $reply = $this->display('intro');
            $action = $this->continueSession();
        }
		
        # step 2: Get policy or pate number
        if ($input_count == 2) {
			$sv = $inputs[1];

			$result = $this->checkPolicy($sv); 
			$reply = $this->getPolicyStatusMessage($result);
			
			$action = $this->endSession();
        }

        return array(
			'action' => $action,
            'message' => $reply
		);
    }

    public function display($key) {
        $options = array('intro' => "Car verifier. \n Enter vehicle number");
        return $options[$key];
    }

    public function checkPolicy($str) {
        if (strlen(trim($str)) == 8) {
            $searchType = 'Registration number';
        
			$sv = urlencode($str);
			$st = urlencode($searchType);

			$result = file_get_contents("http://restapi.niid.org/NIIDRESTService/Verification?st=$st&sv=$sv");
			return json_decode($result);
		}
		
		return false;
    }

    public function getPolicyStatusMessage($json) {
        $result = json_decode($json);

        $status = $result->{'LicenseStatus'};

        switch ($status) {
            case "OK":
                $message = "Car verifier says:"
						. "\n Vehicle Plate No: ". $result->NewRegistrationNumber
                        . "\n Car Make: ". $result->VehicleMake . " " . $result->VehicleModel
                        . "\n Color: ". $result->Color
						. "\n Chassis Number: ". $result->ChassisNumber;
                break;
            case "NOT FOUND":
                $message = "Vehicle does not exist.";
                break;
            default:
                $message = "Car verifier says\nCar not found.";
                break;
        }
        return $message;
    }

    function correctPlateNumber($car_number) {
        $length = strlen($car_number);
        if ($length == 8) {
            $first = substr($car_number, 0, 3);
            $second = substr($car_number, 3, 3);
            $third = substr($car_number, 6, 2);

            return (ctype_alpha($first) && is_numeric($second) && ctype_alpha($third));
        } else {
            return false;
        }
    }

    # we implement a generic function so that subclasses can re-use logic 
    # without duplicatin same code

    function validPolicyInput($sv) {
        if ($this->correctPlateNumber(trim($sv)) || $this->correctPolicyNumber(trim($sv))) {
            return true;
        }
        return false;
    }

    /* PRM/24/4/45861/16-130298ID */
    ## PRM/24/4/24053/16-101858ID

    function correctPolicyNumber($policy_no) {
        $length = strlen($policy_no);
        if ($length == 26) {
            /*
              $str1 = substr($policy_no, 0, 3);
              $str2 = substr($policy_no, 3, 1);
              $str3 = substr($policy_no, 4, 2);
              $str4 = substr($policy_no, 6, 1);
              $str5 = substr($policy_no, 7, 1);
              $str6 = substr($policy_no, 8, 1);
              $str7 = substr($policy_no, 9, 5);
              $str8 = substr($policy_no, 14, 1);
              $str9 = substr($policy_no, 15, 2);
              $str10 = substr($policy_no, 17, 1);
              $str11 = substr($policy_no, 18, 6);
              $str12 = substr($policy_no, 24, 2);
              return (ctype_alpha($str1) && slash_sh($str2) && is_numeric($str3) &&
             * slash_sh($str4) && is_numeric($str5) && slash_sh($str6) && is_numeric($str7) && slash_sh($str8) && is_numeric($str9) && hyphen_sh($str10) && is_numeric($str11) && ctype_alpha($str12));
             */
            RETURN TRUE;
        } else {
            return false;
        }
    }

    function slash_sh($str) {
        if ($str == '/') {
            return true;
        } else {
            return false;
        }
    }

    function hyphen_sh($str) {
        if ($str == '-') {
            return true;
        } else {
            return false;
        }
    }
}
