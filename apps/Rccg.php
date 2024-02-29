<?php

include_once 'UssdApplication.php';
use Symfony\Component\Yaml\Yaml;

/**
 * @author Emmanuel/David
 */
class Rccg extends UssdApplication
{

    public function getResponse($body)
    {
        $this->initValues($body);

        switch ($this->currentStep) {
            case 1:

                return $this->result("continue", $this->render("menu"));

            case 2:
                if ($this->lastInputed == 1) {
                    $this->set_value("option", 'national');
                    return $this->national();
                } elseif ($this->lastInputed == 2) {
                    $this->set_value("option", 'parish');
                    return $this->parish();
                }
            default:
                if ($this->get_value('option') == 'national') {
                    return $this->national();
                } elseif ($this->get_value('option') == 'parish') {
                    return $this->parish();
                }
                break;
        }

    }

    public function national()
    {
        switch ($this->currentStep) {

            case 2:

                return $this->result("continue", $this->render("collection_type"));

            case 3:

                if (!in_array($this->lastInputed, ['1', '2', '3', '4', '5', '6'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('collection_type'));
                }
                $this->set_value("collection_type", $this->lastInputed);
                return $this->result("continue", $this->render("amount"));

            case 4:

                $amount = floatval(preg_replace("/[^-0-9\.]/", "", $this->lastInputed));
                if ($amount <= 0) {
                    return $this->cancelLastAction("You entered an invalid amount. \n" . $this->render('amount'));
                } else {
                    $this->set_value("amount", $amount);
                    return $this->result("Continue", $this->render("banks"));
                }

            case 5:

                if ($this->getBankCodes($this->lastInputed) == 'false') {

                    return $this->cancelLastAction("You entered a wrong option. \n" . $this->render('banks'));

                } else {

                    $this->set_value("bankId", $this->lastInputed);
                    $offeringId = $this->get_value('collection_type');
                    $amount = $this->get_value('amount');
                    $bankId = $this->lastInputed;
                    $institutionCode = $this->getInstitutionCode($this->lastInputed);
                    $donation = $this->getCollectionType($offeringId);
                    $ref = $this->getRef($offeringId);
                    $link = $this->getNationalBiller($offeringId, $bankId, $amount);
                    $linkUrl = $this->getNationalBillerU($offeringId, $bankId, $amount);
                    $msg = "Payment Details \nMerchant Name: Rccg $donation \nAmount: N$amount \nRef. No: $ref"."$amount \nDial $link# to complete payment.\nAn SMS with string to dial has also been sent to you";
                    $sms = "Welcome to RCCG Mobile World \nDial $linkUrl# to complete payment";
                    $this->sendSms($sms);
                    if ($this->posted["src"] == 'airtel' || $this->posted["src"] == 'Airtel') {

                        print_r($this->switchSession($ref.$amount,$institutionCode));
                        die();
                        return $this->result("End", "You will receive a USSD message from your bank to confirm payment. Press OK to continue");

                    }else {
                        return $this->result("End", $msg);
                    }

                }
            default:
                break;
        }
    }

    public function nationalCTD()
    {
        switch ($this->currentStep) {

            case 3:

                return $this->result("continue", $this->render("collection_type"));

            case 4:

                if (!in_array($this->lastInputed, ['1', '2', '3', '4', '5'])) {
                    return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('collection_type'));
                }
                $this->set_value("collection_type", $this->lastInputed);
                return $this->result("continue", $this->render("amount"));

            case 5:

                $amount = floatval(preg_replace("/[^-0-9\.]/", "", $this->lastInputed));
                if ($amount <= 0) {
                    return $this->cancelLastAction("You entered an invalid amount. \n" . $this->render('amount'));
                } else {
                    $this->set_value("amount", $amount);
                    return $this->result("Continue", $this->render("banks"));
                }

            case 6:

                if ($this->getBankCodes($this->lastInputed) == 'false') {

                    return $this->cancelLastAction("You entered a wrong option. \n" . $this->render('banks'));

                } else {

                    $this->set_value("bankId", $this->lastInputed);
                    $offeringId = $this->get_value('collection_type');
                    $amount = $this->get_value('amount');
                    $bankId = $this->lastInputed;
                    $institutionCode = $this->getInstitutionCode($this->lastInputed);
                    $donation = $this->getCollectionType($offeringId);
                    $ref = $this->getRef($offeringId);
                    $link = $this->getNationalBiller($offeringId, $bankId, $amount);
                    $linkUrl = $this->getNationalBillerU($offeringId, $bankId, $amount);
                    $msg = "Payment Details \nMerchant Name: Rccg $donation \nAmount: N$amount \nRef. No: $ref"."$amount \nDial $link# to complete payment.\nThanks for your Donation. An SMS with string to dial has also been sent to you";
                    $sms = "Welcome to RCCG Mobile World \nDial $linkUrl# to complete payment";
                    $this->sendSms($sms);
                    if ($this->posted["src"] == 'airtel' || $this->posted["src"] == 'Airtel') {
                        $this->switchSession($ref.$amount,$institutionCode);
             
                      return $this->result("End", "You will receive a USSD message from your bank to confirm payment. Press OK to continue");
                    }else {
                        return $this->result("End", $msg);
                    }
                    


                }

            default:
                break;
        }
    }

