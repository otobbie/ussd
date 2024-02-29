<?php

include_once 'apps/Easytax.php';

/**
 * Description of TaxPayer
 *
 * @author Chuks
 */
class TaxPayerInfo extends Easytax {

	//put your code here

	private $url = 'http://easytaxpayer.com/web/api/getpayerid';

	public function getPayerInfo($arr) {
		switch ($arr['currentStep']) {
			case 4:
				$result = Api::makeApiCall($this->url, ["phone" => $arr['msisdn']]);
				$ret = json_decode($result, true);

				if ($ret['result']['code'] == 200) {
					return $this->result("end", "Payer Information.\n"
							. "Name: " . $ret['result']['name'] . "\n"
							. "Mobile: " . $arr['msisdn'] . "\n"
							. "State: " . $ret['result']['state'] . "\n"
							. "PayerID: " . $ret['result']['payer_id']);
				}

				return $this->result('end', 'PayerID not found for this subscriber');
		}
	}

}
