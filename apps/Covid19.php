<?php

include_once 'UssdApplication.php';
require_once 'vendor/autoload.php';
require_once 'predis/src/Autoloader.php';

use Symfony\Component\Yaml\Yaml;

class Covid19 extends UssdApplication
{

    private $max_steps;

    public function __construct()
    {
        # some initiailization
        $this->max_steps = 12;
    }

    public function getResponse($body)
    {
        //global $redis;
        # always first thing to do
        $this->initValues($body);
        # validate entry before showing content
        # we need 13 covid:answers[0-12]
        for ($i = 0; $i <= $this->max_steps; $i++) {
            if (!$this->value_exists("covid:ans:$i")) {
                if (!$this->valid_entry($this->lastInputed)) {
                    return $this->result('continue', $this->display($i));
                }
                $this->set_value("covid:ans:$i", $this->lastInputed);
                if ($this->value_exists("covid:ans:{$this->max_steps}")) {
                    #this is the end;
                    # first compute result
                    return $this->result('continue', $this->compute_score());
                }
                if ($this->value_exists("covid:ans:$i") && $this->valid_entry($this->get_value("covid:ans:$i"))) {
                    # show the next page
                    #if ($i == 0) {
                    #    return $this->result('continue', $this->display($i + 1));
                    #}
                    return $this->result('continue', $this->display($i + 1));
                } else {
                    return $this->result('continue', $this->display($i));
                }
            }
        }
    }

    private function begin_again()
    {
        $this->clear_values();
        $this->set_value("covid:ans:1", $this->lastInputed);
        return $this->result('continue', $this->display(1));
    }

    private function compute_score()
    {
        $cough = $this->get_value("covid:ans:1") == 1 ? 1 : 0;
        $colds = $this->get_value("covid:ans:2") == 1 ? 1 : 0;
        $diarrhea = $this->get_value("covid:ans:3") == 1 ? 1 : 0;
        $sore_throat = $this->get_value("covid:ans:4") == 1 ? 1 : 0;
        $body_aches = $this->get_value("covid:ans:5") == 1 ? 1 : 0;
        $head_aches = $this->get_value("covid:ans:6") == 1 ? 1 : 0;
        $fever = $this->get_value("covid:ans:7") == 1 ? 1 : 0;
        $breathing_difficulty = $this->get_value("covid:ans:8") == 1 ? 2 : 0;
        $fatigue = $this->get_value("covid:ans:9") == 1 ? 2 : 0;
        $recently_travelled = $this->get_value("covid:ans:10") == 1 ? 3 : 0;
        $travel_history = $this->get_value("covid:ans:11") == 1 ? 3 : 0;
        $direct_contact = $this->get_value("covid:ans:12") == 1 ? 3 : 0;
        $result = $this->sendResult($cough, $colds, $diarrhea, $sore_throat, $body_aches, $head_aches, $fever, $breathing_difficulty, $fatigue, $recently_travelled, $travel_history, $direct_contact);
        //$recommendation = $this->get_recommendation($score);

        if ($result->code == '000' && $result->status == 'success') {
            $this->clear_values();
            $score = $result->score;
            $recommendation = $result->advise;
            $ehdata = $this->sendData($cough, $colds, $diarrhea, $sore_throat, $body_aches, $head_aches, $fever, $breathing_difficulty, $fatigue, $recently_travelled, $travel_history, $direct_contact, $recommendation, $score);

            if ($ehdata->success == true) {
                return "Your Covid-19 Checklist Result\n"
                    . "Score: $score\n"
                    . "Advice: $recommendation";
            } else {
                return "Covid-19 Checklist\n"
                    . "Something went wrong, Kindly tru again\n";

            }

        }

    }

    public function sendData($cough, $colds, $diarrhea, $sore_throat, $body_aches, $head_aches, $fever, $breathing_difficulty, $fatigue, $recently_travelled, $travel_history, $direct_contact, $advice, $score)
    {
        $time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $datetime = date("D-M-Y") . " " . $time->format('H.i.s');
        $postdata = array(
            'resource_id' => 'fbe5e657-ee04-4cba-95e0-d2671f24e688',
            'force' => 'true',
            'method' => 'insert',
            'records' => [
                [   
                    'ID' => '1',
                    'MOBILE_NUMBER' => $this->posted["msisdn"],
                    'COUGH' => $cough,
                    'COLD' => $colds,
                    'DIARRHEA' => $diarrhea,
                    'SORE_THROAT' => $sore_throat,
                    'BODY_ACHE' => $body_aches,
                    'HEAD_ACHE' => $head_aches,
                    'FEVER' => $fever,
                    'FATIGUE' => $fatigue,
                    'BREATHING_DIFFICULTY' => $breathing_difficulty,
                    'RECENTLY_TRAVELLED' => $recently_travelled,
                    'COVID_TRAVEL_HISTORY' => $travel_history,
                    'COVID_CONTACT' => $direct_contact,
                    'CHANNEL' => 'ussd',
                    'SCORE' => $score,
                    'NETWORK' => $this->posted["src"],
                    'RESULT' => $advice,
                    'DURATION' => '60',
                    'STATE' => 'Lagos',
                    'CREATED_AT' => $datetime,
                ],
            ],
        );
        $url = "ehdata2.tinitop.com/api/3/action/datastore_upsert?resource_id=fbe5e657-ee04-4cba-95e0-d2671f24e688";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: b3ad60df-9dd2-4d5a-82f9-617b34c6fc34',
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

    public function sendResult($cough, $colds, $diarrhea, $sore_throat, $body_aches, $head_aches, $fever, $breathing_difficulty, $fatigue, $recently_travelled, $travel_history, $direct_contact)
    {

        $msisdn = '0' . substr($this->posted["msisdn"], -10);
        $postdata = array(
            'apikey' => 'xsgjuyygga',
            'mobile_number' => $msisdn,
            'cough' => $cough,
            'cold' => $colds,
            'diarrhea' => $diarrhea,
            'sore_throat' => $sore_throat,
            'body_ache' => $body_aches,
            'head_ache' => $head_aches,
            'fever' => $fever,
            'fatigue' => $fatigue,
            'breathing_difficulty' => $breathing_difficulty,
            'recently_travelled' => $recently_travelled,
            'covid_travel_history' => $travel_history,
            'covid_contact' => $direct_contact,
            'channel' => 'ussd',
            'mno' => $this->posted["src"],
        );
        $url = "https://novajii.com/ords/covid19/api/response";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            //'Authorization: Bearer rZCZdS338ZAZ96UVkFEC8ktrWNRsUGKXP9m',
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

    private function get_recommendation($score)
    {
        if ($score >= 12) {
            return "Call the DOH Hotline 02-8-651-7800";
        }
        if ($score >= 6) {
            return "Seek a consultation with Doctor";
        }
        if ($score >= 3) {
            return "Hydrate properly and observe proper personal hygeine\n"
                . "Observe and revaluate after 2 days";
        }
        return "May be stress related. Continue to observe";
    }

    private function nvl($val)
    {
        return isset($val) ? $val : 0;
    }

    // help us render a display to ussd menu
    private function display($key)
    {
        $config_file = "config/covid19/covid19.yml";
        $arr = Yaml::parse(file_get_contents($config_file));
        $pages = $arr['step'];
        return $pages[$key];
    }

    # accept onlt 1,2 by user for all steps except the first step

    private function valid_entry($input)
    {
        return in_array($input, ["1", "2", "*371*19#", "*371*19"]);
    }

    private function clear_values()
    {
        for ($i = 0; $i <= $this->max_steps; $i++) {
            $this->delete_value("covid:ans:$i");
        }
    }

}
