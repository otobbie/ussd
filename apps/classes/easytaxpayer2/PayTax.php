<?php

include_once 'apps/Easytax2.php';
include_once 'Accessapi.php';

/**
 * Description of PayTax
 *
 * @author Chuks
 */
class PayTax extends Easytax2 {

    private $url = 'http://easytaxpayer.com.ng/web/api/getpayerid';

    /**
     * Pay Tax
     *
     * @param type $arr
     * @return type
     */
    public function payTax($arr) {
        switch ($arr['currentStep']) {
            case 4:
                return $this->result('continue', $this->render('tax_payment_options'));
            default:
                switch ($arr['userInputs'][4]) {
                    case 1:
                        return $this->withholdingTax($arr);
                    case 2:
                        return;
                }
                return $this->cancelLastAction($this->render('tax_payment_options'));
        }
    }

    public function withholdingTax($arr) {
        switch ($arr['currentStep']) {
            case 5:
                return $this->result('continue', $this->render('witholding_tax_options'));
            case 6:
                if (in_array($arr['lastInputed'], [1, 2, 3, 4])) {
                    return $this->result('continue', $this->render('enter_tax_year'));
                }
                return $this->cancelLastAction($this->render('tax_payment_options'));
            case 7:
                if ($this->validTaxYear($arr['lastInputed'])) {
                    return $this->result('continue', $this->render('payment_amount'));
                }
                return $this->cancelLastAction($this->display('enter_tax_year'));
            case 8:
                if ($arr['userInputs'][7] > 0) {
                    $result = Accessapi::makeApiCall($this->url, ["phone" => $arr['msisdn']]);
                    $ret = json_decode($result, true);

                    return $this->result("continue", "Payment Summary\n"
                            //. "Tax: " . $this->render_2(['pages']['witholding_tax_options'][$arr['userInputs'][5]]) . "\n"
                            . "Mobile: " . $arr['msisdn'] . "\n"
                            . "Amount: " . number_format($arr['userInputs'][7], 2) . "\n"
                            . "State: " . $ret['result']['state'] . "\n"
                            . "PayId: " . $ret['result']['payer_id'] . "\n\n"
                            . "Press 1 to confirm");
                }
                return $this->cancelLastAction($this->display('enter_tax_year'));
            case 9:
                return $this->result("end", "EasyTax\nPayment successful");
        }
    }

    private function validTaxYear($yr) {
        $currYr = (int) date('Y');

        try {
            $int = (int) $yr;
            return ($int <= $currYr) && ($int >= ($currYr - 50));
        } catch (Exception $e) {
            return false;
        }
    }

}
