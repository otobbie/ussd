<?php

include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';
#include_once 'apps/classes/assotel/Bills.php';
#include_once 'apps/classes/assotel/Airtime.php';
#include_once 'apps/classes/assotel/Api.php';
include_once 'Mcash.php';
#include_once 'Payment.php';

use Symfony\Component\Yaml\Yaml;
use Predis\Client as RedisClient;

/**
 * Description of Assotel
 *
 * @author Chuks
 */
class Assotel extends UssdApplication {

//put your code here
    private $arr;
    private $serviceCode = 'assotel';
    private $amt;
    private $src;
    private $dst;
    private $network;
    private $body;
    private $activity;
    private $redis;
    private $client_id;
    private $secret;
    private $merchant_code;
    private $bank_id;
    private $active_msisdn;
    private $test_amount;

    public function getResponse($body) {
        $this->initValues($body);
        $this->redis = new RedisClient();
        $this->src = $body["msisdn"];
        $this->client_id = "0xACEVxi";
        $this->secret = "REDuBrNXq86OKt4AkwSp7xC5";
        $this->merchant_code = "77000701";
        #$this->active_msisdn = "2347066192100";
        # 07069341985
        $this->active_msisdn = $body["msisdn"];
        # 08169043369
        $this->test_amount = "50";
        switch ($this->currentStep) {
            case 1:
                /* entry, if *372# then ask for network, and amount */
                $this->clear_all();
                $list = $this->explode_input($body);
                $tokens = count($list);
                #return $this->result("End", "$tokens tokens found");
                switch ($tokens) {
                    case 1:
                        /*
                         * User dialed *372#
                         * Entry point: choose, self or others
                         * 
                         */

                        return $this->result("continue", $this->render("airtime"));
                    case 2:
                        /*
                         * User dialed *372*Amount#
                         * Top up for self, select your bank
                         */
                        $this->persist_value("vend:type", "self");
                        $this->persist_value("vend:amt", $list[1]);
                        $this->persist_value("vend:dst", $this->src);
                        $this->persist_value("vend:network", $body["src"]);
                        return $this->select_bank($this->src);
                    case 3:
                        /*
                         * User dialed *372*Amount*Mobile Number#
                         * Top up others,
                         * Persist values and then select network 
                         */
                        $this->dst = $list[2];
                        $this->persist_value("vend:type", "other");
                        $this->persist_value("vend:amt", $list[1]);
                        $this->persist_value("vend:dst", $this->dst);
                        return $this->result("continue", $this->render("select_network"));

                    default:
                        return $this->result("continue", $this->render("airtime"));
                }
            default:
                /* if type is not set, then set it, then enter amount */
                if (!$this->value_exists("vend:type")) {
                    $this->persist_value("vend:type", $this->get_vend_type($this->lastInputed));
                    if (!$this->value_exists("vend:amt")) {
                        return $this->result("continue", $this->render("amount"));
                    }
                }

                /*
                 * after amount entered, check and collect the 
                 * destination number
                 */
                if (!$this->value_exists("vend:amt")) {
                    $this->persist_value("vend:amt", $this->lastInputed);
                    if (!$this->value_exists("vend:dst")) {
                        return $this->result("continue", $this->render("mobile"));
                    }
                }
                /*
                 *  show network after amount if not set
                 */
                if (!$this->value_exists("vend:dst")) {
                    $this->persist_value("vend:dst", $this->get_vend_type($this->lastInputed));
                    if (!$this->value_exists("vend:network_id")) {
                        return $this->result("continue", $this->render("select_network"));
                    }
                }
                /*
                 * show bank options after setting network
                 */
                if (!$this->value_exists("vend:dst")) {
                    $this->persist_value("vend:dst", $this->lastInputed);
                    if (!$this->value_exists("vend:select_bank")) {
                        return $this->select_bank();
                    }
                }

                /*
                 * set bank option and bankId, 
                 * then pay with Bank
                 */
                if (!$this->value_exists("vend:select_bank")) {
                    $this->persist_value("vend:select_bank", $this->lastInputed);
                    $this->bank_id = $this->get_bank_id();
                    /* pay with bank */
                    return $this->pay_with_bank();
                    /*
                     * return $this->result("End", 
                     * "You will be prompted to authorize this payment in a few seconds..");
                     * 
                     */
                }
        }
    }

    public function persist_value($key, $value) {
        $id = $this->active_msisdn . ":$key";
        $this->redis->set($id, $value);
        $this->redis->expire($id, 60);
    }

    public function get_value($key) {
        $id = $this->active_msisdn . ":$key";
        return $this->redis->get($id);
    }

    public function value_exists($key) {
        $id = $this->active_msisdn . ":$key";
        return $this->redis->exists($id);
    }

    public function select_bank() {
        $mcash = new Mcash($this->client_id, $this->secret, $this->merchant_code);
        $amt = $this->get_value("vend:amt");
        return $mcash->prepayment($this->active_msisdn,$amt);
    }

    private function get_bank_id() {
        $msisdn = $this->active_msisdn;
        $mcash = new Mcash($this->client_id, $this->secret, $this->merchant_code);
        return $mcash->get_bank_id($msisdn, $this->lastInputed);
    }

    private function pay_with_bank() {
        $mcash = new Mcash($this->client_id, $this->secret, $this->merchant_code);
        $notes = "<vend><msisdn>" . $this->get_value("vend:dst")."</msisdn><network>".$this->get_value("vend:network")."</network></vend>";
        $this->bank_id = $this->get_bank_id();
        return $mcash->pay_with_bank($this->active_msisdn, $this->bank_id, $notes);
    }

    public function clear_all() {
        $this->delete_value("vend:type");
        $this->delete_value("vend:amt");
        $this->delete_value("vend:dst");
        $this->delete_value("vend:network");
        $this->delete_value("vend:select_bank");
        $this->delete_value("vend:network_id");
    }

    public function delete_value($key) {
        $id = $this->active_msisdn . ":$key";
        $this->redis->del($id);
    }

    public function get_vend_type($input) {
        $types = ["1" => "self", "2" => "other"];
        return $types[$input];
    }

    public function explode_input($body) {
        $val = str_replace(['*', '#'], ['/', ''], $body['content']);
        $var = explode('/', $val);
# the first values in array is empty, push it out
        array_shift($var);
        return $var;
    }

    public function render($key) {
        $arr = Yaml::parse(file_get_contents("config/assotel/assotel.yml"));
        return $arr[$key];
    }

    public function is_airtime_amount($amt) {
        return is_numeric($amt) && ($amt >= 20);
    }

    public function get_product_codes() {
        return [1, 2, 3, 4, 5, 6];
    }

    public function get_network($src) {
        if ($src == 'etisalat') {
            return '9mobile';
        }
        return $src;
    }

    public function networks() {
        return ["1" => "MTN", "2" => "Airtel", "3" => "Glo", "4" => "9Mobile"];
    }

}

