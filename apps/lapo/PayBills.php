<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Lapo.php";
use Symfony\Component\Yaml\Yaml;

class PayBills extends Lapo
{

    #########################################################################
    public function payingBills($body)
    {
        $this->initValues($body);
        $category = $this->ProductCategory($body);
        // $bill = $this->BillsPayment($body);

        # code...
        switch ($this->currentStep) {
            case 2:
                return $this->result("continue", "Select Product Category:\n$category\n00. Back");
            case 3:
                if (!in_array($this->lastInputed, ['1', '2'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . "Select Product Category:\n$category\n00. Back");
                }
                switch ($this->lastInputed) {
                    case 1:
                        $this->set_value("action", "cable");
                        return $this->Category($body);
                    case 2:
                        $this->set_value("action", "eletricity");
                        return $this->Category($body);
                }
            default:
                return $this->Category($body);
        }

    }

    public function Category($body)
    {
        $mode = $this->get_value("action");
        if ($mode == "cable") {
            return $this->Cable($body);
        } elseif ($mode == "eletricity") {
            return $this->Electricity($body);
        }
    }

    public function Cable($body)
    {
        $res = $this->getAccount($body);
        # code...
        switch ($this->currentStep) {
            case 3:
                $this->set_value("category", $this->lastInputed);
                // $product_id = $this->get_value("category");
                // $product_category = $this->get_value($product_id);
                $bill = $this->BillsPayment($body);
                return $this->result("continue", "Select Bill Category:\n$bill");
            case 4:
                $this->set_value("bill", $this->lastInputed);
                $billing_code2 = $this->get_value($this->lastInputed . '-code');
                $bill_code = $this->BillCode($body, $billing_code2);
                return $this->result("continue", "Select Bill:\n$bill_code");
            case 5:
                $this->set_value("bill_code", $this->lastInputed);
                return $this->result("continue", $this->render("cus_number"));

            case 6:
                $this->set_value("customer_no", $this->lastInputed);
                return $this->result("continue", "Select Account:\n$res");
            case 7:
                $this->set_value("debit_account", $this->lastInputed);
                $pin = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                $this->set_value("pin", $this->lastInputed);
                $validate = $this->PIN($body);
                if (strlen($pin) != 4) {
                    return $this->cancelLastAction("You entered an invalid pin.\n" . $this->render("pin"));
                } elseif ($validate == "Successful") {
                    return $this->QueryBill($body);
                } else {
                    return $this->cancelLastAction("You entered an incorrect pin.\n" . $this->render("pin"));
                }

        }
    }

    public function Electricity($body)
    {

        # code...
        switch ($this->currentStep) {
            case 3:
                $this->set_value("category", $this->lastInputed);
                // $product_id = $this->get_value("category");
                // $product_category = $this->get_value($product_id);
                $bill = $this->BillsPayment($body);
                return $this->result("continue", "Select Bill Category:\n$bill");
            case 4:
                $this->set_value("bill", $this->lastInputed);
                $billing_code2 = $this->get_value($this->lastInputed . '-code');
                $bill_code = $this->BillCode($body, $billing_code2);
                return $this->result("continue", "Select Bill:\n$bill_code");
            case 5:
                $this->set_value("bill_code", $this->lastInputed);
                return $this->result("continue", $this->render("cus_number"));

            case 6:
                $this->set_value("customer_no", $this->lastInputed);
                return $this->result("continue", $this->render("tranfer_amount"));
            case 7:
                $this->set_value("amount", $this->lastInputed);
                return $this->getAccount($body);
            case 8:
                $this->set_value("debit_account", $this->lastInputed);
                $pin = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                $this->set_value("pin", $this->lastInputed);
                $validate = $this->PIN($body);
                if (strlen($pin) != 4) {
                    return $this->cancelLastAction("You entered an invalid pin.\n" . $this->render("pin"));
                } elseif ($validate == "Successful") {
                    return $this->QueryBill($body);
                } else {
                    return $this->cancelLastAction("You entered an incorrect pin.\n" . $this->render("pin"));
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

    public function ProductCategory($body)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://pcash.ng/ords/pcash/fcubs/bill-product-categories',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Cookie: cookiesession1=4F7D4358GNHUU9KI8VHEYBBO07KL6F7C',
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $yummy = json_decode($response);
        $count = 1;
        $arr = [];

        foreach ($yummy->items as $categories) {
            array_push($arr, $count . ". " . $categories->name . " ");
            $this->set_value($count, $categories->id);
            $count++;
        }
        return (implode("\n", array_values($arr)));
    }

    public function BillsPayment($body)
    {
        $product_id = $this->get_value("category");
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/bill-products?category_id=$product_id",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Cookie: cookiesession1=0CCA0A42OOH2X4RGHIIIKME301FO9C61',
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $yummy = json_decode($response);
        $count = 1;
        $arr = [];

        foreach ($yummy->items as $bills) {
            array_push($arr, $count . ". " . $bills->name . " ");
            $this->set_value($count . '-code', $bills->biller_code);
            $count++;
        }
        return (implode("\n", array_values($arr)));
    }

    public function BillCode($body, $billing_code2)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://pcash.ng/ords/pcash/fcubs/bill-product-items?biller_code=$billing_code2",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Cookie: cookiesession1=0CCA0A42OOH2X4RGHIIIKME301FO9C61',
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $yummy = json_decode($response);
        $count = 1;
        $arr = [];

        foreach ($yummy->items as $bills) {
            array_push($arr, $count . ". " . $bills->short_name . " ");
            $this->set_value($count, $bills->bill_id);
            $count++;
        }
        return (implode("\n", array_values($arr)));
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

    public function QueryBill($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);

        $account_selected = $this->get_value("debit_account");
        $debit_account = $this->get_value($account_selected);
        $amount = $this->get_value("amount");
        $billing_code = $this->get_value("bill_code");
        $bill_id = $this->get_value($billing_code);
        $pin = $this->get_value("pin");
        $customer_no = $this->get_value("customer_no");

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://pcash.ng/ords/pcash/fcubs/bill-payment',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "{'phone':'$msisdn','pin':'$pin','customer_number':'$customer_no','bill_id':$bill_id,'debit_account':'$debit_account','amount':'$amount'}",
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Cookie: cookiesession1=0CCA0A423TEZAWDDWAJUNGDDGURH5183',
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return "an error occured\n Please try again";
        } elseif ($response) {
            $request = json_decode($response);
            $description = $request->description;
            return $this->result("end", "LAPO Swift Banking\n$description");
        } else {
            return $this->result("end", "Service temporarily unavailable\nPlease try again");
        }
    }

##################################################################################################
    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }
}
