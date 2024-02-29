<?php

include_once 'UssdApplication.php';
include_once 'classes/guineainsurance/Personal.php';
include_once 'classes/guineainsurance/Fire.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Description of Insurance Demo
 *
 * @author Chuks
 */
class InsuranceDemo extends UssdApplication {

    public function getResponse($body) {
        $this->initValues($body);

        switch ($this->currentStep) {
            case 1:
                return $this->result("continue", $this->render("enter_reg_chasis"));
            case 2:
                return $this->result("continue", $this->render("enter_engine_number"));
            case 3:
                return $this->result("continue", $this->render("enter_vehicle_make"));
            case 4:
                return $this->result("continue", $this->render("confirm"));
            case 5:
                return $this->result("continue", $this->render("enter_pin"));
            case 6:
                return $this->result("end", $this->render("result"));

            /*
              default:
              return $this->exec([
              'currentStep' => $this->currentStep,
              'lastInputed' => $this->lastInputed,
              'userInputs' => $this->userInputs,
              ]);
             * 
             */
        }
    }

    /*

      public function exec($arr) {
      switch ($this->userInputs[1]) {
      case 1:
      $ret = new Personal;
      return $ret->init($arr);
      case 2:
      $ret = new Fire;
      return $ret->init($arr);
      }
      }
     * 
     */

    public function render($key) {
        $arr = Yaml::parse(file_get_contents("config/insurance_demo/demo.yml"));
        return $arr['pages'][$key];
    }

}
