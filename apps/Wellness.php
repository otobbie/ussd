<?php

use Symfony\Component\Yaml\Yaml;
include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
include_once 'Mcash.php';
include_once 'Rave.php';
require_once 'lib/classes/rb.php';

/**
 * Description of Wellness Living
 *]
 * @author Emmanuel
 */
class Wellness extends UssdApplication
{
    public $msisdn;
    private $merchant_code = "77000717";
    public $Policy_Number;
    public function getResponse($body)
    {
        $this->initValues($body);
        switch ($this->currentStep) {
            case 1:
                return $this->result("continue", $this->render("welcome"));
            case 2:
                return $this->WellnessScore();
                default:
                return $this->WellnessScore();
        }


    }
    public function WellnessScore(){
        

    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("apps/config/wellness/config.yml"));
        return $arr['pages'][$key];
    }

}