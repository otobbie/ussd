<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Lapo.php";
use Symfony\Component\Yaml\Yaml;

class SelfService extends Lapo
{
    public function Service($body)
    {
        $this->initValues($body);
        switch ($this->currentStep) {

            case 2:
                $this->set_value("menu_option", $this->lastInputed);
                return $this->result("continue", $this->render("self_sev"));

            case 3:
                if (!in_array($this->lastInputed, ['1', '2', '3', '4'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('self_sev'));
                }
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("service", "new_acc");
                        return $this->create_menu($body);
                    case 2:
                        $this->set_value("service", "reset_pin");
                        return $this->create_menu($body);
                    case 3:
                        $this->set_value("service", "debit");
                        return $this->create_menu($body);
                    case 4:
                        $this->set_value("service", "forgot");
                        return $this->create_menu($body);
                    default:
                        return $this->cancelLastAction($this->render("self_sev"));
                }
            default:
                return $this->create_menu($body);
        }
    }

    public function create_menu($body)
    {
        $mode = $this->get_value("service");
        if ($mode == "new_acc") {
            return $this->newAccountNumber($body);
        } elseif ($mode == "reset_pin") {
            return $this->pinReset($body);
        } elseif ($mode == "debit") {
            return $this->blockDebit($body);
        } elseif ($mode == "forgot") {
            return $this->ForgotPin($body);
        }
    }
######################################################################################
    public function newAccountNumber($body)
    {
        switch ($this->currentStep) {
            case 3:
                return $this->result("continue", $this->render("pin"));
            case 4:
                $this->set_value("pin", $this->lastInputed);
                $validate = $this->PIN($body);
                if ($validate == "Successful") {
                    $this->set_value("pin", $this->lastInputed);
                    return $this->createNewAccountNumber($body);
                } else {
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render('pin'));
                }
        }
    }

    public function createNewAccountNumber($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $pin = $this->get_value("pin");
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/account",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"phone\":\"$msisdn\",\"pin\":\"$pin\"}",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $request = json_decode($response);
        if ($err) {
            return "an error occured\n please try again";
        } elseif ($request->description == "Successful") {
            $account = $request->account_number;
            return $this->result("end", "Your Lapo savings account has been created successfully:\nYour account number: $account.");
        } else {
            return $this->result("continue", "Error creating account\nPlease try again later");
        }
    }
######################################################################################

    public function pinReset($body)
    {
        switch ($this->currentStep) {
            case 3:
                return $this->result("continue", $this->render("oldpin"));
            case 4:
                $this->set_value("pin", $this->lastInputed);
                $validate = $this->PIN($body);
                if ($validate == "Successful") {
                    $this->set_value("old_pin", $this->lastInputed);
                    return $this->result("continue", $this->render("new_pin"));
                } else {
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("oldpin"));
                }
            case 5:
                $pin = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if (strlen($pin) != 4) {
                    return $this->cancelLastAction("You entered an invalid pin. \n" . $this->render("new_pin"));
                } else {
                    $this->set_value("new_pin", $this->lastInputed);
                    return $this->resetPin($body);
                }
        }
    }

    public function resetPin($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $new_pin = $this->get_value("new_pin");
        $old_pin = $this->get_value("old_pin");

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/pin",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"phone\":\"$msisdn\",\"pin\":\"$old_pin\",\"new_pin\":\"$new_pin\"}",
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
            $message = $request->message;
            return $this->result("end", "LAPO Swift Banking:\n$message");
        } else {
            return "Something went wrong\n Please try again";
        }
    }

######################################################################################

    public function blockDebit($body)
    {
        switch ($this->currentStep) {
            case 3:

                return $this->getAccount($body);

            case 4:
                if (!in_array($this->lastInputed, ['1', '2', '3', '4'])) {
                    return $this->cancelLastAction("You entered an invalid account. \n" . $this->getAccount($body));
                } else {
                    $this->set_value("account", $this->lastInputed);
                    $account_selected = $this->get_value("account");
                    $account = $this->get_value($account_selected);
                    return $this->result("continue", "You are about to block\nAccount Number $account from debiting " . $this->render("pin"));
                }
            case 5:
                $this->set_value("pin", $this->lastInputed);
                $validate = $this->PIN($body);
                if ($validate == "Successful") {
                    return $this->queryBlockDebit($body);
                } else {
                    return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }
        }
    }

    public function queryBlockDebit($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $pin = $this->get_value("pin");
        $account_selected = $this->get_value("account");
        $account = $this->get_value($account_selected);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/block-debit",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"phone\":\"$msisdn\",\"pin\":\"$pin\",\"account_number\":\"$account\"}",
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
            return $this->result("end", "LAPO Swift Banking\n$description");
        } else {
            return "Something went wrong\n Please try again";
        }
    }
######################################################################################

    public function ForgotPin($body)
    {
        switch ($this->currentStep) {
            case 3:
                return $this->result("end", $this->render("forgot"));
        }
    }

######################################################################################

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

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }
}
