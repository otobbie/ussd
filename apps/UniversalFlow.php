<?php

use Symfony\Component\Yaml\Yaml;

include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
include_once 'Mcash.php';
include_once 'classes/guineainsurance/Personal.php';
include_once 'classes/guineainsurance/Fire.php';


/**
 *  Description Universal Insurance
 *
 * @author Emmanuel
 */


class UniversalFlow extends UssdApplication
{
    public $msisdn;
    private $merchant_code = "77000717";
    public function getResponse($body)
    {
        $this->initValues($body);
        switch ($this->currentStep) {
            case 1:
                # Option Message (1 New Policy, 2 Policy Renewal)
                return $this->result("continue", $this->render("welcome"));
            case 2:
            $last = $this->lastInputed;
                if ($last == "1") {
                 $msg = "Universal Insurance Info\nDial $bank_array[10]$bank_reference#\nTo Complete Payement";
                return $this->sendPaymentSms($msg);
                }
                
            default:
                return $this->flow($body);
        }
    }
    


    public function sendPaymentSms($msg) {
        $msisdn = $this->posted["msisdn"];
        $message = urlencode($msg);
        file_get_contents("https://novajii.com/web/bulksms/all/send?network=etisalat&msg=$message&src=371&msisdn=$msisdn");
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("config/universal/config.yml"));
        return $arr['pages'][$key];
    }
}
