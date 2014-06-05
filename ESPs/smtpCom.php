<?php

class SmtpCom extends ESP
{
    const SMTP_HOST = "smtp.com";
    const SMTP_PORT = "2525";


    protected $host          = null;
    protected $port          = null;
    protected $user          = null;
    protected $password      = null;
    
    public function __construct() {
        parent::__construct();
        $this->host = self::SMTP_HOST;
        $this->port = self::SMTP_PORT;
                
    }

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
        return $this->host;
    }
    
    public function getPort() {
        return $this->port;
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