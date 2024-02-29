<?php
// include_once 'lapo/AirtimeDeposit.php';
include_once 'lapo/AirtimePurchase.php';
include_once 'lapo/CheckAccountBalance.php';
include_once 'lapo/CreateAccount.php';
include_once 'lapo/Data.php';
include_once 'pcash/LoanRequestPcash.php';
include_once 'lapo/MiniStatement.php';
include_once 'lapo/PayBills.php';
include_once 'lapo/SelfService.php';
include_once 'lapo/TransferFunds.php';
include_once 'lapo/CreatePin.php';
include_once 'LapoShortCodes/AccountCreation.php';
include_once 'LapoShortCodes/AirtimeOthers.php';
include_once 'LapoShortCodes/AirtimeSelf.php';
include_once 'LapoShortCodes/DataOthers.php';
include_once 'LapoShortCodes/DataSelf.php';
include_once 'LapoShortCodes/BankStatement.php';
include_once 'LapoShortCodes/Transfer.php';

include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
use Symfony\Component\Yaml\Yaml;

class Lapo extends UssdApplication
{

    public function getResponse($body)
    {

        $this->initValues($body);
        # back functionality can come here
        if($this->lastInputed == '00'){
            return $this->back();
        }
        # end back functionality

        $shortcode = $this->get_value("isShortCode");
        $open = $this->get_value("Open");
        $pin = $this->get_value("Pin");

        if ($shortcode == true) {
            return $this->checkInputContent($body);
        } elseif ($open == true) {
            return $this->OpenAccount($body);
        } elseif ($pin == true) {
            return $this->CreatePin($body);
        }

        $register = $this->registeredCustomer($body);
        $check = $this->checkCustomer($body);
        /*
        if registeredCustomer



        */

         /*
        Suggestion to re-writethis becaue if too many static values,
        This code is likely to break
         */
        if ($register != "Successful" && $register != "Customer with phone number does not exist") {

            return $this->result("end", "System is unavailable. Please try again later");

        }
        /*
        to much if , elseif, else: likey to break somewhere
        */

        switch ($this->currentStep) {
            case 1:
                if ($register == "Successful") {

                    return $this->checkInputContent($body);

                } elseif ($check == "Successful" && $register != "Successful") {

                    $pin = new CreatePin();
                    return $pin->AddPin($body);

                } else {

                    $create = new CreateAccount();
                    return $create->openAccount($body);

                }

            case 2:
                if (!in_array($this->lastInputed, ['1', '2', '3', '4', '5', '6', '7', '8'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render("main_menu"));
                }
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("lapo_action", "buy_airtime");
                        return $this->lapo_menu($body);
                    case 2:
                        $this->set_value("lapo_action", "funds_transfer");
                        return $this->lapo_menu($body);
                    case 3:
                        $this->set_value("lapo_action", "bill_payment");
                        return $this->lapo_menu($body);
                    case 4:
                        $this->set_value("lapo_action", "account_balance");
                        return $this->lapo_menu($body);
                    case 5:
                        $this->set_value("lapo_action", "data");
                        return $this->lapo_menu($body);
                    case 6:
                        $this->set_value("lapo_action", "statement");
                        return $this->lapo_menu($body);
                    case 7:
                        $this->set_value("lapo_action", "service");
                        return $this->lapo_menu($body);
                    case 8:
                        $this->set_value("lapo_action", "loan");
                        return $this->lapo_menu($body);
                    default:
                        return $this->cancelLastAction($this->render("main_menu"));
                }
            default:
                return $this->lapo_menu($body);
        }
    }

    public function CreatePin($body)
    {
        $pin = new CreatePin();
        return $pin->AddPin($body);
    }

    public function OpenAccount($body)
    {
        $create = new CreateAccount();
        return $create->openAccount($body);
    }

    public function registeredCustomer($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/registered?phone=$msisdn",
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
            # better to return the error code than the description(which is more likely to change)
            return $description;
        } else {
            return "Something went wrong\n Please try again";
        }
    }

    public function checkCustomer($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/customer-check?phone=$msisdn",
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
            return "An error occured\n please try again";
        } elseif ($response) {
            $request = json_decode($response);
            $description = $request->description;
            return $description;

        } else {
            return "Something went wrong\n Please try again";
        }
    }

    public function checkInputContent($body)
    {
        $first_pass = $this->get_value("lapo_type");
        if (!$first_pass) {
            //  $content = $this->lastInputed = $body['content'];
            $content = $this->posted['content'];
            $exploded = explode('*', $content);
            $remove = preg_replace("/[^-0-9\.]/", "", $exploded);
            $content_three = $remove[2];
            $content_four = $remove[3];
            $this->set_value("content_three", $content_three);
            $this->set_value("content_four", $content_four);
        }

        // return $this->result("continue", "$content");

        $content_four = $this->get_value("content_four");
        $content_three = $this->get_value("content_three");
        $mode = $this->get_value("lapo_type");
        if ($mode == "airtime_others") {
            $airtime = new AirtimeOthers();
            return $airtime->AirtimeForOthers($body);
        } elseif ($mode == "airtime_self") {
            $self = new AirtimeSelf();
            return $self->Airtime($body, $content_three);
        } elseif ($mode == "data_others") {
            $self = new DataOthers();
            return $self->DataForOthers($body);
        } elseif ($mode == "data_self") {
            $self = new DataSelf();
            return $self->Data($body);
        } elseif ($mode == "transfer") {
            $transfer = new Transfer();
            return $transfer->TransferSteps($body);
        } elseif ($mode == "bankstatement") {
            $statement = new BankStatement();
            return $statement->Statement($body);
        }

        if ($content == "*371#") {
            return $this->result("continue", $this->render("main_menu"));
        } elseif ($content_three >= 49 && strlen($content_four) == '11') {
            $airtime = new AirtimeOthers();
            return $airtime->AirtimeForOthers($body);
        } elseif ($content_three == '5' && strlen($content_four) == '11') {
            $data_other = new DataOthers();
            return $data_other->DataForOthers($body);
        } elseif ($content_three == '5') {
            $data_self = new DataSelf();
            return $data_self->Data($body);
        } elseif ($content_three >= 10 && strlen($content_four) == '10') {
            $transfer = new Transfer();
            return $transfer->TransferSteps($body);
        } elseif (strlen($content_three) == '10') {
            $statement = new BankStatement();
            return $statement->Statement($body);
        } elseif ($content_three >= 49) {
            $airtime_self = new AirtimeSelf();
            return $airtime_self->Airtime($body);
        } else {
            return $this->result("End", $this->render("invalid_code"));
        }

    }

    public function lapo_menu($body)
    {
        $mode = $this->get_value("lapo_action");
        if ($mode == "buy_airtime") {
            $airtime = new AirtimePurchase();
            return $airtime->purchaseAirtime($body);
        } elseif ($mode == "funds_transfer") {
            $funds = new TransferFunds();
            return $funds->transferingFunds($body);
        } elseif ($mode == "bill_payment") {
            $bills = new PayBills();
            return $bills->payingBills($body);
        } elseif ($mode == "account_balance") {
            $balance = new CheckAccountBalance();
            return $balance->balanceInAccount($body);
        } elseif ($mode == "data") {
            $data = new Data($body);
            return $data->BuyData($body);
        } elseif ($mode == "statement") {
            $mini = new MiniStatement();
            return $mini->Statement($body);
        } elseif ($mode == "loan") {
            $request = new LoanRequestPcash($body);
            return $request->Loan($body);
        } elseif ($mode == "service") {
            $self = new SelfService();
            return $self->Service($body);
        }
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }

}
