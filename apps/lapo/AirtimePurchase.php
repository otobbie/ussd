<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Lapo.php";
use Symfony\Component\Yaml\Yaml;

class AirtimePurchase extends Lapo
{

    public function purchaseAirtime($body)
    {
        $this->initValues($body);
        switch ($this->currentStep) {
            case 2:
                return $this->result("continue", $this->render("airtime_for"));
            case 3:
                if (!in_array($this->lastInputed, ['1', '2'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render("airtime_for"));
                }
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("action", "airtime_for_me");
                        return $this->aritime_menu($body);
                    case 2:
                        $this->set_value("action", "aritime_others");
                        return $this->aritime_menu($body);
                    default:
                        return $this->cancelLastAction($this->render("airtime_for"));
                }
            default:
                return $this->aritime_menu($body);
        }

    }
    public function aritime_menu($body)
    {
        $mode = $this->get_value("action");
        if ($mode == "airtime_for_me") {
            return $this->Self($body);
        } elseif ($mode == "aritime_others") {
            return $this->Others($body);
        }
    }

    public function Self($body)
    {
        switch ($this->currentStep) {
            case 3:
                $this->set_value("dest_number", $this->lastInputed);
                return $this->getAccount($body);
            case 4:
                if (!in_array($this->lastInputed, ['1', '2', '3', '4'])) {
                    return $this->cancelLastAction("You entered an invalid option.\nSelect Account\n" . $this->getAccount($body));
                } else {
                    $this->set_value("debit_account", $this->lastInputed);
                    return $this->result("continue", $this->render("tranfer_amount"));
                }
            case 5:
                $amount = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if ($amount <= 49) {
                    return $this->cancelLastAction("You entered an amount too low. \n" . $this->render("tranfer_amount"));
                } else {
                    $this->set_value("amount", $this->lastInputed);
                    $amount = $this->get_value("amount");
                    $amount_display = number_format($amount, 2);
                    $msisdn = '234' . substr($this->posted["msisdn"], -10);
                    return $this->result("continue", "You are about to purchase airtime for self\nAmount: N$amount_display\n" . $this->render("pin"));
                }
            case 6:
                $pin = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                $this->set_value("pin", $this->lastInputed);
                $validate = $this->PIN($body);
                if (strlen($pin) != 4) {
                    return $this->cancelLastAction("You entered an invalid pin.\n" . $this->render("pin"));
                } elseif ($validate == "Successful") {
                    return $this->PurchaseForMe($body);
                } else {
                    return $this->cancelLastAction("You entered an incorrect pin.\n" . $this->render("pin"));
                }
        }
    }

    public function Others($body)
    {
        switch ($this->currentStep) {
            case 3:
                return $this->result("continue", $this->render("beneficiary_number"));
            case 4:
                $beneficiary = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if (strlen($beneficiary) != 11) {
                    return $this->cancelLastAction("You entered an invalid phone number. \n" . $this->render("beneficiary_number"));
                }
                $this->set_value("dest_number", $this->lastInputed);
                return $this->result("continue", $this->render("vendor_voucher"));
            case 5:
                if (!in_array($this->lastInputed, ['1', '2', '3', '4'])) {
                    return $this->cancelLastAction("You enter an invalid option. \n" . $this->render("vendor_voucher"));
                }
                $this->set_value("dest_mno", $this->lastInputed);
                return $this->result("continue", $this->render("tranfer_amount"));
            case 6:
                $amount = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if ($amount <= 49) {
                    return $this->cancelLastAction("You entered an amount too low. \n" . $this->render("tranfer_amount"));
                }
                $this->set_value("amount", $this->lastInputed);
                return $this->getAccount($body);

            case 7:
                $this->set_value("debit_account", $this->lastInputed);
                $amount = $this->get_value("amount");
                $amount_display = number_format($amount, 2);
                $msisdn = '234' . substr($this->get_value("dest_number"), -10);
                $amount = $this->get_value("amount");
                return $this->result("continue", "You are about to purchase airtime for\nPhone Number: $msisdn, Amount: N$amount_display\n" . $this->render("pin"));
            case 8:
                $pin = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                $this->set_value("pin", $this->lastInputed);
                $validate = $this->PIN($body);
                if (strlen($pin) != 4) {
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                } elseif ($validate == "Successful") {
                    return $this->purchaseForOther($body);
                } else {
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }
        }
    }

    public function getAccount($body)
    {
        $curl = curl_init();
        $msisdn = '0' . substr($this->posted["msisdn"], -10);

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/accounts-by-phone?phone=$msisdn",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $yummy = json_decode($response);

        if ($err) {
            $this->result("end", "An error occured. Please try again after some time.");
        }

        if ($yummy->code != 0) {

            return $this->result("end", "Unable to fetch account\n" . $yummy->message);

        }

        $count = 1;
        $arr = [];
        foreach ($yummy->items as $account) {
            array_push($arr, $count . ". " . $account->ACCOUNT_NUMBER . " ");
            $this->set_value($count, $account->ACCOUNT_NUMBER);
            $count++;
        }

        $res = implode("\n", array_values($arr));
        return $this->result("continue", "Select Account:\n$res");

    }

    public function PIN($body)
    {

        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/auth?phone=$msisdn&pin=$pin",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return "an error occured\n please try again";
        } elseif ($response) {
            $request = json_decode($response);
            $description = $request->description;
            return $description;
        } else {
            return "Service temporarily unavailable\n Please try again";
        }
    }

    public function PurchaseForMe($body)
    {
        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $account_selected = $this->get_value("debit_account");
        $debit_account = $this->get_value($account_selected);
        $amount = $this->get_value("amount");
        $dest_mno = $this->posted["src"];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/airtime",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"phone\":\"$msisdn\",\"pin\":\"$pin\",\"debit_account\":\"$debit_account\",\"dest_mno\":\"$dest_mno\",\"amount\":$amount,\"dest_number\":\"$msisdn\"}",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return "An error occured\n Please try again";
        } elseif ($response) {

            $response_data = json_decode($response);

            if ($response_data->code == 0) {

                $message = $response_data->message;
                return $this->result("end", "LAPO Swift Banking\n$message");

            }

            $message = $response_data->description;
            return $this->result("end", "LAPO Swift Banking\n$message");

        } else {
            return $this->result("end", "Service temporarily unavailable\n Please try again");
        }
    }

    public function purchaseForOther($body)
    {
        $pin = $this->get_value("pin");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $mno = $this->get_value("dest_mno");
        if ($mno == "1") {
            $dest_mno = 'mtn';
        } elseif ($mno == "2") {
            $dest_mno = 'glo';
        } elseif ($mno == "3") {
            $dest_mno = 'airtel';
        } else {
            $dest_mno = '9mobile ';
        }

        $account_selected = $this->get_value("debit_account");
        $debit_account = $this->get_value($account_selected);
        $amount = $this->get_value("amount");
        $dest_number = $this->get_value("dest_number");
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/airtime",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"phone\":\"$msisdn\",\"pin\":\"$pin\",\"debit_account\":\"$debit_account\",\"dest_mno\":\"$dest_mno\",\"amount\":$amount,\"dest_number\":\"$dest_number\"}",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {

            return "An error occured\n Please try again";

        } elseif ($response) {

            $response_data = json_decode($response);

            if ($response_data->code == 0) {

                $message = $response_data->message;
                return $this->result("end", "LAPO Swift Banking\n$message");

            }

            $message = $response_data->description;
            return $this->result("end", "LAPO Swift Banking\n$message");

        } else {
            return $this->result("end", "Service temporarily unavailable\n Please try again");
        }
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }
}
