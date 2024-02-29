<?php

use Symfony\Component\Yaml\Yaml;

include_once 'UssdApplication.php';
include_once 'Mcash.php';
include_once 'Rave.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
require_once 'lib/classes/rb.php';
include_once 'classes/easytaxpayer/SendSms.php';
include_once 'CoralPay.php';

/*

Main USSD : 13220

To Git Pull:

git pull origin master

To Push:
git add -A
git commit -m 'commit message'
git push origin master

*/
define('INVALID_OPTION', 'Invalid option. Please try again');
define('NEXT_OPTION', 10);

class Baxi extends UssdApplication
{
    # set variables once, avoid duplicate codes
    public $client_id = "2faf96ec-e1ab-40bd-b732-edde0a314a71";
    public $client_key = "NDc4NDM3ODQzODczNDg5MzQ4";
    public $invalid_response = "Invalid response from Baxi Mobile";
    public $app_name = "Baxi Mobile";
    public $registration_error = "Registration failed on Baxi Mobile";
    private $base_url = "https://api.staging.baxi-services.com/";
    private $cp;

    public function getResponse($body)
    {
        # setup CoralPay for payment
        $this->cp = new CoralPay();
        $this->initValues($body);
        # handle back 
        #return $this->continue("Screen: ".$this->currentStep);
        if ($this->lastInputed == '00') {
            return $this->back();
        }
        #return $this->continue("Screen: ".$this->currentStep);
        switch ($this->currentStep) {
            case 1:
                # *13220# 
                return $this->result("continue", $this->render("welcome"));
            case 2:
                switch ($this->lastInputed) {
                    case 1:
                        # *13220# , Press 1
                        $this->set_value("baxi_action", "register");
                        return $this->flow($body);
                    case 2:
                        # *13220# -> Press 2
                        $this->set_value("baxi_action", "transfer");
                        return $this->flow($body);
                    case 3:
                        # *13220# -> Press 3
                        $this->set_value("baxi_action", "fund-wallet");
                        return $this->flow($body);
                    case 4:
                        # *13220# -> Press 4
                        $this->set_value("baxi_action", "balance-enquiry");
                        return $this->flow($body);
                    default:
                        return $this->cancelLastAction($this->render("welcome"));
                }
            default:
                return $this->flow($body);
        }
    }


    public function flow($body)
    {
        $mode = $this->get_value("baxi_action");
        # use case rather that if
        /*
        if ($mode == "register") {
            return $this->Register($body);
        }elseif ($mode == "transfer") {
            return $this->Transfer($body);
        }
        */
        switch ($mode) {
            case 'register':
                return $this->Register($body);
                #break;
            case 'transfer':
                return $this->Transfer($body);
                #break;
            case 'fund-wallet':
                return $this->FundWallet($body);
                #break;
            case 'balance-enquiry':
                return $this->CheckBalance($body);
                #break;
            default:
                return $this->Register($body);
                #break;
        }
    }


    public function FundWallet($body)
    {
        if ($this->value_exists('do_funding') and $this->get_value('do_funding') == 'yes') {
            return $this->initiate_wallet_funding();
        }
        switch ($this->currentStep) {
            case 2:
                # Enter amount
                $this->delete_value('do_funding');
                return $this->result("continue", $this->render("amount_fund"));
            case 3:
                $this->set_value("amount", $this->lastInputed);
                # Ask for Account
                return $this->result("continue", $this->render("account"));
            case 4:
                # Show bank page 1
                $this->set_value("account", $this->lastInputed);
                #$this->set_value("bank", $this->lastInputed);

                # list page 1 of banks
                return $this->continue($this->cp->list_bank_page(1));
            case 5:
                #$this->set_value("account", $this->lastInputed);
                if ($this->lastInputed == NEXT_OPTION) {
                    # list page 2 of banks
                    return $this->continue($this->cp->list_bank_page(2));
                }
                # bank was selected, show summary
                $this->set_value("bank_page", 1);
                return $this->show_fund_summary();
                #return $this->result("continue", "Wallet Transfer Details\n1.Amt: N$amount\n2. A/C: $account\n3. Beneficiary: John Doe\nEnter PIN to authorize transfer.");
            case 6:
                if ($this->lastInputed == NEXT_OPTION) {
                    # list page 3 of banks
                    return $this->continue($this->cp->list_bank_page(3));
                }
                $this->set_value("bank_page", 2);
                # show summary
                return $this->show_fund_summary();
            case 7:
                if ($this->lastInputed == NEXT_OPTION) {
                    # list page 3 of banks
                    return $this->continue($this->cp->list_bank_page(4));
                }
                $this->set_value("bank_page", 3);
                # show summary
                return $this->show_fund_summary();
            case 8:
                # show summary
                $this->set_value("bank_page", 4);
                return $this->show_fund_summary();
            default:
                return $this->continue(INVALID_OPTION);
        }

        #$message = "Wallet funded successfully";
        #return $this->result("end", $message);
    }

