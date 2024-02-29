<?php

include_once 'UssdApplication.php';
include_once 'lib/classes/rb.php';
include_once 'classes/easytaxpayer/SendSms.php';

class EasytaxOptout {

    public static function subscriberOptout($msisdn) {
        try {
            UssdApplication::connect();
            $ret = R::findOne("novaji_vas_subscriptions", "service_id = ? and msisdn = ?", [3, $msisdn]); 

            if ($ret) {
                R::trash($ret);
                SendSms::sendSmsMessage("You have successfully unsubscribed from easytaxpayer. To get tax info, news and updates dial *371*5#", $msisdn);
                return "You have successfully unsubscribed from easytaxpayer. To get tax info, news and updates dial *371*5#";
            }
            return 'You\'re not registered on Easytaxpayer. Dial *371*5# to get started';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

}
