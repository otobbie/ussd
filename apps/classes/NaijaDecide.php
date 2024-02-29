<?php

include_once 'UssdApplication.php';
include_once 'apps/classes/naijadecide/Questionaire.php';
include_once 'apps/classes/naijadecide/NaijadecideOptout.php';
include_once 'apps/classes/naijadecide/Billing.php';

require_once 'lib/classes/rb.php';

use Symfony\Component\Yaml\Yaml;

class NaijaDecide extends UssdApplication {

    public $arr = [];
    public $msisdn;

    public function getResponse($body) {
        UssdApplication::connect();

        $this->initValues($body);

        $val = str_replace(['*', '#'], ['/', ''], $body['content']);
        $var = explode('/', $val);
        array_shift($var);

        $this->msisdn = '234' . substr($this->getMsisdn(), -10);

        switch ($this->currentStep) {
            case 1:
                return $this->result('continue', "Welcome to 9JADECIDES\n1. Register\n2. Vote\n3. Opt-out");
            default:
                $this->arr = array(
                    'currentStep' => $this->currentStep,
                    'lastInputed' => $this->lastInputed,
                    'userInputs' => $this->userInputs,
                    'msisdn' => '0' . substr($this->getMsisdn(), -10)
                );

                $this->exec();
        }

        // if (!$this->registerStatus($msisdn)) {
        //     switch ($this->currentStep) {
        //         case 1:
        //             return $this->result('continue', "Welcome to 9jaDECIDES service. You are not subscribed. \nPress 1 to subscribe");

        //         case 2:
        //             if ($this->userInputs[1] == '1') {
        //                 $this->registered($msisdn);
        //                 return $this->result("end", "You have successfully registered on 9jaDECIDES. Dial *371*4# to get question for the week");
        //             }

        //             return $this->result("end", "Invalid key pressed. Dial *371*4# to register");
        //     }
        // } else {
        //     switch ($this->currentStep) {
        //         case 1:
        //             if ((string)$var[2] == '0') {
        //                 return $this->result('end', NaijadecideOptout::subscriberOptout($msisdn));
        //             }
        //             $res = Questionaire::getQuestion($msisdn);

        //             $result = $res['code'] == 0 ? 'continue' : 'end';
        //             return $this->result($result, "Welcome to 9jaDECIDES\n\nQuestion for the week\n" . $res['message']);
        //         default:
        //             return $this->result('end', Questionaire::postResponse($this->userInputs[1], $msisdn));
        //     }
        // }
    }

    public function exec() {
        switch ($this->userInputs[1]) {
            case 1:
                $this->processRegistration();
                break;
            case 2:
                $this->vote();
                return;
            case 3:
                return;
            default:
                return $this->result('end', "Invalid input passed");
        }
    }

    public function processRegistration() {
        switch ($this->currentStep) {
            case 2:
                if($this->registerStatus()) {
                    $this->result('continue', "Enter 1 to register to this service. Service cost N50/7 days");
                }
                break;
            case 3:
                if($this->userInputs[3] == '1') {
                    $this->result('continue', "Enter 2 to confirm. Service cost N50/7 days");
                } else {
                    $this->result('end', "Invalid confirmation entry");
                }
            case 4:
                if ($this->userInputs[4] == '2') {
                    $this->register($this->register($this->msisdn));
                    return $this->result("end", "You have successfully registered on 9JADECIDES. Dial *371*4# to get question for the week");
                }
        }
    }

    public function vote() {
        switch ($this->currentStep) {
            case 2:
                if ($this->isMsisdnRegistered($this->msisdn)) {
                    $res = Questionaire::getQuestion($this->msisdn);
                    $result = $res['code'] == 0 ? 'continue' : 'end';

                    return $this->result($result, "Welcome to 9JADECIDES\n\nQuestion for the week\n" . $res['message']);
                }
            case 3:
                if (!empty($this->userInputs[3])) {
                    return $this->result('end', Questionaire::postResponse($this->userInputs[3], $msisdn));
                }
        }
    }

    public function render($key) {
		$arr = Yaml::parse(file_get_contents("config/naijadecide.yml"));
		return $arr['pages'][$key];
	}

    public function register($msisdn) {
        $ret = $this->isMsisdnRegistered($msisdn);

        if (!$ret) {
            // register subscriber and bill
            $msg = Billing::bill($msisdn);
            // Check for billing status
            $res = json_decode($msg, true);

            $query = "INSERT INTO novaji_vas_subscriptions("
                . "msisdn, subscriptiondate, expiredate, notificationdate, service_id, mno) "
                . "VALUES (?,?,?,?,?,?)";

            if ($query) {
                $this->sendMessage("Hello, your subscription for the 9JADECIDES service was successful. This service costs N50/7 days. For more, text HELP 9JADECIDES to 371", $msisdn);
            }

            try {
                R::exec($query, [$msisdn, date('Y-m-d h:i:s'), date('Y-m-d h:i:s'), date('Y-m-d h:i:s'), 3, 'etisalat']);
            } catch (Exception $ex) {
                log::error($ex->getMessage());
            }
        }

        return $ret;
    }

    public function isMsisdnRegistered($msisdn) {
        $ret = R::exec('select * from  novaji_vas_subscriptions where msisdn = '. $msisdn .' and service_id = 3');
        return !empty($ret) ? true : false;
    }

    public function sendMessage($params, $msisdn) {
        file_get_contents("http://sms.novajii.com/send?"
            . "username=oracle&"
            . "password=oracle&"
            . "to=" . $msisdn . "&"
            . "from=371&"
            . "content=" . urlencode($params) . "&"
            . "dlr=yes&"
            . "dlr-url=http://portal.novajii.com/smsgw/dlr&dlr-level=3&dlr-method=GET"
        );
    }
}