    public function initiate_wallet_funding()
    {
        $pin = $this->lastInputed;
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $amount = $this->get_value("amount");
        $account = $this->get_value("account");
        $bank_option = $this->get_value("bank_option");
        $bank_page = $this->get_value("bank_page");
        $bank = $this->cp->get_bank($bank_page, $bank_option);
        $bank_code = $bank['code'];
        $bank_name = $bank['name'];
        $request_id = time();
        #$bank_name = $bank['name'];
        $account_name = $this->get_value("account_name");
        # To do: initiate wallet funding
        return $this->end('Dial *966*000*1213# to authorize your transaction to Baxi Mobile');
    }

    public function CheckBalance($body)
    {
        switch ($this->currentStep) {
            case 2:
                $resp = $this->get_user($body);
                if ($resp) {
                    # user exists, ask for PIN
                    #return $this->result("end", $resp->resp_message);
                    if ($resp->resp_code == "00") {
                        $message = $this->render("ask_pin");
                        return $this->result("end", $message);
                    }
                    # probably account does not exist
                    return $this->result("end", $resp->resp_description);
                }
                # bad response from Baxi
                return $this->result("end", $this->invalid_response);
            case 3:
                # get CheckBalance
                $balance = "0.00";
                return $this->result("end", "Account Balance: " . $balance);
        }
    }

    public function Register($body)
    {
        #return $this->result("continue", $this->render("new_user"));
        #return $this->result("continue", $this->currentStep);

        switch ($this->currentStep) {
            case 2:
                #Enter username
                return $this->continue($this->render("new_user"));
            case 3:
                # validate username and enter PIN if correct

                $this->set_value("user_name", $this->lastInputed);
                return $this->validate_username($body);
            case 4:

                $pin = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                $this->set_value("pin", $this->lastInputed);
                if (strlen($pin) <> 4) {
                    # incorrect PIN entered
                    return $this->cancelLastAction("You entered an invalid pin.\n" . $this->render("pin"));
                } else {
                    # Register User
                    return $this->register_user($body);
                }
        }
    }



    public function Transfer($body)
    {
        #return $this->result("continue", $this->render("amount_transfer"));
        # if we have everything set we can initiate transfer
        # else we cancel all
        if ($this->value_exists('do_transfer') && $this->get_value('do_transfer') == 'yes') {
            return $this->initiate_transfer();
        }

        switch ($this->currentStep) {
            case 2:
                # Ask for Amount
                $this->delete_value('do_transfer');
                return $this->result("continue", $this->render("amount_transfer"));
            case 3:
                $this->set_value("amount", $this->lastInputed);
                # Ask for Account
                return $this->result("continue", $this->render("account"));
            case 4:
                # Show bank page 1
                $this->set_value("account", $this->lastInputed);
                #$this->set_value("bank", $this->lastInputed);

                # list page 1 of banks
                return $this->continue($this->cp->list_bank_page(1));
            case 5:
                #$this->set_value("account", $this->lastInputed);
                if ($this->lastInputed == NEXT_OPTION) {
                    # list page 2 of banks
                    return $this->continue($this->cp->list_bank_page(2));
                }
                # bank was selected, show summary
                $this->set_value("bank_page", 1);
                return $this->show_transfer_summary();
                #return $this->result("continue", "Wallet Transfer Details\n1.Amt: N$amount\n2. A/C: $account\n3. Beneficiary: John Doe\nEnter PIN to authorize transfer.");
            case 6:
                if ($this->lastInputed == NEXT_OPTION) {
                    # list page 3 of banks
                    return $this->continue($this->cp->list_bank_page(3));
                }
                $this->set_value("bank_page", 2);
                # show summary
                return $this->show_transfer_summary();
            case 7:
                if ($this->lastInputed == NEXT_OPTION) {
                    # list page 3 of banks
                    return $this->continue($this->cp->list_bank_page(4));
                }
                $this->set_value("bank_page", 3);
                # show summary
                return $this->show_transfer_summary();
            case 8:
                # show summary
                $this->set_value("bank_page", 4);
                return $this->show_transfer_summary();
                #return $this->continue("Wallet Transfer Details\n1.Amt: N$amount\n2. A/C: $account\n3. Beneficiary: John Doe\nEnter PIN to authorize transfer.");
            default:
                return $this->continue(INVALID_OPTION);
        }
    }

