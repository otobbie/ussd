<?php

require_once 'init.php';
include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';

use Unirest\Request as Unirest;
use Unirest\Request\Body as Body;

class Soa extends UssdApplication {

//put your code here
    public function getResponse($body) {
        $telco = isset($body['src']) ? $body['src'] : '9mobile';
        if ($telco === 'etisalat') {
            $telco = '9mobile';
        }
        $commandID = Ussd::getCommand($body['commandID']);
        $action = ($commandID === 'BEGIN') ? 'Begin' : 'Continue';
        $data = [
            "command" => $action,
            "content" => $body['content'],
            "msisdn" => $body['msisdn'],
            "src" => $telco,
            'serviceCode' => $body['serviceCode']];

        $headers = array('accept' => 'application/json');
        $post = Body::Json($data);
        //die($post);
        //Unirest::verifyPeer(false);
        $response = Unirest::post("http://ahab-reactor.herokuapp.com/gateway/USSDServer", $headers, $post);
        // $response = Unirest::post("http://gamaliel-dev-as.azurewebsites.net/Gateway/USSDServer", $headers, $post);
        #$this->write_log($response->raw_body);
        $msg = 'Invalid response from Soa';
        if ($response && $response->raw_body) {
            $json = json_decode($response->raw_body);

            return array('action' => (string) $json->command,
                'message' => (string) $json->content);

            /*
              return array('action' => "Continue",
              'message' => $json->content);
             * 
             */
        }
        return array('action' => 'Continue',
            'message' => $msg);
        /*

         * 
         */
    }

    public function write_log($log) {
        $content = date('Y-m-d h:i:s') . " | $log";
        file_put_contents('/var/www/html/novajii/web/logs/soa_' . date("Y-m-d") . '.log', $content . PHP_EOL, FILE_APPEND);
    }

}
