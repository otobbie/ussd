<?php

include_once 'UssdApplication.php';
include_once 'lib/classes/rb.php';
include_once 'classes/easytaxpayer/SendSms.php';

/**
 * Description of Register
 *
 * @author Chuks
 */
class Register {

	public static function registerMsisdn($msisdn) {
		try {
			$date = date("Y-m-d H:i:s");

			UssdApplication::connect();
            $ret = R::findOne("novaji_vas_subscriptions", "service_id = ? and msisdn = ?", [3, $msisdn]); 

			if(!$ret) {
				// Register
				$sql = "INSERT INTO novaji_vas_subscriptions("
		            . "msisdn, subscriptiondate, expiredate, notificationdate, service_id, mno) "
		            . "VALUES (?,?,?,?,?,?)";

				SendSms::sendSmsMessage("Thank you for using easytaxpayer. Dial 371*5# for latest tax info, news &  updates. Dial 371*5*Amount# to calaculate your tax. N50/mo", $msisdn);

				return R::exec($sql, [$msisdn, $date, date('Y-m-d', strtotime("+7 days")), date('Y-m-d', strtotime("+5 days")), 3, "etisalat"]);
			}
			return false;
		} catch(Exception $e) {
			return $e->getMessage();
		}
	}

}
