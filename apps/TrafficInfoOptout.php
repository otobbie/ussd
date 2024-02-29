<?php

use Unirest\Request as Unirest;
use Unirest\Request\Body as Body;

include_once 'UssdApplication.php';
include_once '_init.php';
require_once 'vendor/autoload.php';

require_once 'lib/classes/rb.php';

class TrafficInfoOptout extends UssdApplication {

	protected $action;
	private $mobile;
	private $username = 'support@novajii.com';
	private $password = 'rmCSL675588_';

	# DB params
	/*
	 * @var string
	 */
	private $user = 'novaji_introserve';
	private $host = '54.36.101.235';
	private $dbpass = 'Amore123_';
	private $database = 'novaji_introserve';
	private $network;

	public function getResponse($body) {
//		$this->connect();
		UssdApplication::connect();

		$action = $this->continueSession();

		#mobie number
		$this->mobile = $body['msisdn'];

		$userKey = Ussd::getUserKey($body['msisdn']);
		$inputKey = Ussd::getInputKey($body['msisdn']);
		$inputs = Ussd::getUserInputs($inputKey);
		$lastInput = $body['content'];
		$this->network = $body['src'];

		# main application service flow
		$input_count = count($inputs);

		# step 1 - opt-out
		if ($input_count == 1) {
			$this->removeSubscriber($this->mobile);
			$this->sendSMSMessage();
			$reply = $this->display('intro');
			$action = $this->endSession();
		}

		return array(
			'action' => $action,
			'message' => $reply
			)
		;
	}

	protected function sendSMSMessage() {
		file_get_contents("http://sms.novajii.com/send?"
			. "username=oracle&"
			. "password=oracle&"
			. "to=" . $this->mobile . "&"
			. "from=371&"
			. "content=" . urlencode($this->display('intro')) . "&"
			. "dlr=yes&"
			. "dlr-url=http://portal.novajii.com/smsgw/dlr&dlr-level=3&dlr-method=GET"
		);
	}

//	protected function connect() {
//		return R::setup('mysql:host=' . $this->host . ';dbname=' . $this->database, $this->user, $this->dbpass);
//	}

	protected function removeSubscriber($mobile) {
		try {
			$ret = R::findOne('novaji_vas_subscriptions', 'msisdn = ?', [$mobile]);
			$res = R::findOne('requests', 'msisdn = ?', [$mobile]);
			R::trash($ret);
			R::trash($res);
			
			// Add subscriber to opt-out table
			$query = "INSERT INTO novaji_vas_opt_out(msisdn, service_id, network) VALUES(?,?,?)";
			R::exec($query, [$mobile, 1, $this->network]);
			
		} catch (Exception $e) {
			$e->getMessage();
		}
	}

	public function display($key) {
		$options = array(
			'intro' => "Dear customer, you have successfully unsubscribed from Traffic Info. To subscribe again, Dial *371#"
		);

		return $options[$key];
	}

}
