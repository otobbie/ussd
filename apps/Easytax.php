<?php

include_once 'UssdApplication.php';
include_once 'classes/easytaxpayer/TaxCalculator.php';
include_once 'classes/easytaxpayer/EasytaxOptout.php';
include_once 'classes/easytaxpayer/Register.php';

use Symfony\Component\Yaml\Yaml;

class Easytax extends UssdApplication {

    public $lang = 1;
    public $arr = [];

    public function getResponse($body) {
        $this->initValues($body);

        $val = str_replace(['*', '#'], ['/', ''], $this->userInputs[0]);
        $var = explode('/', $val);
        array_shift($var);

        $this->arr = [
            'currentStep' => $this->currentStep,
            'lastInputed' => $this->lastInputed,
            'userInputs' => $this->userInputs,
            'msisdn' => '234' . substr($this->getMsisdn(), -10),
            'amount' => $var[2]
        ];

        switch ($this->currentStep) {
            case 1:
                if (isset($var[1]) && isset($var[2])) {
                    if ($var[2] == 0) {
                        return $this->result('end', EasytaxOptout::subscriberOptout('234' . substr($this->getMsisdn(), -10)));
                    }
                    # Calculate Tax
                    $cal = new TaxCalculator();
                    return $cal->calculateTax($this->arr);
                } else {
                    Register::registerMsisdn('234' . substr($this->getMsisdn(), -10));
                    $items = $this->render('broadcast');
                    return $this->result('end', "Tax info, news  & updates:\n" . $items[array_rand($items)]);
                }
            default:
                $cal = new TaxCalculator();
                return $cal->calculateTax($this->arr);
        }
    }

    /**
     *
     * @param array $key
     * @return array
     */
    public function render($key) {
        $arr = Yaml::parse(file_get_contents("config/easytax_vas_language1.yml"));
        return $arr['pages'][$key];
    }

}
