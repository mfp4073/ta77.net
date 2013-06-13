<?php

class ErrorController extends Am_Controller
{
    public function errorAction()
    {
        $this->render('error.phtml');
    }
}