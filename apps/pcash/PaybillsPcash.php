<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Pcash.php";
use Symfony\Component\Yaml\Yaml;

class PaybillsPcash extends Pcash{

    #########################################################################
    public function PayingBill($body)
    {
        $this->initValues($body);
        $bill = $this->PayBills($body);
        $bill_code = $this->BillCode($body);

        # code...
        switch ($this->currentStep) {
            case 2:
                return $this->result("continue", "Select Bill:\n$bill");
            case 3:
                $this->set_value("bill", $this->lastInputed);
                return $this->result("continue", "Select Bill:\n$bill_code");
            case 4: 
                $this->set_value("bill_code", $this->lastInputed);
                return $this->result("continue", $this->render("cus_number"));
            case 5: 
                $this->set_value("customer_no", $this->lastInputed);
                return $this->result("continue", $this->render("pin"));
            case 6: 
                $this->set_value("pin", $this->lastInputed);
                $token = $this->getToken($body);
                if($token){
                    return $this->QueryBill($body, $token);
                }else{
                return $this->cancelLastAction("Invalid PIN entered by customer\n" . $this->render("pin"));
                }   
        }
    }


#######################################################################################
public function getToken($body){
    $headers = [];
    $pin = $this->get_value("pin");
    $msisdn = '0' . substr($this->posted["msisdn"], -10);
    $mno = $this->posted["src"];

    $postdata = array(
        'uid' => $msisdn,
        "pin" => $pin,
        'device' => 'ussd',
        "mno" => $mno,
    );             
    $url = "http://10.0.0.140:8080/ords/pcash/api/auth";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers_head = [
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_head);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
        function($curl, $header) use (&$headers)
        {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) // ignore invalid headers
            return $len;
            $headers[strtolower(trim($header[0]))][] = trim($header[1]);
            return $len;
        }
        );
        $data = curl_exec($ch);
        $request = json_decode($data);
        $head = json_encode($headers);
        $heads = json_decode($head);
        $err = curl_error($ch);
        if ($err) {
            return $err;
        }else{
            $token =  $heads->token['0'];
            return  $token;
         }

}

    public function PayBills($body){

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pcash.ng/ords/pcash/fcubs/bill-products',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Cookie: cookiesession1=0CCA0A42OOH2X4RGHIIIKME301FO9C61'
        ),
        ));


        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $yummy = json_decode($response);
        $count = 1;
        $arr = [];
        
        foreach($yummy->items as $bills){
            array_push($arr, $count.". ".$bills->name." ");
            $this->set_value($count, $bills->biller_code);
                $count++;
        }
        return(implode("\n",array_values($arr)));
    }


    public function BillCode($body)
    {
        $code = $this->get_value("bill");
        $billing_code = $this->get_value($code);

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://pcash.ng/ords/pcash/fcubs/bill-product-items?biller_code=$billing_code",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
            'Cookie: cookiesession1=0CCA0A42OOH2X4RGHIIIKME301FO9C61'
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $yummy = json_decode($response);
        $count = 1;
        $arr = [];
        
        foreach($yummy->items as $bills){
               array_push($arr, $count.". ".$bills->short_name." ");
               $this->set_value($count, $bills->bill_id);

                $count++;
        }
        return(implode("\n",array_values($arr)));
    }


    public function QueryBill($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $billing_code = $this->get_value("bill_code");
        $bill_id = $this->get_value($billing_code);
        $pin = $this->get_value("pin");
        $customer_no = $this->get_value("customer_no");

        $postdata = array(
            "phone" => "$msisdn",
            "pin" => "$pin",
            "customer_number" => "$customer_no",
            "bill_id" => $bill_id,
        );  
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'http:///ords/pcash/fcubs/bill-payment',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>json_decode($postdata),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return "an error occured\n Please try again";
        }elseif($response) {
            $request = json_decode($response);
                $description = $request->description;
                return $this->result("end", "LAPO Swift Banking\n$description");
        }else{
            return $this->result("end","Service temporarily unavailable\nPlease try again");
        }
    }

    
##################################################################################################
    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }
}