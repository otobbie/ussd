<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Lapo.php";
use Symfony\Component\Yaml\Yaml;

class TransferFunds extends Lapo
{

    public function transferingFunds($body)
    {
        $this->initValues($body);
        switch ($this->currentStep) {
            case 2:
                return $this->getAccount($body);
            case 3:
                $this->set_value("debit_account", $this->lastInputed);
                if (!in_array($this->lastInputed, ['1', '2', '3', '4'])) {
                    return $this->cancelLastAction("You entered an invalid account option. \n" . $this->getAccount($body));
                } else {
                    return $this->result("continue", $this->render("credit_account"));
                }
            case 4:
                $beneficiary = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                $this->set_value("credit_account", $this->lastInputed);
                if (strlen($beneficiary) != 10) {
                    return $this->cancelLastAction("The account number you entered is not valid.\n" . $this->render("credit_account"));
                } else {
                    return $this->result("continue", $this->render("tranfer_amount"));
                }
            case 5:
                $this->set_value("amount", $this->lastInputed);
                $account_check = $this->CheckBank($body);
                if ($account_check == "Successful") {
                    return $this->ValidatAccount($body);
                } else {
                    return $this->result("continue", $this->render("banks"));
                }
            case 6:
                $this->set_value("pin", $this->lastInputed);
                $validate = $this->PIN($body);
                if (strlen($this->lastInputed) == 4 && $validate == "Successful") {
                    return $this->QueryTransferToLapo($body);
                } else {
                    $this->set_value("banks", $this->lastInputed);
                    return $this->SelectBank();
                }
            case 7:
                $this->set_value("bank_code", $this->lastInputed);
                $institutionCode = $this->getInstitutionCode($this->lastInputed);
                $this->set_value("institutionCode", $institutionCode);
                return $this->QueryName($institutionCode);
            case 8:
                $this->set_value("pin", $this->lastInputed);
                $validate = $this->PIN($body);
                if ($validate == "Successful") {
                    return $this->QueryTransfer($body);
                } else {
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }

        }

    }

    public function CheckBank($body)
    {
        $credit_account = $this->get_value("credit_account");
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/account?account_number=$credit_account",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
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

    public function SelectBank()
    {

        $banks = $this->get_value("banks");
        if ($banks == 1) {
            return $this->result("continue", $this->render("bank_one"));
        } elseif ($banks == 2) {
            return $this->result("continue", $this->render("bank_two"));
        } elseif ($banks == 3) {
            return $this->result("continue", $this->render("bank_three"));
        } elseif ($banks == 4) {
            return $this->result("continue", $this->render("bank_four"));
        } else {
            return $this->cancelLastAction("You entered an invalid option.\n" . $this->render("banks"));
        }
    }

    public function getInstitutionCode($body)
    {
        $options = $this->get_value("bank_code");
        $banks = $this->get_value("banks");
        if ($banks == 1) {
            return $this->Bank_One($options);
        } elseif ($banks == 2) {
            return $this->Bank_Two($options);
        } elseif ($banks == 3) {
            return $this->Bank_Three($options);
        } elseif ($banks == 4) {
            return $this->Bank_Four($options);
        } else {
            return $this->cancelLastAction("You entered an invalid option.\n" . $this->render("banks"));
        }
    }

    public function Bank_One($option)
    {
        $bank_codes =
        array(
            "000014" => '1',
            "000009" => '2',
            "000005" => '3',
            "000010" => '4',
            "000019" => '5',
            "000007" => '6',
            "000016" => '7',
            "000003" => '8',
        );
        if (array_search($option, $bank_codes)) {
            return array_search($option, $bank_codes);
        } else {
            return 'false';
        }
    }

    public function Bank_Two($option)
    {
        $bank_codes =
        array(
            "000013" => '1',
            "000020" => '2',
            "000006" => '3',
            "000002" => '4',
            "090171" => '5',
            "090274" => '6',
            "000023" => '7',
        );
        if (array_search($option, $bank_codes)) {
            return array_search($option, $bank_codes);
        } else {
            return 'false';
        }
    }

    public function Bank_Three($option)
    {
        $bank_codes =
        array(
            "090006" => '1',
            "000012" => '2',
            "000021" => '3',
            "000001" => '4',
            "000022" => '5',
            "000018" => '6',
            "000004" => '7',
            "000011" => '8',

        );
        if (array_search($option, $bank_codes)) {
            return array_search($option, $bank_codes);
        } else {
            return 'false';
        }
    }

    public function Bank_Four($option)
    {
        $bank_codes =
        array(
            "000017" => '1',
            "000015" => '2',
        );
        if (array_search($option, $bank_codes)) {
            return array_search($option, $bank_codes);
        } else {
            return 'false';
        }
    }

    public function ValidatAccount($body)
    {
        $credit_account = $this->get_value("credit_account");
        $amount = $this->get_value("amount");
        $amount_formated = number_format($amount, 2);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/account?account_number=$credit_account",
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
        if ($response) {
            $yummy = json_decode($response);
            $firstname = $yummy->custname;
            $acc = $yummy->acc;
            return $this->result("continue", "LAPO Swift Banking\nTransfer N$amount_formated TO  $acc $firstname.\nEnter 4-digit PIN to confirm:");
        }
    }

    public function QueryName($institutionCode)
    {
        $acc_num = $this->get_value("credit_account");
        $amount = $this->get_value("amount");
        $amount_formated = number_format($amount, 2);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/api/resolve-account?account_number=$acc_num&bank_code=$institutionCode",
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
            return 'an error occured\n please try again';
        } elseif ($response) {
            $request = json_decode($response);
            $account_name = $request->account_name;
            return $this->result("continue", "Transfer of NGN$amount_formated To $acc_num\n$account_name\nEnter Your 4-digit PIN to confirm");
        } else {
            $request = json_decode($response);
            $description = $request->description;
            return $this->cancelLastAction("$description\n" . $this->SelectBank());
        }
    }