    public function initiate_transfer()
    {
        $pin = $this->lastInputed;
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $amount = $this->get_value("amount");
        $account = $this->get_value("account");
        $bank_option = $this->get_value("bank_option");
        $bank_page = $this->get_value("bank_page");
        $bank = $this->cp->get_bank($bank_page, $bank_option);
        $bank_code = $bank['code'];
        $bank_name = $bank['name'];
        $request_id = time();
        #$bank_name = $bank['name'];
        $account_name = $this->get_value("account_name");
        # transfer
        $fields = [
            "request_id" => "$request_id",
            "account_number" => "$account",
            "bank_code" => "$bank_code",
            "account_name" => $account_name,
            "bank_name" => "$bank_name",
            "transaction_amount" => "$amount",
            "pin" => "$pin",
            "telephone" => "$msisdn",
            "narration" => "USSD Transfer:" . $request_id

        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->base_url . "api/vaccount/public/ussd/fund-transfer",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'BaxiClientId: ' . $this->client_id,
                'BaxiClientKey: ' . $this->client_key
            ],
        ));

        $response = curl_exec($curl);
        #var_dump(json_encode($fields));
        #var_dump($response); 
        #die();
        $err = curl_error($curl);
        curl_close($curl);
        if ($response) {
            $json_obj = json_decode($response);
            $status_code = $json_obj->resp_code;
            if ($status_code == "00") {
                return $this->end($json_obj->resp_message);
            }
            # bad response. Show the reason
            return $this->end($json_obj->resp_message);
        }
        return $this->end($this->invalid_response);
    }
    public function show_transfer_summary()
    {
        # set the bank option
        $bank_option = $this->lastInputed;
        $this->set_value("bank_option", $bank_option);
        $amount = number_format($this->get_value("amount"), 2);
        $account = $this->get_value("account");
        $bank_page = $this->get_value("bank_page");
        $bank = $this->cp->get_bank($bank_page, $bank_option);
        $bank_name = $bank['name'];
        $account_name = $this->get_account_name($account, $bank['code']);
        $this->set_value('do_transfer', 'yes');
        return $this->continue("Transfer N$amount to $account ($account_name) $bank_name.\nEnter PIN to proceed.");
    }
    public function show_fund_summary()
    {
        # set the bank option
        $bank_option = $this->lastInputed;
        $this->set_value("bank_option", $bank_option);
        $amount = number_format($this->get_value("amount"), 2);
        $account = $this->get_value("account");
        $bank_page = $this->get_value("bank_page");
        $bank = $this->cp->get_bank($bank_page, $bank_option);
        $bank_name = $bank['name'];
        $account_name = $this->get_account_name($account, $bank['code']);
        $this->set_value('do_funding', 'yes');
        return $this->continue("Fund wallet with N$amount from $account ($account_name) $bank_name.\nEnter PIN to proceed.");
    }

    public function TransferFund($body)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.staging.baxi-services.com/api/vaccount/public/ussd/fund-transfer',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
          "request_id": "93142786747",
          "user_name": "username",
          "telephone": "08023732763",
          "pin": "1234",
          "transaction_amount": 2000.00,
          "bank_code": "000004",
          "bank_name": "Access Bank",
          "account_number": 03293837465,
          "account_name": "Customer name",
          "narration": "Transaction description"
        }
        ',
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'BaxiClientId: 2faf96ec-e1ab-40bd-b732-edde0a314a71',
                'BaxiClientKey: NDc4NDM3ODQzODczNDg5MzQ4'
            ),
        ));
    }

    function get_user($body)
    {

        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        #$userName = $this->get_value("user");

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->base_url . "api/core/apps/ussd/validate?telephone=$msisdn",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'BaxiClientId: ' . $this->client_id,
                'BaxiClientKey: ' . $this->client_key
            ],
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($response) {
            $request = json_decode($response);
            return $request;
        }
    }




    function validate_username($body)
    {
        $result = $this->get_user($body);
        if ($result) {
            # user exists
            if ($result->resp_code == "C02") {
                # Account not found
                $userName = $this->get_value("user_name");
                return $this->result("continue", "Username: $userName\nEnter 4-digit PIN to complete registration");
                #return $this->result("end", "An account with this phone number already exists");
            }
            if ($result->resp_code == "00") {
                # Account already exists
                return $this->result("continue", "Account already exists");
                #return $this->result("end", "An account with this phone number already exists");
            }
            return $this->result("end", $result->resp_description);
        }
    }

    public function get_account_name($account_number, $bank_code)
    {
        $fields = [
            "account_number" => $account_number,
            "bank_code" => $bank_code

        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->base_url . "api/vaccount/public/ussd/account-name-enquiry",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'BaxiClientId: ' . $this->client_id,
                'BaxiClientKey: ' . $this->client_key
            ],
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($response) {
            $json_obj = json_decode($response);
            $status_code = $json_obj->statusCode;
            if ($status_code == "00" && !empty($json_obj->data)) {
                $account_name = $json_obj->data->account_name;
                $this->set_value("account_name", $account_name);
                return $account_name;
            }
            # bad response
        }
    }

    public function register_user($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $userName = $this->get_value("user_name");
        $pin = $this->get_value("pin");
        $fields = [
            "user_name" => $userName,
            "pin" => $pin,
            "telephone" => $msisdn
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->base_url . "api/core/apps/ussd/register",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'BaxiClientId: ' . $this->client_id,
                'BaxiClientKey: ' . $this->client_key
            ],
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($response) {
            $json_obj = json_decode($response);
            $resp_code = $json_obj->resp_code;
            if ($resp_code == "00") {
                return $this->end($this->app_name . " \n Registration successful");
            }
            #return $this->result("end", $this->registration_error);
            return $this->end("Registration Failed:\nDesc:" . $json_obj->resp_description
                . "\nMessage: " . $json_obj->resp_message . "\nCode:" . $json_obj->resp_code);
        }
        return $this->result("end", $this->invalid_response);
    }



    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("apps/config/baxi/config.yml"));
        return $arr['pages'][$key];
    }
}
