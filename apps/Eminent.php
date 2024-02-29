<?php

include_once 'UssdApplication.php';

class Eminent extends UssdApplication {

    public function getResponse($body) {
        $this->initValues($body);

        if ($body['src'] == 'flash') {
            switch($this->currentStep) {
                case 1:
                    $this->result('end', 'Welcome to Eminent payment platform');
                    break;
            }
        }
    }

}
