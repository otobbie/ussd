<?php

include_once 'UssdApplication.php';
#include_once 'NaijaDecide.php';
include_once 'apps/classes/naijadecide/Questionaire.php';
require_once 'lib/classes/rb.php';

use Symfony\Component\Yaml\Yaml;

class NaijaDecideCampaign extends UssdApplication {

    public $arr = [];
    public $msisdn;
    public $serviceId = 3;
    public $body;

    public function getResponse($body) {
        $this->initValues($body);
        $this->msisdn = $body['msisdn'];
        $this->body = $body;
        #$message = "Will you vote for a presidential candidate who is endored by America or Europe? Dial *371*4# to share your opinion";
        #return $this->result('end', $message);
        return $this->poll();
    }

    public function poll() {
        #return $this->result("continue", "Share your opinion\n" . $this->currentStep);
        switch ($this->currentStep) {
            
            case 1:
                $res = Questionaire::getQuestion($this->msisdn);
                #$result = $res['code'] == 0 ? 'continue' : 'end';
                return $this->result("continue", "Share your opinion\n" . $res['message']);
            case 2:
                # register them if they voted
                $msisdn = $this->msisdn;
                #$app = new NaijaDecide;
                $service_code = "9JADEC50";
                $sub_result = json_decode(file_get_contents("http://notification.novajii.com/web/etisalat/ussd/subscribe/no?"
                                . "msisdn={$msisdn}&service_code={$service_code}"));                            
                return $this->result('end', Questionaire::postResponse($this->body['content'], $this->msisdn));
        }
    }

}
