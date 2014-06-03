<?php

require_once dirname(__FILE__) . '/../email.php';

Cron::checkConcurrency('process-campaigns');

$db    = new Database;
$esp   = new Dynect;

$channel      = Config::DEFAULT_CHANNEL;

if (!Campaign::getRandomCampaign()) {
    Locks_Cron::removeLock('process-campaigns');
    die();
}

$retries = 0;

while ($retries < Config::MAX_CRON_RETRIES) {
    $campaignId = Campaign::getRandomCampaign();

    if (is_numeric($campaignId)) {
        $campaign = new Campaign($campaignId);

        $campaignLimit = ($campaign->getSendLimit() - $campaign->getSentCount());

        if ($campaignLimit >= Config::MAX_BATCH_SIZE) {
            $campaignLimit = Config::MAX_BATCH_SIZE;
        } else if ($campaignLimit < 0) {
            $campaignLimit = 0;
        }

        $leadAttributes = unserialize($campaign->getAttributes());
        $leadAttributes['count'] = $campaignLimit;

        $leads = Lead::getLeads($leadAttributes);

        if (empty($leads)) {
            $retries++;
        } else {
            $retries = Config::MAX_CRON_RETRIES;
        }
    }
}

if (empty($leads) || empty($campaignId)) {
    Locks_Cron::removeLock('process-campaigns');
    die("No leads to process");
}

foreach ($leads AS $lead) {

$creativeId  = Random::getRandomCreativeId(unserialize($campaign->getCreativeIds()));

    if (!empty($creativeId)) {
        $creative = new Creative($creativeId);

        $senderId     = $creative->getSenderId();
        $senderEmail  = Sender::getNameById($senderId) . '@' . Sender::getDomainById($senderId);
        $senderDomain = Sender::getDomainById($senderId);
        $footerId     = Sender::getFooterIdById($senderId);
        $footer       = new Footer($footerId);

        Lead::setLock($lead['email']);
        $subId = Logging::logActivity($lead['email'], $campaignId, $creativeId, $senderEmail, $channel, $creative->getCategoryId());

        $htmlBody = $creative->getHtmlBody(array('subid' => $subId));
        $textBody = $creative->getTextBody(array('subid' => $subId));
        HTML::doEncoding($lead['email'], $subId, $senderDomain, $htmlBody, $textBody);

        if (!empty($htmlBody)) {
            HTML::addHtmlFooter($lead['email'], $subId, $senderDomain, Config::$subdomains['clicks'], $htmlBody, $footer);
            HTML::addHtmlPixel($lead['email'], $subId, $senderDomain, $htmlBody);
        }

        if (!empty($data['textBody'])) {
            HTML::addTextFooter($lead['email'], $subId, $senderDomain, Config::$subdomains['clicks'], $textBody, $footer);
        }

        $unsubUrl = HTML::getUnsub(Config::$subdomains['clicks'], $senderDomain, $lead['email'], $subId);

        $esp->sendEmail($lead['email'], $creative->getFrom(), $senderEmail, $creative->getSubject(), $htmlBody, $textBody, $subId, $unsubUrl);

        if ($esp->getLastStatus() == 'sent') {
            Lead::scoreSend($lead['email']);
            Campaign::addSentCount($campaignId);
        } else {
            if (isset($subId) && is_numeric($subId) && $subId > 0) {
                Logging::removeActivity($subId);
            }
        }

        Lead::removeLock($lead['email']);
    }
}

Locks_Cron::removeLock('process-campaigns');