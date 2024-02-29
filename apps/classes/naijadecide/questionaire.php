<?php

include_once 'lib/classes/rb.php';
include_once 'apps/UssdApplication.php';
include_once 'apps/classes/easytaxpayer/SendSms.php';

class Questionaire {

    public static function getQuestion($msisdn) {
        try {
            UssdApplication::connect();
            $ret = R::findOne("novaji_vas_subscriptions", "msisdn = ? and service_id = ?", [$msisdn, 2]);

            if (!$ret) {
                try {
                    $sql = "INSERT INTO novaji_vas_subscriptions("
    		            . "msisdn, subscriptiondate, expiredate, notificationdate, service_id, mno) "
    		            . "VALUES (?,?,?,?,?,?)";

                    R::exec($sql, [$msisdn, date('Y-m-d H:i:s'), date('Y-m-d', strtotime("+7 days")), date('Y-m-d', strtotime("+5 days")), 2, "etisalat"]);
                } catch (Exception $e) {
                    return $e->getMessage();
                }
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "http://notification.novajii.com/web/naijadecides/weekquestion",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode(['msisdn' => $msisdn]),
                CURLOPT_HTTPHEADER => array(
                    "api-key: AKwPJMEM5ytgJyJyGqoD5FQwxv82YvMr",
                    "cache-control: no-cache",
                    "token: duoRF6gAawNOEQRICnOUNYmStWmOpEgS"
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            $errMsg = 'No question found. Dial *371*4# to try again';

            if ($err) {
                return "cURL Error #:" . $err;
            } else {
                $str = json_decode($response, true)['question'];
                $options = json_decode($response, true)['option'];

                if (strpos($str, 'Sorry') !== false) {
                    $errMsg = $str;
                    $str = null;
                }

                if (!empty($options)) {
                    foreach($options as $opt) {
                        $str .= "\n" . $opt['sn'] .". ". $opt['value'];
                    }
                }

                return empty($str) ? ['code' => 1, 'message' => $errMsg] : ['code' => 0, 'message' => $str];
            }
        } catch (Exception $e) {
            $e->getMessage();
        }
    }

    public static function postResponse($option, $msisdn) {
        try {

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "http://notification.novajii.com/web/naijadecides/getanswer",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode(['msisdn' => $msisdn, 'answer' => $option]),
                CURLOPT_HTTPHEADER => array(
                    "api-key: AKwPJMEM5ytgJyJyGqoD5FQwxv82YvMr",
                    "cache-control: no-cache",
                    "token: duoRF6gAawNOEQRICnOUNYmStWmOpEgS"
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                return "cURL Error #:" . $err;
            } else {
                SendSms::sendSmsMessage(json_decode($response)->message, $msisdn);
                return json_decode($response)->message;
            }
        } catch (Exception $e) {
            $e->getMessage();
        }
    }

}
