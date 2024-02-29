<?php

include_once 'UssdApplication.php';
include_once 'init.php';
require 'vendor/autoload.php';

class FlashSwitch extends UssdApplication {

    public function getResponse($body) {
        //global $redis;
        $action = $this->switchSession();
        $reply = '*371#';
        return ['action' => $action,
            'srcServiceCode' => '*371',
            'destServiceCode' => '*371',
            'message' => $reply];
    }

}
