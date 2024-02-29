<?php

$url = "http://notification.novajii.com/web/vas2nets/api/v1/live/payment";
$telco = "Airtel";
$msisdn = "2348023393461";
$amt = "100";
$service_code = "Assotel";


$parameters = array('phone' => $msisdn, 'amount' => $amt, 'telco' => $telco, 'service_code' => $service_code);

$data = json_encode($parameters);

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

$res = json_decode($response);

echo $res;

