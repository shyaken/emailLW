<?php

class SmtpCom extends ESP
{

    public function getRestAuthParams()
    {
        return array(
            'apikey'  => $this->getApiKey()
        );
    }
    //--------------------------------------------------------------------------


    public function getName()
    {
        return 'Smtp.com E-Mail Delivery';
    }
    //--------------------------------------------------------------------------


    public function getRestUrl()
    {
        return 'http://emailapi.dynect.net/rest/';
    }
    //--------------------------------------------------------------------------


    public function sendEmail($to, $fromPerson, $fromEmail, $subject, $bodyHtml, $bodyText, $subId, $unsubUrl, $debug = false)
    {
        
    }
    //--------------------------------------------------------------------------


    public function setStatusFromReturnCode($code)
    {
        
    }

}