<?php

include_once 'UssdApplication.php';

use Symfony\Component\Yaml\Yaml;

/**
 * @author David
 */
class CovidAid extends UssdApplication
{

    public function getResponse($body)
    {
        $this->initValues($body);

        if ($this->value_exists("option")) {

            switch ($this->get_value("option")) {
                // case 'whatsapp':
                //     $this->delete_value("option");
                //     return $this->result("end", "COVID AID REQUEST \nOur WhatsApp number is 0706 485 2875");

                case 'web':
                    $this->delete_value("option");
                    return $this->result("end", "COVID AID REQUEST \nVisit https://covidaid.ng to register");

                case 'ussd':
                    return $this->ussd();

                default:

                    break;
            }

        } else {
            switch ($this->currentStep) {
                case 1:

                    return $this->result("continue", $this->render("menu"));

                case 2:
                    if ($this->lastInputed == 1) {

                        $this->set_value("option", 'ussd');
                        return $this->ussd();

                    } elseif ($this->lastInputed == 2) {

                        return $this->result("end", "COVID AID REQUEST \nVisit https://covidaid.ng to register");
                        
                    } 
                    // elseif ($this->lastInputed == 3) {

                        
                    //     return $this->result("end", "COVID AID REQUEST \nOur WhatsApp number is 0706 485 2875"); 

                    // }

                default:
                    # code...
                    break;
            }

        }

    }

    public function ussd()
    {

        $is_used = $this->checkMsisdn();
        switch ($this->currentStep) {
            case 2:
                if ($is_used->code == 400) {
                    $this->delete_value("option");
                    return $this->result("end", "COVID AID REQUEST \nYour phone number has already been registered. \nGo to https://covidaid.ng to track your delivery");
                } else if ($is_used->code == 200) {
                    return $this->result("continue", $this->render("name"));
                } else {
                    $this->delete_value("option");
                    return $this->result("end", "COVID AID REQUEST \nRequest Failed. \nPlease register on https://covidaid.ng instead");
                }
            case 3:
                $this->set_value("name", $this->lastInputed);
                return $this->result("continue", $this->render("street"));
            case 4:
                $this->set_value("street", $this->lastInputed);
                return $this->result("continue", $this->render("family_size"));
            case 5:
                $this->set_value("family_size", $this->lastInputed);
                $id = $this->generateTicketId();
                $ticket = $this->sendTicket($id);
                if ($ticket->message == 'Success') {
                    $this->delete_value("option");
                    return $this->result("end", "COVID AID REQUEST \nRequest Successful. \nYour ticket number is $id. Give it to the delivery person");
                } else if ($ticket->code == '400') {
                    $this->delete_value("option");
                    return $this->result("end", "COVID AID REQUEST \nYour phone number has already been registered. \nGo to https://covidaid.ng to track your delivery");
                } else {
                    $this->delete_value("option");
                    return $this->result("end", "COVID AID REQUEST \nRequest Failed. \nPlease register on https://covidaid.ng instead");
                }
        }
    }

    public function checkMsisdn()
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $postdata = array(
            'mobile_phone_number' => $msisdn,
            "type" => 'aid',
        );
        $url = "https://covidaid.ng/check";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer rZCZdS338ZAZ96UVkFEC8ktrWNRsUGKXP9m',
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

    public function sendTicket($otp)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $name = $this->get_value("name");
        $street = $this->get_value("street");
        $size = $this->get_value("family_size");

        $this->logRequest($name, $street, $size, $otp);

        $postdata = array(
            'mobile_phone_number' => $msisdn,
            "full_name" => $name,
            'street_address' => $street,
            "family_size" => $size,
            "otp" => $otp,
            "type" => 'aid',
        );
        $url = "https://covidaid.ng/data";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer rZCZdS338ZAZ96UVkFEC8ktrWNRsUGKXP9m',
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

    public function logRequest($name, $address, $size, $ticket_id)
    {
        $msisdn = '0' . substr($this->posted["msisdn"], -10);

        $query = "INSERT INTO noavji_covid_aid("
            . "name, msisdn, address, size, ticket_id) "
            . "VALUES (?,?,?,?,?)";
        try {
            R::exec($query, [$name, $msisdn, $address, $size, $ticket_id]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function generateTicketId()
    {
        return rand(100000, 900000);
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("apps/config/covidaid/aid.yml"));
        return $arr['pages'][$key];
    }

}
