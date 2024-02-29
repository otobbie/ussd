<?php

include_once 'UssdApplication.php';

class Unknown extends UssdApplication {
    public function getResponse($body) {
        return array('action'=>'End',
            'message'=>strtoupper('Sorry, an error occured. Please try again later.'));
    }

}
