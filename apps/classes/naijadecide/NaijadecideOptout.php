<?php

include_once 'apps/UssdApplication.php';
include_once 'lib/classes/rb.php';
include_once 'apps/classes/easytaxpayer/SendSms.php';

class NaijadecideOptout {

    public static function subscriberOptout($msisdn) {
        try {
            UssdApplication::connect();
            $ret = R::findOne("novaji_vas_subscriptions", "service_id = ? and msisdn = ?", [3, $msisdn]);

            if ($ret) {
                $msg="Hello, you have successfully unsubscribed from 9JADECIDES. To subscribe again, text 9JADECIDES to 371";
                R::trash($ret);
                SendSms::sendSmsMessage($msg, $msisdn);
                return $msg;
            }
            return 'You are not registered on this service. Dial *371*4# to get started';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

}
