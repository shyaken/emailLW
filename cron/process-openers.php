<?php

require_once dirname(__FILE__) . '/../email.php';

Cron::checkConcurrency('process-openers');

$db    = new Database;
$esp   = new Dynect;
$adnet = new ADKI;

$senderId     = Config::DEFAULT_SENDER;
$channel      = Config::DEFAULT_CHANNEL;
$senderEmail  = Sender::getNameById($senderId) . '@' . Sender::getDomainById($senderId);
$senderDomain = Sender::getDomainById($senderId);
$footerId     = Sender::getFooterIdById($senderId);
$footer       = new Footer($footerId);

$leadAttributes = array(
    'countOnly'  => false,
    'queryName'  => 'cronopeners',
    'count'      => Config::MAX_BATCH_SIZE,
    'interval'   => Config::INTERVAL_OPENER,
    'type'       => 'openers',
    'minScore'   => '1',
    'campaignId' => NULL,
    'tldList'    => false,
    'gender'     => NULL,
    'country'    => NULL,
    'state'      => NULL
);

$leads = Lead::getLeads($leadAttributes);

if (empty($leads)) {
    Locks_Cron::removeLock('process-openers');
    die("No openers to process");
}

$leadData = Builder::buildLeadData($leads);
$emails = $adnet->buildPayload($leadData);

$adnet->send($emails);
$adnet->getResult();

if ($adnet->getLastStatus() != 'success') {
    Locks_Cron::removeLock('process-openers');
    die('AdNet status != success');
}

$adnetData = $adnet->buildArrayByRecipientKey($leadData);

if (empty($adnetData)) {
    Locks_Cron::removeLock('process-openers');
    die ('adnetData empty');
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

    $unsubUrl = HTML::getUnsub(Config::$subdomains['clicks'], $senderDomain, $data['email'], $subId);

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

Locks_Cron::removeLock('process-openers');