    // public function getAccount($body){
    //     $curl = curl_init();
    //     $msisdn = '0' . substr($this->posted["msisdn"], -10);

    //     curl_setopt_array($curl, array(
    //     CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/accounts-by-phone?phone=$msisdn",
    //     CURLOPT_RETURNTRANSFER => true,
    //     CURLOPT_ENCODING => "",
    //     CURLOPT_MAXREDIRS => 10,
    //     CURLOPT_TIMEOUT => 0,
    //     CURLOPT_FOLLOWLOCATION => true,
    //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //     CURLOPT_CUSTOMREQUEST => "GET",
    //     ));
    //     $response = curl_exec($curl);
    //     $err = curl_error($curl);
    //     curl_close($curl);
    //     $yummy = json_decode($response);
    //     $count = 1;
    //     $arr = [];
    //     foreach($yummy->items as $account){
    //            array_push($arr, $count.". ".$account->ACCOUNT_NUMBER." ");
    //            $this->set_value($count, $account->ACCOUNT_NUMBER);
    //             $count++;
    //     }

    //     return(implode("\n",array_values($arr)));
    // }

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

    public function QueryTransfer($body)
    {

        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $pin = $this->get_value("pin");
        $account_selected = $this->get_value("debit_account");
        $debit_account = $this->get_value($account_selected);
        $credit_account = $this->get_value("credit_account");
        $amount = $this->get_value("amount");
        $institutionCode = $this->get_value("institutionCode");
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/transfer-other-bank",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"phone\":\"$msisdn\",\"pin\":\"$pin\",\"debit_account\":\"$debit_account\",\"credit_account\":\"$credit_account\",\"amount\":\"$amount\",\"bank_code\":\"$institutionCode\"}",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return "an error occured\n please try again";
        } elseif ($response) {
            $request = json_decode($response);
            $description = $request->description;
            return $this->result("End", "LAPO Swift Banking\n$description");
        } else {
            return "Something went wrong\n Please try again";
        }
    }

    public function QueryTransferToLapo($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $pin = $this->get_value("pin");
        $account_selected = $this->get_value("debit_account");
        $debit_account = $this->get_value($account_selected);
        $credit_account = $this->get_value("credit_account");
        $amount = $this->get_value("amount");
        $curl = curl_init();

        $postdata = array(
            'phone' => $msisdn,
            "pin" => $pin,
            'debit_account' => $debit_account,
            "credit_account" => $credit_account,
            'amount' => $amount,
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/transfer",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postdata),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",

            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return "an error occured\n please try again";
        } elseif ($response) {
            $request = json_decode($response);
            $description = $request->description;
            return $this->result("End", "LAPO Swift Banking\n$description");
        } else {
            return "Something went wrong\n Please try again";
        }
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }
}
