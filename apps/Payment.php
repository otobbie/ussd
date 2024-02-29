<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace apps;

/**
 * Description of Payment
 *
 * @author jo
 */
class Payment {

    public function pay($body, $amt, $service_code, $extra = null) {
        # make 3 attempts to push payment
        $attempts = 1;
        /*
        $msg = null;
        for ($i = 0; $i <= $attempts; $i++) {
            
        }
         * 
         */
        return $this->pushPayment2($body, $amt, $service_code, $extra);
        #return $msg;
    }

    private function pushPayment($msisdn, $amt, $telco, $service_code, $extra = null) {

        $msg = "You have entered an incorrect amount. Please try again";
        if (is_numeric($amt)) {

            $url = "http://notification.novajii.com/web/vas2nets/api/v1/live/payment";
            $telco = isset($body['src']) ? $body['src'] : '9mobile';
            if ($telco === 'etisalat') {
                $telco = '9mobile';
            }

            $parameters = array('phone' => $msisdn, 'amount' => $amt, 'telco' => $telco, 'service_code' => $service_code);

            if ($extra != null) {
                $parameters['extra'] = $extra;
            }

            $data = json_encode($parameters);
            /*
              if ($extra != null) {
              $parameters['extra'] = $extra;
              }
             */
            file_put_contents('/var/www/html/ussd/apps/payment_' . date("Y-m-d") . '.log', $data);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    "cache-control: no-cache",
                    "content-type: application/json"
                ),
            ));

            $response = curl_exec($curl);

            $err = curl_error($curl);

            curl_close($curl);


            if ($err) {
                #echo "cURL Error #:" . $err;
                $this->log($err);
                $msg = "Error, Unable to process payments now. Please try again later";
            }
            if ($response && (preg_match('/referenceid/', $response))) {
                # we got a response with a sessionid, so everything went well
                $msg = "Payment processing will start shortly..please wait";
                #$result = json_decode($response);
                #$msg = $result->status;
            } else {
                $this->log($response);
                $msg = "Unable to process payments now. Please try again later";
            }

            return $msg;
        }
    }

    private function pushPayment2($body, $amt, $service_code, $extra = null) {
        # here we should call payment API of 565
        # split the dialed content

        $msg = "You have entered an incorrect amount. Please try again";
        if (is_numeric($amt)) {
            $url = "http://notification.novajii.com/web/vas2nets/api/v1/live/payment";
            $telco = isset($body['src']) ? $body['src'] : '9mobile';
            if ($telco === 'etisalat') {
                $telco = '9mobile';
            }
            $parameters = ['phone' => $body['msisdn'],
                'telco' => strtoupper($telco), 'amount' => $amt, 'service_code' => $service_code];
            if ($extra != null) {
                $parameters['extra'] = $extra;
            }
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($parameters),
                CURLOPT_HTTPHEADER => array(
                    "cache-control: no-cache",
                    "content-type: application/json"
                ),
            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                #echo "cURL Error #:" . $err;
                $this->log($err);
                $msg = "Unable to process payments now. Please try again later";
            }
            if ($response) {
                # we got a response with a sessionid, so everything went well
                #
                
                 $result = json_decode($response);
                # check we have something returned from the provider
                $msg = (($result->status == "00")) ? "Payment processing will start shortly..please wait" : "Payments not availabe now. Please try again later";
            } else {
                $this->log($response);
                $msg = "Unable to process payments now. Please try again later";
            }
            #return $telco;
            return $msg;
        }
    }

    public
            function log($info) {
        file_put_contents('/var/www/html/ussd/apps/payment_' . date("Y-m-d") . '.log', $info);
    }

}
