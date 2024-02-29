<?php

// include_once 'apps/Easytax.php';

class SendSms {

    public static function sendSmsMessage($message, $msisdn) {
        file_get_contents("http://sms.novajii.com/send?"
            . "username=oracle&"
            . "password=oracle&"
            . "to=" . $msisdn . "&"
            . "from=371&"
            . "content=" . urlencode($message) . "&"
            . "dlr=yes&"
            . "dlr-url=http://portal.novajii.com/smsgw/dlr&dlr-level=3&dlr-method=GET"
        );
    }
}
