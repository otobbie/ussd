<?php

include_once 'UssdApplication.php';
include_once 'apps/classes/naijadecide/Questionaire.php';
include_once 'apps/classes/naijadecide/NaijadecideOptout.php';
include_once 'apps/classes/naijadecide/Billing.php';

require_once 'lib/classes/rb.php';

use Symfony\Component\Yaml\Yaml;

class NaijaDecide extends UssdApplication
{

    public $arr = [];
    public $msisdn;
    public $serviceId = 3;

    public function getResponse($body)
    {
        UssdApplication::connect();

        $this->initValues($body);

        $val = str_replace(['*', '#'], ['/', ''], $body['content']);
        $var = explode('/', $val);
        array_shift($var);

        $this->msisdn = '234' . substr($this->getMsisdn(), -10);

        switch ($this->currentStep) {
            case 1:
                return $this->result('continue', "Welcome to 9JADECIDES\n1. Subscribe\n2. Participate in Opinion Polls\n3. Opt-out");
            default:
                return $this->exec();
        }
    }

    public function exec()
    {
        switch ($this->userInputs[1]) {
            case 1:
                return $this->processRegistration();
            case 2:
                return $this->vote();
            case 3:
                return $this->result('end', NaijadecideOptout::subscriberOptout($this->msisdn));
            default:
                return $this->result('end', "Invalid input passed");
        }
    }



    // public function SubscriptionPhase()
    // {
    //     switch ($this->currentStep) {
    //         case 1:
    //             if ($this->expectedInput($this->lastInputed, [2])) { }
    //     }
    // }

    // public function auto_renew()
    // {
    //     switch ($this->expectedInput($this->lastInputed, [1])) {
    //         case 1:
    //             return $this->processRegistration();
    //         case 2:
    //             return $this->vote();
    //         default:
    //             return $this->result('end', "Invalid input passed");
    //     }
    // }

    // public function one_off()
    // {
    //     switch ($this->currentStep) {

    //         case 1:
    //             if ($this->expectedInput($this->lastInputed, [1])) {

    //                 return $this->processRegistration();
    //             }

    //         case 2:
    //             return $this->vote();
    //         default:
    //             return $this->result('end', "Invalid input passed");
    //     }
    // }

    public function processRegistration()
    {
        switch ($this->currentStep) {

            case 2:
                if ($this->expectedInput($this->lastInputed, [1])) {
                    if ($this->isMsisdnRegistered($this->msisdn)) {
                        return $this->result('end', "You are already subscribed to this service. To participate in the poll for the week, dial *371*4# and Enter 2");
                    }
                    return $this->result('continue', "Select 1 for auto-renewal or select 2 for one-off purchase");
                } else {
                    return $this->result('end', "Invalid confirmation entry");
                }
        }

        switch ($this->currentStep) {
            case 3:
                if ($this->expectedInput($this->lastInputed, [1])) {
                    $service = $this->getService();
                    $result = $this->register($this->msisdn);
                    if ($result->code == 200) {
                        # send daily content for new subscriber
                        $this->pushDailyContent();
                        return $this->result("end", "You have successfully registered on 9JADECIDES on Auto-renewal. To participate in the poll for the week, dial *371*4# and Enter 2");
                    }
                    if ($result->code == 202) {
                        return $this->result("end", $service->insufficient_balance);
                    }
                    return $this->result("end", $result->contextMsg);
                } elseif ($this->expectedInput($this->lastInputed, [2])) {

                    $service = $this->getService();
                    $result = $this->register($this->msisdn);
                    if ($result->code == 200) {
                        # send daily content for new subscriber
                        $this->pushDailyContent();
                        return $this->result("end", "You have successfully registered on 9JADECIDES on One-Off Purchase. To participate in the poll for the week, dial *371*4# and Enter 2");
                    }
                    if ($result->code == 202) {
                        return $this->result("end", $service->insufficient_balance);
                    }
                    return $this->result("end", $result->contextMsg);
                }
                return $this->cancelLastAction('Invalid input please enter 2 to confirm');
                #return $this->result("continue", 'Invalid input please dial *371*4#');
        }
    }

    public function pushDailyContent()
    {
        $receiver = $this->msisdn;
        file_get_contents("http://notification.novajii.com/web/naijadecides/pushinfo/$receiver");
    }

    public function vote()
    {
        switch ($this->currentStep) {
            case 2:
                if ($this->isMsisdnRegistered($this->msisdn)) {
                    $res = Questionaire::getQuestion($this->msisdn);
                    $result = $res['code'] == 0 ? 'continue' : 'end';

                    return $this->result($result, "Welcome to 9JADECIDES\n\nQuestion for the week\n" . $res['message']);
                }
                return $this->result('end', "You are not subscribed to this service. Please dial *371*4# and Enter 1 to subscribe");

            case 3:
                // return $this->result('end', $this->userInputs[2]);
                return $this->result('end', Questionaire::postResponse($this->userInputs[2], $this->msisdn));
        }
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("config/naijadecide.yml"));
        return $arr['pages'][$key];
    }

    public function register($msisdn)
    {

        ## first we bill and if successful we subscribe
        ## http://notification.novajii.com/web/etisalat/ussd/bill/stage?msisdn=08094196929&service_code=9JADEC50&authorization=503AF4D1A7D049740198C0679FA58B88949F9F9B7A9AA3D66670F85CBF76B67B&key=deb3c7c967484adc94ad8852dcd80b22
        #
        #then subscribe
        # http://notification.novajii.com/web/etisalat/ussd/subscribe?msisdn=08094196929&service_code=9JADEC50
        # 
        $service = $this->getService();
        $service_code = $service->service_code;
        # change to live or stage
        $bill_url = "http://notification.novajii.com/web/etisalat/ussd/bill/live?"
            . "msisdn=$msisdn&service_code=$service_code";
        $bill_result = json_decode(file_get_contents($bill_url));
        if ($bill_result->code == 200) {
            $sub_result = json_decode(file_get_contents("http://notification.novajii.com/web/etisalat/ussd/subscribe?"
                . "msisdn=$msisdn&service_code=$service_code"));
        }
        return $bill_result;
    }

    public function isMsisdnRegistered($msisdn)
    {
        // $ret = R::exec('select * from  novaji_vas_subscriptions where msisdn = '. $msisdn .' and service_id = 3');
        // return !empty($ret) ? true : false;

        $ret = R::findOne("novaji_vas_subscriptions", "service_id = ? and msisdn = ?", [$this->serviceId, $msisdn]);
        return !empty($ret) ? true : false;
    }

    public function sendMessage($params, $msisdn)
    {
        file_get_contents(
            "http://sms.novajii.com/send?"
                . "username=oracle&"
                . "password=oracle&"
                . "to=" . $msisdn . "&"
                . "from=371&"
                . "content=" . urlencode($params) . "&"
                . "dlr=yes&"
                . "dlr-url=http://portal.novajii.com/smsgw/dlr&dlr-level=3&dlr-method=GET"
        );
    }

    public function getService()
    {
        $ret = R::findOne("novaji_vas_services", "id = ?", [$this->serviceId]);
        return $ret;
    }
}
