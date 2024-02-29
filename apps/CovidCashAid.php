<?php

include_once 'UssdApplication.php';
use Symfony\Component\Yaml\Yaml;

/**
 * @author David
 */
class CovidCashAid extends UssdApplication
{

    public function getResponse($body)
    {
        $this->initValues($body);

        switch ($this->currentStep) {
            case 1:

                return $this->result("continue", $this->render("menu"));

            case 2:
                if ($this->lastInputed == 1) {
                    $this->set_value("option", 'account');
                    return $this->withAccount();
                } elseif ($this->lastInputed == 2) {
                    $this->set_value("option", 'noaccount');
                    return $this->withOutAccount();
                }
            default:
                if ($this->get_value('option') == 'account') {
                    return $this->withAccount();
                } elseif ($this->get_value('option') == 'noaccount') {
                    return $this->withOutAccount();
                }
                break;
        }

    }

    public function withAccount()
    {
        switch ($this->currentStep) {
            // case 2:

            //     return $this->result("continue", $this->render("name"));

            case 2:

                //$this->set_value("lga", $this->lastInputed);
                return $this->result("continue", $this->render("account_no"));

            case 3:

                $this->set_value("account_no", $this->lastInputed);
                return $this->result("continue", $this->render("bank_name"));

                
            case 4:

                $this->set_value("bank_name", $this->lastInputed);
                return $this->result("continue", $this->render("state"));

            case 5:

                $this->set_value("state", $this->lastInputed);
                return $this->result("continue", $this->render("lga"));

            case 6:

                $this->set_value("lga", $this->lastInputed);
                return $this->result("continue", $this->render("bvn"));

            case 7:

                $msisdn = '0' . substr($this->posted["msisdn"], -10);
                //$name = explode(" ", $this->get_value("name"));
                //$address = $this->get_value("address");
                $bank_name = $this->get_value("bank_name");
                $lga = $this->get_value("lga");
                $state = $this->get_value("state");
                $bvn = $this->lastInputed;
                $account_number = $this->get_value("account_no");
                $this->dbDataPusher("null", "null", $lga, $state, $bank_name, $bvn, $account_number, 'Yes', 'null', $msisdn);
                $pushData = $this->sendWithAccountData("null", "null", $lga, $state, $bank_name, $bvn, $account_number, 'Yes', 'null', $msisdn);

                if ($pushData->ResponseHeader->ResponseCode == "00") {
                    return $this->result("End", $this->render("thank_you"));
                } else {
                    return $this->result("End", "CovidCashAid \nSorry your request failed. Kindly try again later");
                }

            default:
                break;
        }
    }

    public function withOutAccount()
    {
        switch ($this->currentStep) {
            case 2:

                return $this->result("continue", $this->render("name"));

            case 3:

                $this->set_value("name", $this->lastInputed);
                return $this->result("continue", $this->render("dob"));
            case 4:

                $this->set_value("dob", $this->lastInputed);
                return $this->result("continue", $this->render("gender"));
            case 5:

                $gender = ($this->lastInputed == 1) ? 'Male' : "Female";
                $this->set_value("gender", $gender);
                return $this->result("continue", $this->render("state"));
            case 6:

                $this->set_value("state", $this->lastInputed);
                return $this->result("continue", $this->render("lga"));
            case 7:

                $msisdn = '0' . substr($this->posted["msisdn"], -10);
                $name = explode(" ", $this->get_value("name"));
                $lga = $this->lastInputed;
                $dob = $this->get_value('dob');
                $state = $this->get_value('state');
                $gender = $this->get_value("gender");
                $this->dbDataPusherNoAccount($name[0], $name[1], $state, 'No', $gender, $msisdn, $dob, $lga);
                $pushData = $this->sendWithoutAccountData($name[0], $name[1], $lga, null, $state, 'No', $gender, $msisdn, $dob);

                if ($pushData->ResponseHeader->ResponseCode == "00") {
                    return $this->result("End", $this->render("thank_you"));
                } else {
                    return $this->result("End", "CovidCashAid \nSorry your request failed. Kindly try again later");
                }

            default:
                # code...
                break;
        }
    }

    public function dbDataPusher($FirstName, $LastName, $Lga, $State, $BankName, $Bvn, $AccountNumber, $BankAccountExist, $Gender, $Phone)
    {

        $query = "INSERT INTO coralpay_cashaid_with_account("
            . "FirstName, LastName, Lga, State, BankName, Bvn, AccountNumber, BankAccountExist, Gender, Phone) "
            . "VALUES (?,?,?,?,?,?,?,?,?,?)";
        try {
            R::exec($query, [$FirstName, $LastName, $Lga, $State, $BankName, $Bvn, $AccountNumber, $BankAccountExist, $Gender, $Phone]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function dbDataPusherNoAccount($FirstName, $LastName, $State, $BankAccountExist, $Gender, $Phone, $dob, $lga)
    {

        $query = "INSERT INTO coralpay_cashaid_without_account("
            . "FirstName, LastName, Gender, State, lga, Phone, Dob, BankAccountExist) "
            . "VALUES (?,?,?,?,?,?,?,?)";
        try {
            R::exec($query, [$FirstName, $LastName, $Gender, $State, $lga, $Phone, $dob, $BankAccountExist]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        } 
    }

    public function sendWithAccountData($FirstName, $LastName, $Lga, $State, $BankName, $Bvn, $AccountNumber, $BankAccountExist, $Gender, $Phone)
    {

        $postdata = array(
            'RequestHeader' => [
                "Username" => "Covid19Nova",
                "Password" => "Cy\$Vn2@0",
            ],
            'PeoplesList' => [
                [
                    'FirstName' => $FirstName,
                    "LastName" => $LastName,
                    'Lga' => $Lga,
                    "State" => $State,
                    "BankName" => $BankName,
                    "Bvn" => $Bvn,
                    'AccountNumber' => $AccountNumber,
                    "BankAccountExist" => $BankAccountExist,
                    "Gender" => $Gender,
                    "Phone" => $Phone,
                ],
            ],
        );
        $url = "https://testdev.coralpay.com/Covid19Data/Api/DataCollection";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Content-Type: application/json',

        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $request = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return $err;
        } else {
            return json_decode($request);
        }
    }

    public function sendWithoutAccountData($FirstName, $LastName, $Lga, $Address, $State, $BankAccountExist, $Gender, $Phone, $dob)
    {

        $postdata = array(
            'RequestHeader' => [
                "Username" => "Covid19Nova",
                "Password" => "Cy\$Vn2@0",
            ],
            'PeoplesList' => [
                [
                    'FirstName' => $FirstName,
                    "LastName" => $LastName,
                    'Lga' => $Lga,
                    //'Dob' => $dob,
                    'State' => $State,
                    //"Address" => $Address,
                    "BankAccountExist" => $BankAccountExist,
                    "Gender" => $Gender,
                    "Phone" => $Phone,
                ],
            ],
        );
        $url = "https://testdev.coralpay.com/Covid19Data/Api/DataCollection";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Content-Type: application/json',

        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $request = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return $err;
        } else {
            return json_decode($request);
        }
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("apps/config/covidcashaid/aid.yml"));
        return $arr['pages'][$key];
    }
}
