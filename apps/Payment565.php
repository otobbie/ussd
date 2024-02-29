<?php

include_once 'UssdApplication.php';
include_once 'Payment.php';

class Payment565 extends UssdApplication {

    public function getResponse($body) {
        $payment = new apps\Payment();
        $service_code = 'easytaxpayer';
        $val = str_replace(['*', '#'], ['/', ''], $body['content']);
        $vars = explode('/', $val);
        $amt = $vars[3];
        $telco = ($body['src'] === 'etisalat') ? '9mobile' : $body['src'];
        $msg = $payment->pushPayment2($body, $amt, $service_code, 'account:novaji');
        return array('action' => 'End',
            'message' => $msg);
    }

}
