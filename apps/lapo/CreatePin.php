<?php
include_once 'apps/UssdApplication.php';
include_once "apps/Lapo.php";
use Symfony\Component\Yaml\Yaml;

class CreatePin  extends Lapo{

    public function AddPin($body)
    {
        $this->initValues($body);
        $this->set_value("Pin", true);
        switch ($this->currentStep) {
            case 1:
                    return $this->result("continue", $this->render("new_ussd_customer"));
            case 2:
                if (!in_array($this->lastInputed, ['1'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('new_ussd_customer'));
                }
                return $this->result("continue", $this->render("enter_pin"));
            case 3:
                $bvn = preg_replace("/[^-0-9\.]/", "", $this->lastInputed);
                if (strlen($bvn) <> 4) { 
                    return $this->cancelLastAction("You entered an invalid pin. \n" . $this->render('enter_pin'));
                } 
                $this->set_value("pin_number", $this->lastInputed);
                $num = $this->getCusNo($body);
                return  $this->AddAccountAndPin($body, $num);  
        }
    }


    public function getCusNo($body)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/customer?phone=$msisdn",
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
        }elseif($response) {
            $request = json_decode($response);
            $custno = $request->custno;
                return $custno;
        }else{
            return "Something went wrong\n Please try again";
        }
    }

    public function AddAccountAndPin($body, $num)
    {
        $pin_number = $this->get_value("pin_number");
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        if ($this->posted["src"] == 'airtel' || $this->posted["src"] == 'Airtel') {
            $mno = 'airtel';
        }elseif($this->posted["src"] == 'mtn' || $this->posted["src"] == 'MTN'){
            $mno = 'mtn';
        }elseif($this->posted["src"] == '9mobile' || $this->posted["src"] == 'Etisalat'){
            $mno = '9mobile ';
        }
        $postdata = array(
            'phone' => $msisdn, 
            "pin" => $pin_number,
            'mno' => $mno,
            "custno" => $num,
        );
        $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => "http://10.0.0.140:8080/ords/pcash/fcubs/register-ussd",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postdata),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return  $this->result("end", "an error occured\n please try again");
            }elseif($response) {
                $request = json_decode($response);
                if($request->description == 'Successful'){
                    return  $this->result("end", "LAPO Swift Banking \nMobile number successfully registerd on USSD");
                }else{
                    $description = $request->description;
                    return  $this->result("end", "$description");
                }
            }else{
                return  $this->result("end", "Something went wrong\n Please try again");
            }
    }

    public function render($key){
        $arr = Yaml::parse(file_get_contents("./apps/config/lapo/config.yml"));
        return $arr['pages'][$key];
    }

}





