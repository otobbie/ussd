<?php

include_once 'UssdApplication.php';

use Symfony\Component\Yaml\Yaml;

class ARMLife extends UssdApplication {

    public function getResponse($body) {
        $this->initValues($body);
        switch ($this->currentStep) {
            case 1:
                return $this->result('continue', $this->render('welcome'));
            case 2:
                if ($this->expectedInput($this->lastInputed, [1, 2, 3])) {
                    switch ($this->lastInputed) {
                        case 1:
                            return $this->result('continue', $this->render('provide_age'));
                        case 2:
                            return $this->cancelLastAction($this->render('welcome'));
                        case 3:
                            return $this->cancelLastAction($this->render('welcome'));
                    }
                }
                return $this->cancelLastAction($this->render('welcome'));
            case 3:
                if ($this->validAge($this->lastInputed)) {
                    return $this->result('continue', $this->render('provide_name'));
                }
                return $this->cancelLastAction($this->render('provide_age'));
            case 4:
                return $this->result('continue', $this->render('provide_children'));
            case 5:
                if ($this->integerEntered($this->lastInputed)) {
                    return $this->result('continue', $this->render('annual_school_fees'));
                }
                return $this->cancelLastAction($this->render('provide_children'));
            case 6:
                if ($this->numericEntered($this->lastInputed)) {
                    return $this->result('continue', $this->render('insured_years'));
                }
                return $this->cancelLastAction($this->render('annual_school_fees'));
            case 7:
                if ($this->integerEntered($this->lastInputed)) {
                    return $this->result('continue', $this->render('select_bank1'));
                }
                return $this->cancelLastAction($this->render('insured_years'));
            case 8:
                if ($this->validBank()) {
                    return $this->summary();
                }
                return $this->cancelLastAction($this->render('select_bank1'));
            case 9:
                return $this->registerEduPlan();
            default:
                return $this->registerEduPlan();
        }
    }

    private function registerEduPlan() {
        return $this->result('end', $this->render('confirm'));
    }

    private function summary() {
        $age = number_format($this->userInputs[2]);
        $name = ucwords($this->userInputs[3]);
        $children = number_format($this->userInputs[4]);
        $fees = number_format($this->userInputs[5], 2);
        $duration = number_format($this->userInputs[6]);
        return $this->result('continue', "Name : $name\n"
                        . "Age : $age Yr(s)\n"
                        . "Children: $children\n"
                        . "Annual Fees : $fees\n"
                        . "Policy Duration: $duration Yr(s)\n"
                        . "Bank : GTBank\n"
                        . "Press any key to confirm");
    }

    private function validAge($age) {
        if ($this->integerEntered($age)) {
            $val = (int) $age;
            return (($val >= 18) && ($val <= 55));
        }
        return FALSE;
    }

    private function validBank() {
        return $this->expectedInput($this->lastInputed, [1, 2, 3, 4, 5]);
    }

    public function render($key) {
        $arr = Yaml::parse(file_get_contents('config/arm-life.yml'));
        return $arr[$key];
    }

}