    public function parish()
    {
        switch ($this->currentStep) {
            case 2:

                return $this->result("continue", $this->render("parish_code"));

            case 3:
                $this->set_value("parish_code", $this->lastInputed);
                return $this->result("continue", $this->parishCTD());
  
            case 4:

                    if (!in_array($this->lastInputed, ['1', '2', '3', '4', '5'])) {
                        return $this->cancelLastAction("You entered an invalid option. \n" . $this->render('collection_type'));
                    }
                    $this->set_value("collection_type", $this->lastInputed);
                    return $this->result("continue", $this->render("amount"));
    
            case 5:
    
                    $amount = floatval(preg_replace("/[^-0-9\.]/", "", $this->lastInputed));
                    if ($amount <= 0) {
                        return $this->cancelLastAction("You entered an invalid amount. \n" . $this->render('amount'));
                    } else {
                        $this->set_value("amount", $amount);
                        return $this->result("Continue", $this->render("banks"));
                    }
    
            case 6:
    
                    if ($this->getBankCodes($this->lastInputed) == 'false') {
    
                        return $this->cancelLastAction("You entered a wrong option. \n" . $this->render('banks'));
    
                    } else {
    
                        $this->set_value("bankId", $this->lastInputed);
                        $offeringId = $this->get_value('collection_type');
                        $amount = $this->get_value('amount');
                        $bankId = $this->lastInputed;
                        $institutionCode = $this->getInstitutionCode($this->lastInputed);
                        $donation = $this->getCollectionType($offeringId);
                        $ref = $this->getRef($offeringId);
                        $link = $this->getNationalBiller($offeringId, $bankId, $amount);
                        $linkUrl = $this->getNationalBillerU($offeringId, $bankId, $amount);

                        $msg = "Payment Details \nMerchant Name: Rccg $donation \nAmount: N$amount \nRef. No: $ref"."$amount \nDial $link# to complete payment.\nThanks for your Donation. An SMS with string to dial has also been sent to you";
                        $sms = "Welcome to RCCG Mobile World \nDial $linkUrl# to complete payment";
                        $this->sendSms($sms);
                        if ($this->posted["src"] == 'airtel' || $this->posted["src"] == 'Airtel') {
                          $this->switchSession($ref.$amount,$institutionCode);
                          return $this->result("End", "You will receive a USSD message from your bank to confirm payment. Press OK to continue");
                        }else {
                            return $this->result("End", $msg);
                        }
                        
    
    
                    }
    
                default:
        }
    }

