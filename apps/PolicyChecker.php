<?php

include_once 'UssdApplication.php';
include_once 'init.php';
require 'vendor/autoload.php';

class PolicyChecker extends UssdApplication {

   /* private $inputKey;
    private $userKey;
    private $body;
    private $lastInput;
*/
    public function getResponse($body) {

        //global $redis;
        $action = $this->continueSession();
        $reply = 'NIID Policy Checker';
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
        $this->initValues($body);
        # Step 1: Welcome
        if ($input_count == 1) {
            # log to NIID portal
            try {
                $this->logDb($body);
            } catch (Exception $ex) {
                mail("support@novajii.com"
                        , "NIID API Error on Bluemix"
                        , "We have a problem logging request to Bluemix on NIID. Please check ASAP");
            }
            $reply = $this->display('intro');
            $action = $this->continueSession();
        }
        # step 2: Get policy or pate number
        if ($input_count == 2) {
            if ($lastInput != '1') {
                $reply = $this->display('intro');
                Ussd::deleteLastInput($inputKey);
            } else {
                $reply = $this->display('policy');
            }
            $action = $this->continueSession();
        }
        # step 3: Check policy or Plate Number
        /*
          if ($input_count == 3) {
          $sv = $inputs[2];
          if ($this->validPolicyInput($sv)) {
          $reply = $this->display('confirm');
          } else {
          Ussd::deleteLastInput($inputKey);
          $reply = $this->display("invalid_policy") . $this->display("policy");
          }
          $action = $this->continueSession();
          }
         *
         */
        if ($input_count == 3) {
            $sv = $inputs[2];
            /*
              if ($lastInput != '1') {
              $reply = $this->display('cancel');
              Ussd::deleteLastInput($inputKey);
              } else {
              # call NIID to get response

              $sv = $inputs[2];
              $result = $this->checkPolicy($sv);
              $reply = $this->getPolicyStatusMessage($result);
              }
             *
             */
            if ($this->validPolicyInput($sv)) {
                $result = $this->checkPolicy($sv);
                $reply = $this->getPolicyStatusMessage($result);
                $action = $this->endSession();
            } else {
                Ussd::deleteLastInput($inputKey);
                $reply = $this->display("invalid_policy") . $this->display("policy");
                $action = $this->continueSession();
            }
        }

        return array('action' => $action,
            'message' => $reply);
    }

    public function display($key) {
        $options = array('intro' => "NIID Policy Checker. \n1. Motor vehicle",
            'policy' => "Enter vehicle plate number (eg: kja123bc) or policy number",
            'confirm' => "Service costs N30 \n Press 1 to confirm",
            'cancel' => "Request not confirmed & cancelled",
            'invalid_policy' => "Invalid vehicle plate or policy number\n");
        return $options[$key];
    }

    public function checkPolicy($str) {

        if (strlen(trim($str)) == 8) {
            $searchType = 'Registration number';
        } else {
            $searchType = 'Policy number';
        }
        //var_dump($searchType);
        $sv = urlencode($str);
        $st = urlencode($searchType);
        /*
          $parameters = array('st' => 'Registration number',
          'sv' => $sv);
         *
         */
        /*
          $url = "http://restapi.niid.org/NIIDRESTService/Verification";
          $headers = null;
          $result = Unirest\Request::get($url, $headers, $parameters);
         * *
         */
        $result = file_get_contents("http://restapi.niid.org/NIIDRESTService/Verification?st=$st&sv=$sv");
        //var_dump($result);
        //die();
        return json_decode($result);
    }

    public function logDb($body) {
        $parameters = array('phone' => $body["msisdn"],
            "network_id" => "1");
        $url = "https://niid.mybluemix.net/main/api";
        $headers = null;
        Unirest\Request::verifyPeer(false); // Disables SSL cert validation
        $result = Unirest\Request::get($url, $headers, $parameters);
        $data = $result->body;
        if (!$data) {
            mail("support@novajii.com"
                    , "NIID API Error on Bluemix"
                    , "We have a problem logging request to Bluemix on NIID. Please check ASAP");
        }
    }

    public function getPolicyStatusMessage($json) {
        $result = json_decode($json);
        //var_dump($result);

        $message = "NIID says\nPolicy not found. Please contact your insurance broker";
        $status = $result->{'LicenseStatus'};
        //var_dump($status);
        //die();
        switch ($status) {
            case "OK":
                $message = "NIID says: Policy Active\nVehicle Plate No:".$result->{'NewRegistrationNumber'}."\nPolicy No:" . $result->{'PolicyNumber'}
                        . "\n" . $result->{'VehicleMake'} . " " . $result->{'VehicleModel'} . "\n"
                        . "Expires:" . $result->{'ExpiryDate'};
                break;
            case "NOT FOUND":
                $message = "Policy does not exist on the NIID.";
                break;
            case "EXPIRED":
                $message = "Policy exists on NIID but has expired.";
                break;
            default:
                null;
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

            /*
              if(ctype_alpha($first) && is_numeric($second) && ctype_alpha($third))
              {
              return true;
              }
              else
              {
              return false;
              }
             */

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
