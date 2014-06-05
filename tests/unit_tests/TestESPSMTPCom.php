<?php

set_time_limit(0);
$skipOtherTests = 1;
defined('RUN_ALL_TESTS') or require_once '../tests.php';

class TestESPSMTPCom extends UnitTestCase
{

    public function testSMTPCom_getUsername()
    {
        $dynect = new SmtpCom;

        $this->assertEqual($dynect->getUsername(),Config::$espCredentials['smtpCom']['username']);
    }
    //--------------------------------------------------------------------------


    public function testSMTPCom_getPassword()
    {
        $dynect = new SmtpCom;

        $this->assertEqual($dynect->getPassword(),Config::$espCredentials['smtpCom']['password']);
    }
    //--------------------------------------------------------------------------


    public function testDynect_getApiKey()
    {
        $dynect = new Dynect;

        $this->assertEqual($dynect->getApiKey(), Config::$espCredentials['smtpCom']['apikey']);
    }
    //--------------------------------------------------------------------------

    public function testSMTPCom_getName()
    {
        $dynect = new SmtpCom;

        $this->assertEqual($dynect->getName(),'Smtp.com E-Mail Delivery');
    }
    //--------------------------------------------------------------------------


    public function testSMTPCom_sendEmail()
    {
        $api = new SmtpCom;
        
        $fromPerson = "Jason Hart";
        $fromEmail  = Config::$fromDomains[0]['sender'] . "@" . Config::$fromDomains[0]['domain'];
        $api->sendEmail(Config::$emailTests, $fromPerson, $fromEmail, 'Email System Test - Smtp.com', 'Testing the Email system', '500', '', false);
        $this->assertEqual($api->getLastStatus(),'sent');
    }
    //--------------------------------------------------------------------------

}