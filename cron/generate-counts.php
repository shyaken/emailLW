<?php

require_once dirname(__FILE__) . '/../email.php';

Cron::checkConcurrency('generate-counts');

$db = new Database;

$verifiedAttributes = array(
    'countOnly'  => true,
    'queryName'  => 'cronverified',
    'count'      => Config::MAX_BATCH_SIZE,
    'interval'   => Config::INTERVAL_VERIFIED,
    'type'       => 'verified',
    'minScore'   => '1',
    'campaignId' => null,
    'tldList'    => null,
    'gender'     => null,
    'country'    => null,
    'state'      => null
);

$verifiedYahooAttributes = array(
    'countOnly'  => true,
    'queryName'  => 'cronyahoo',
    'count'      => Config::MAX_BATCH_SIZE,
    'interval'   => Config::INTERVAL_VERIFIED,
    'type'       => 'verified',
    'minScore'   => '1',
    'campaignId' => null,
    'tldList'    => TldList::$yahooTldList,
    'gender'     => null,
    'country'    => null,
    'state'      => null
);

$verifiedGIAttributes = array(
    'countOnly'   => true,
    'queryName'   => 'crongi',
    'count'       => Config::MAX_BATCH_SIZE,
    'interval'    => Config::INTERVAL_VERIFIED,
    'type'        => 'verified',
    'minScore'    => '1',
    'campaignId'  => null,
    'tldList'     => array_merge(
        TldList::$aolTldList,
        TldList::$microsoftTldList,
        TldList::$gmailTldList,
        TldList::$unitedOnlineTldList,
        TldList::$cableTldList,
        TldList::$yahooTldList
    ),
    'hygiene'     => true,
    'lastHygiene' => date('Y-m-d H:i:s', strtotime("now -30 days")),
    'gender'      => null,
    'country'     => array('US','CA','GB'),
    'state'       => null,
    'inverse'     => array('tldList' => true)
);

$openersAttributes = array(
    'countOnly'  => true,
    'queryName'  => 'cronopeners',
    'count'      => Config::MAX_BATCH_SIZE,
    'interval'   => Config::INTERVAL_OPENER,
    'type'       => 'openers',
    'minScore'   => '1',
    'campaignId' => null,
    'tldList'    => false,
    'gender'     => null,
    'country'    => null,
    'state'      => null
);

$clickersAttributes = array(
    'countOnly'  => true,
    'queryName'  => 'cronclickers',
    'count'      => Config::MAX_BATCH_SIZE,
    'interval'   => Config::INTERVAL_CLICKER,
    'type'       => 'clickers',
    'minScore'   => '1',
    'campaignId' => null,
    'tldList'    => false,
    'gender'     => null,
    'country'    => null,
    'state'      => null
);

$verifiedGBAttributes = array(
    'countOnly'  => true,
    'queryName'  => 'cronverified',
    'count'      => Config::MAX_BATCH_SIZE,
    'interval'   => Config::INTERVAL_VERIFIED,
    'type'       => 'verified',
    'minScore'   => '1',
    'campaignId' => null,
    'tldList'    => null,
    'gender'     => null,
    'country'    => array('GB'),
    'state'      => null
);

$openersGBAttributes = array(
    'countOnly'  => true,
    'queryName'  => 'cronopeners',
    'count'      => Config::MAX_BATCH_SIZE,
    'interval'   => Config::INTERVAL_OPENER,
    'type'       => 'openers',
    'minScore'   => '1',
    'campaignId' => null,
    'tldList'    => false,
    'gender'     => null,
    'country'    => array('GB'),
    'state'      => null
);

$clickersGBAttributes = array(
    'countOnly'  => true,
    'queryName'  => 'cronclickers',
    'count'      => Config::MAX_BATCH_SIZE,
    'interval'   => Config::INTERVAL_CLICKER,
    'type'       => 'clickers',
    'minScore'   => '1',
    'campaignId' => null,
    'tldList'    => false,
    'gender'     => null,
    'country'    => array('GB'),
    'state'      => null
);

$countVerified   = Lead::getLeads($verifiedAttributes);
$countYahoo      = Lead::getLeads($verifiedYahooAttributes);
$countGI         = Lead::getLeads($verifiedGIAttributes);
$countOpeners    = Lead::getLeads($openersAttributes);
$countClickers   = Lead::getLeads($clickersAttributes);
$countGBVerified = Lead::getLeads($verifiedGBAttributes);
$countGBOpeners  = Lead::getLeads($openersGBAttributes);
$countGBClickers = Lead::getLeads($clickersGBAttributes);

$sql  = "UPDATE `counts` SET `count` = '" . $countVerified . "'";
$sql .= " WHERE `query_name` = 'cronverified' LIMIT 1";

$db->query($sql);

$sql  = "UPDATE `counts` SET `count` = '" . $countYahoo . "'";
$sql .= " WHERE `query_name` = 'cronyahoo' LIMIT 1";

$db->query($sql);

$sql  = "UPDATE `counts` SET `count` = '" . $countGI . "'";
$sql .= " WHERE `query_name` = 'crongi' LIMIT 1";

$db->query($sql);

$sql  = "UPDATE `counts` SET `count` = '" . $countClickers . "'";
$sql .= " WHERE `query_name` = 'cronclickers' LIMIT 1";

$db->query($sql);

$sql  = "UPDATE `counts` SET `count` = '" . $countOpeners . "'";
$sql .= " WHERE `query_name` = 'cronopeners' LIMIT 1";

$db->query($sql);

$sql  = "UPDATE `counts` SET `count` = '" . $countGBVerified . "'";
$sql .= " WHERE `query_name` = 'crongbverified' LIMIT 1";

$db->query($sql);

$sql  = "UPDATE `counts` SET `count` = '" . $countGBClickers . "'";
$sql .= " WHERE `query_name` = 'crongbclickers' LIMIT 1";

$db->query($sql);

$sql  = "UPDATE `counts` SET `count` = '" . $countGBOpeners . "'";
$sql .= " WHERE `query_name` = 'crongbopeners' LIMIT 1";

$db->query($sql);

Locks_Cron::removeLock('generate-counts');
