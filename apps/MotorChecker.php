<?php

include_once 'PolicyChecker.php';
include_once 'init.php';
require 'vendor/autoload.php';

class MotorChecker extends PolicyChecker {

    public function getResponse($body) {
        //global $redis;
        $action = $this->continueSession();
        $reply = null;
        $userKey = Ussd::getUserKey($body['msisdn']);
        $inputKey = Ussd::getInputKey($body['msisdn']);
        $inputs = Ussd::getUserInputs($inputKey);
        $lastInput = $body['content'];
        # main application service flow
        $input_count = count($inputs);
        # Step 1: Welcome
        if ($input_count == 1) {

            $reply = $this->display('policy');
            $action = $this->continueSession();
        }
        # step 2: Check Policy input before we confirm
        if ($input_count == 2) {
            $sv = $inputs[1];
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
        # step 3:  
        /*
          if ($input_count == 3) {
          if ($lastInput != '1') {
          $reply = $this->display('cancel');
          Ussd::deleteLastInput($inputKey);
          } else {
          # call NIID to get response

          $sv = $inputs[1];
          $result = $this->checkPolicy($sv);
          $reply = $this->getPolicyStatusMessage($result);
          }
          $action = $this->endSession();
          }
         * 
         */
        return array('action' => $action, 'message' => $reply);
    }

}
