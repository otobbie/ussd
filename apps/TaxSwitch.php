<?php

include_once 'UssdApplication.php';
include_once 'init.php';
require 'vendor/autoload.php';

class TaxSwitch extends UssdApplication {

    public function getResponse($body) {
        //global $redis;
        $action = $this->switchSession();
        /*
        $reply = '*371*19#';
        return ['action' => $action,
            'srcServiceCode' => '*371',
            'destServiceCode' => '*371',
            'message' => $reply];
         * 
         */
         
        
        $reply = '*565#';
        return ['action' => $action,
            'srcServiceCode' => '371',
            'destServiceCode' => '*565',
            'message' => $reply];
         
         
    }
       

}