    public function parishCTD(){
        $parish_code = $this->get_value("parish_code");
        $username = 'Test';
        $password = 'Password2$';
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://testdev.coralpay.com/RccgParish/api/GetParish/{$parish_code}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic '. base64_encode("$username:$password")
            )
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return $err;
        }else{
            $result = json_decode($response);
            if($result->ResponseHeader->ResponseMessage === 'Succesful'){
                $parishName = $result->ParishName;
                return("Welcone to $parishName\n " . $this->render('collection_type'));
            }else{
                return("Invaid Credentials");
            }
            
        }
        

    }

    public function getCollectionType($offeringId)
    {
        $collections =
        array(
            "PARTNER75" => '1',
            "TITHE" => '2',
            "THANKSGIVING" => '3',
            "OFFERING" => '4',
            "FIRSTFRUIT" => '5',
            "NEW AUDITORIUM" => '6',
            // "RCCG FIRSTFRUIT OFFERING" => '6',
            // "RCCG HGS THANKSGIVING" => '7'
        );

        if (array_search($offeringId, $collections)) {

            return array_search($offeringId, $collections);

        } else {

            return 'false';

        }
    }

    public function getRef($offeringId)
    {
        $collections =
        array(
            "719+" => '1',
            "712+" => '2',
            "721+" => '3',
            "716+" => '4',
            "720+" => '5',
            "715+" => '6',
            // "720+" => '6',
            // "721+" => '7',
        );

        if (array_search($offeringId, $collections)) {

            return array_search($offeringId, $collections);

        } else {

            return 'false';

        }
    }

    public function getNationalBiller($offeringId, $bankId, $amount)
    {
        $bank_code = $this->getBankCodes($bankId);

        $biller_codes =
        array(
            $bank_code . '719' . '+' . $amount => "1",
            $bank_code . '712' . '+' . $amount => "2",
            $bank_code . '721' . '+' . $amount => "3",
            $bank_code . '716' . '+' . $amount => "4",
            $bank_code . '720' . '+' . $amount => "5",
            $bank_code . '715' . '+' . $amount => "6",
            // $bank_code . '720' . '+' . $amount => "6",
            // $bank_code . '721' . '+' . $amount => "7",
        );

        if (array_search($offeringId, $biller_codes)) {

            return array_search($offeringId, $biller_codes);

        } else {

            return 'false';

        }
    }

    public function getNationalBillerU($offeringId, $bankId, $amount)
    {
        $bank_code = $this->getBankCodes($bankId);

        $biller_codes =
        array(
            $bank_code . '719%2B'. $amount => "1",
            $bank_code . '712%2B'. $amount => "2",
            $bank_code . '721%2B'. $amount => "3",
            $bank_code . '716%2B'. $amount => "4",
            $bank_code . '720%2B'. $amount => "5",
            $bank_code . '715%2B'. $amount => "6",
            // $bank_code . '720%2B'. $amount => "6",
            // $bank_code . '721%2B'. $amount => "7",
        );

        if (array_search($offeringId, $biller_codes)) {

            return array_search($offeringId, $biller_codes);

        } else {

            return 'false';

        }
    }

    public function switchSession($ref,$bank_codes)
    {
    $msisdn = $this->posted["msisdn"];

        $data = json_encode([

            "clientReference" => bin2hex(mcrypt_create_iv(22, MCRYPT_DEV_URANDOM)),// unknown fields
            "sourceUssdCode" => "372",
            "sourceUssdString" => "*372*01#",
            "institutionUssdCode" => "$bank_codes", 
            "mno" => "AIRTEL",
            "msisdn" => $msisdn,
            "reference" => $ref,
            "destinationUssdString" => null
            
        ]);

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt_array($curl, array(
            CURLOPT_URL => "https://push.coralpay.com/push-service/api/ussd-push-requests/initiate-transfer",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/json",
                "Authorization: Basic ".base64_encode('novaji:n0v@j1@2$3&4')
            ),
            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                return 'Something Went Wrong';
            }else {
                return $response;
            }
    }


    public function getInstitutionCode($option)
    {   
        $bank_codes =
        array(
            "901" => '1',
            "326" => '2',
            "894" => '3',
            "329" => '4',
            "770" => '5',
            "737" => '6',
            "745"  => '7',
            "7111" => '8',
            "909" => '9',
            "822" => '10',
            "919" => '11',
            "7799" => '12',
            "5037" => '13',
            "945" => '14',
            "966" => '15',
          
        );
        
        if (array_search($option, $bank_codes)) {

            return array_search($option, $bank_codes);

        } else {

            return 'false';

        }

    }

    public function getBankCodes($option)
    {   
        $bank_codes =
        array(
            "*901*000*" => '1',
            "*326*000*" => '2',
            "*894*000*" => '3',
            "*329*000*" => '4',
            "*770*000*" => '5',
            "*737*000*" => '6',
            "*745*000*"  => '7',
            "*7111*000*" => '8',
            "*909*000*" => '9',
            "*822*000*" => '10',
            "*919*000*" => '11',
            "*7799*000*" => '12',
            "5037*000*" => '13',
            "*945*000*" => '14',
            "*966*000*" => '15',
          
        );
        
        if (array_search($option, $bank_codes)) {

            return array_search($option, $bank_codes);

        } else {

            return 'false';

        }

    }

    public function encrypt($data)
    {
        $encrypted = password_hash($data, PASSWORD_DEFAULT);
        return $encrypted;
    }

    public function validate($data, $msisdn)
    {
        $ret = R::getRow('select * from  coralpay_rccg_user_data where msisdn = ?', [$msisdn]);

        if (!empty($ret)) {
            if (password_verify($ret['password'], $hashed_password)) {
                return 'success';
            }
            return 'failed';
        }
        return 'This user does not exist';
    }

    public function sendSms($msg)
    {
        $msisdn = $this->posted["msisdn"];
        $message = rawurlencode($msg);
        file_get_contents("https://novajii.com/web/bulksms/all/send?network=etisalat&msg=$message&src=371&msisdn=$msisdn");
        file_get_contents("https://novajii.com/web/bulksms/all/send?network=airtel&msg=$message&src=371&msisdn=$msisdn");
        $this->sendMtnSMS($msg,$msisdn);
        // file_get_contents("https://novajii.com/web/bulksms/all/send?network=mtn&msg=$message&src=RCCG+Mobile&msisdn=$msisdn");
    }

    public function dbDataPusher($msisdn, $data)
    {

        $query = "INSERT INTO coralpay_rccg_user_data("
            . "msisdn, password) "
            . "VALUES (?,?)";
        try {
            R::exec($query, [$msisdn, $data]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function generateNationalRef($amount, $bankId, $product, $type)
    {
        $api_key = '98a371-35d142-7e10bf-16773c-7d0d92';
        $product = urlencode('Donation Collection');
        $bank_result = json_decode(file_get_contents("https://novajii.com/web/ussd-payment/api/generateRef?apikey=$api_key&bankId=$bankId&amount=$amount&channel=ussd&product=$product"));

        if ($bank_result->ResponseCode == '000') {

            return $this->result("continue", $bank_result->Description);

        }

    }

    public function getBank($bankId)
    {
        $bank_codes =
        array(
            "zenithbank" => '1',
            "gtb" => '2',
            "uba" => '3',
            "stanbicbank" => '4',
            "sterlingbank" => '5',
            "unitybank" => '6',
            "keystonebank" => '7',
            "fidelitybank" => '8',
            "ecobank" => '9',
            "wemabank" => '10',
            "accessbank" => '11',
            "firstbank" => '12',
        );

        if (array_key_exists($bankId, $bank_codes)) {

            return array_search($bankId, $bank_codes);

        } else {

            return 'false';

        }

    }

    public function sendMtnSMS($msg, $to)
    {
        $route = "2";
        $token = "VIl2IGW6Pm82sRvYLypmrJa1ipdWQrg4BOAbhL7jFrnqivi7Ti1zzqPBI1ObpaHKUh6r3W3wbYqiR6bzpHHMkta4sr2HaXe3MY7V";
        $type = "0";
        $sender = "RCCG";
        $message = urlencode(urldecode($msg));
        return file_get_contents("https://smartsmssolutions.com/api/json.php?message=$message&to=$to&sender=$sender&type=$type&routing=$route&token=$token");
    }

    public function render($key)
    {
        $arr = Yaml::parse(file_get_contents("apps/config/rccg/config.yml"));
        return $arr['pages'][$key];
    }
}
