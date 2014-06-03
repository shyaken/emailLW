<?php

require_once dirname(__FILE__) . '/../email.php';

Cron::checkConcurrency('process-clickers-gb');

$db   = new Database;
$esp  = new Dynect;
$adki = new ADKI;
//$ob   = new OBMedia;

$senderId     = Config::DEFAULT_SENDER;
$channel      = Config::DEFAULT_CHANNEL;
$senderEmail  = Sender::getNameById($senderId) . '@' . Sender::getDomainById($senderId);
$senderDomain = Sender::getDomainById($senderId);
$footerId     = Sender::getFooterIdById($senderId);
$footer       = new Footer($footerId);

$leadAttributes = array(
    'countOnly'  => false,
    'queryName'  => 'crongbclickers',
    'count'      => Config::MAX_BATCH_SIZE,
    'interval'   => Config::INTERVAL_CLICKER,
    'type'       => 'clickers',
    'minScore'   => '1',
    'campaignId' => NULL,
    'tldList'    => false,
    'gender'     => NULL,
    'country'    => array('GB'),
    'state'      => NULL
);

$leads = Lead::getLeads($leadAttributes);

if (empty($leads)) {
    Locks_Cron::removeLock('process-clickers-gb');
    die("No clickers to process");
}

$leadData = Builder::buildLeadData($leads);

//$adkiData = array_slice($leadData, 0, count($leadData) / 2);
//$obData   = array_slice($leadData, count($leadData) / 2);

$adkiData = $leadData;

$adnetData = array();

if (!empty($adkiData)) {
    $adkiEmails = $adki->buildPayload($adkiData, 18579);
    $adki->send($adkiEmails);
    $adki->getResult();
    $adnetData = $adki->buildArrayByRecipientKey($adkiData);
}

//if (!empty($obData)) {
//    $obEmails = $ob->buildPayload($obData);
//    $ob->send($obEmails);
//    $ob->getResult();
//    $adnetData = array_merge($adnetData, $ob->buildArrayByRecipientKey($obData));
//}

if (empty($adnetData)) {
    Locks_Cron::removeLock('process-clickers-gb');
    die('AdNet arrays empty; nothing to send!');
}

foreach ($adnetData AS $data) {
    if (empty($data['subject'])) {
        continue;
    }

    Lead::setLock($data['email']);
    $subId = Logging::logActivity($data['email'], 0, 1, $senderEmail, $channel, $data['categoryId']);
    HTML::doEncoding($data['email'], $subId, $senderDomain, $data['htmlBody'], $data['textBody']);

    if (!empty($data['htmlBody'])) {
        HTML::addHtmlFooter($data['email'], $subId, $senderDomain, Config::$subdomains['clicks'], $data['htmlBody'], $footer);
        HTML::addHtmlPixel($data['email'], $subId, $senderDomain, $data['htmlBody']);
    }

    if (!empty($data['textBody'])) {
        HTML::addTextFooter($data['email'], $subId, $senderDomain, Config::$subdomains['clicks'], $data['textBody'], $footer);
    }

    $unsubUrl = HTML::getUnsub(Config::$subdomains['clicks'], $senderDomain, $_GET['email'], $subId);

    $esp->sendEmail($data['email'], $data['friendlyFrom'], $senderEmail, $data['subject'], $data['htmlBody'], $data['textBody'], $subId, $unsubUrl);

    if ($esp->getLastStatus() == 'sent') {
        Lead::scoreSend($data['email']);
    } else {
        if (isset($subId) && is_numeric($subId) && $subId > 0) {
            Logging::removeActivity($subId);
        }
    }

    Lead::removeLock($data['email']);
}

Locks_Cron::removeLock('process-clickers-gb');
