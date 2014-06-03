<?php

class Lead extends Database
{

    protected $id;
    protected $email;
    protected $address;
    protected $firstName;
    protected $lastName;
    protected $country;
    protected $phone;
    protected $os;
    protected $language;
    protected $state;
    protected $city;
    protected $postalCode;
    protected $domainName;
    protected $sourceUrl;
    protected $campaign;
    protected $username;
    protected $ip;
    protected $subscribeDate;
    protected $birthDay;
    protected $birthMonth;
    protected $birthYear;
    protected $gender;
    protected $seeking;
    protected $hygiene_datetime;
    protected $verification_datetime;


    protected $tableName = 'leads';
    const      tableName = 'leads';

    public function __construct($email)
    {
        parent::__construct();

        $sql  = "SELECT * FROM `$this->tableName` WHERE `email` = '" . mysql_real_escape_string($email) . "';";

        $result = $this->getArrayAssoc($sql);

        $this->email                 = $result['email'];
        $this->address               = $result['address'];
        $this->firstName             = $result['first_name'];
        $this->lastName              = $result['last_name'];
        $this->country               = $result['country'];
        $this->phone                 = $result['phone'];
        $this->os                    = $result['os'];
        $this->language              = $result['language'];
        $this->state                 = $result['state'];
        $this->city                  = $result['city'];
        $this->postalCode            = $result['postal_code'];
        $this->domainName            = $result['source_domain'];
        $this->sourceUrl             = $result['source_url'];
        $this->campaign              = $result['source_campaign'];
        $this->username              = $result['source_username'];
        $this->ip                    = $result['ip'];
        $this->subscribeDate         = $result['subscribe_datetime'];
        $this->birthDay              = $result['birth_day'];
        $this->birthMonth            = $result['birth_month'];
        $this->birthYear             = $result['birth_year'];
        $this->gender                = $result['gender'];
        $this->seeking               = $result['seeking'];
        $this->hygiene_datetime      = $result['hygiene_datetime'];
        $this->verification_datetime = $result['verification_datetime'];
    }
    //--------------------------------------------------------------------------


    public static function getLeadsByPiece($leadAttributes, $maxSearchReturn)
    {
        $leadGroup = self::initializeLeadGroup($maxSearchReturn);
        $leadGroup = self::getLeadsByType($leadGroup, $leadAttributes['type']);

        return $leadGroup;
    }
    //--------------------------------------------------------------------------


    public static function initializeLeadGroup($maxSearchReturn)
    {
        $db = new Database;

        $sql = "SELECT * FROM `" . self::tableName. "` LIMIT " . $maxSearchReturn;

        $result = $db->getArray($sql);

        return $result;
    }
    //--------------------------------------------------------------------------


    public static function getLeadsByType($leadGroup, $type)
    {
        $db = new Database;

//        $sql = "SELECT * FROM `" . self::tableName. "` LIMIT " . $maxSearchReturn;

        $result = $db->getArray($sql);

        return $result;
    }
    //--------------------------------------------------------------------------


    public static function getLeads($leadAttributes)
    {
        $db  = new Database;
        $sql = '';

        $inverse['country']  = (isset($leadAttributes['inverse']['country']))  ? $leadAttributes['inverse']['country'] : NULL;
        $inverse['state']    = (isset($leadAttributes['inverse']['state']))    ? $leadAttributes['inverse']['state']   : NULL;
        $inverse['gender']   = (isset($leadAttributes['inverse']['gender']))   ? $leadAttributes['inverse']['gender']  : NULL;
        $inverse['tldList']  = (isset($leadAttributes['inverse']['tldList']))  ? $leadAttributes['inverse']['tldList'] : NULL;

        $operators = self::buildOperators($inverse['country'],
                                          $inverse['state'],
                                          $inverse['gender'],
                                          $inverse['tldList']);

        $qualifiers = self::buildQualifiers($inverse['country'],
                                            $inverse['state'],
                                            $inverse['tldList']);

        self::buildTldArray($leadAttributes['tldList']);
        self::buildAttributes($sql, $leadAttributes['countOnly']);
        self::buildSuppressionAvoidance($sql);
        self::buildLockAvoidance($sql);
        self::buildQueuedAvoidance($sql);
        self::buildMd5Check($sql);
        self::buildCountryList($sql, $leadAttributes['country'], Config::$defaultCountryList, $operators['country'], $qualifiers['country']);
        self::buildStateList($sql, $leadAttributes['state'], $operators['state'], $qualifiers['state']);
        self::buildGenderList($sql, $leadAttributes['gender'], $operators['gender']);

        if ($leadAttributes['tldList'] !== false) {
            $sql .= " AND    (a.`email`      " . $operators['tldList'] . " '" . $leadAttributes['tldList'][0] . "'";
            if (sizeof($leadAttributes['tldList']) > 1) {
                for ($tldCount = 1, $limit = count($leadAttributes['tldList']); $tldCount < $limit; $tldCount++) {
                    $sql .= "  " . $qualifiers['tldList'] . " a.`email`      " . $operators['tldList'] . " '" . $leadAttributes['tldList'][$tldCount] . "'";
                }
            }
            $sql .= ")";
        }

        if (!empty($leadAttributes['interval'])) {
            $currentDate = new Datetime();
            $currentDate->sub(new Dateinterval('P' . $leadAttributes['interval'] . 'D'));
            $cutOff      = $currentDate->format('Y-m-d H:i:s');

            $sql .= " AND a.`email`      NOT IN (SELECT f.`email`      FROM `activity`               AS f";
            $sql .= "                            WHERE f.`datetime` > '" . $cutOff . "')";
        }

        if ($leadAttributes['type'] == 'clickers') {
            $sql .= " AND a.`email`          IN (SELECT g.`email`      FROM `clickers`               AS g";
            $sql .= "                            WHERE g.`email` = a.`email`)";
        } else if ($leadAttributes['type'] == 'openers') {
            $sql .= " AND a.`email`          IN (SELECT g.`email`      FROM `openers`                AS g";
            $sql .= "                            WHERE g.`email` = a.`email`)";
            $sql .= " AND a.`email`      NOT IN (SELECT h.`email`      FROM `clickers`               AS h";
            $sql .= "                            WHERE h.`email` = a.`email`)";
        } else if ($leadAttributes['type'] == 'verified') {
            $sql .= " AND a.`email`      NOT IN (SELECT g.`email`      FROM `openers`                AS g";
            $sql .= "                            WHERE g.`email` = a.`email`)";
            $sql .= " AND a.`email`      NOT IN (SELECT h.`email`      FROM `clickers`               AS h";
            $sql .= "                            WHERE h.`email` = a.`email`)";
        }

        if ($leadAttributes['minScore'] > 0) {
            $sql .= " AND a.`score`      >= '" . $leadAttributes['minScore'] . "'";
        }

        if ($leadAttributes['campaignId'] > 0) {
            $sql .= " AND a.`email`      NOT IN (SELECT i.`email`      FROM `activity`               AS i";
            $sql .= "                            WHERE i.`campaign_id` = '" . $leadAttributes['campaignId'] . "')";
        }

        if (isset($leadAttributes['hygiene']) && $leadAttributes['hygiene'] === true) {
            if (!empty($leadAttributes['lastHygiene'])) {
                $sql .= " AND a.`hygiene_datetime` >= '" . $leadAttributes['lastHygiene'] .  "'";
            } else {
                $sql .= " AND a.`hygiene_datetime` IS NOT NULL";
            }
        } else if (isset($leadAttributes['hygiene']) && $leadAttributes['hygiene'] === false) {
            if (!empty($leadAttributes['lastHygiene'])) {
                $sql .= " AND (a.`hygiene_datetime` <= '" . $leadAttributes['lastHygiene'] .  "'";
                $sql .= "      OR a.`hygiene_datetime` IS NULL)";
            } else {
                $sql .= " AND a.`hygiene_datetime` IS NULL";
            }
        }

        if (!empty($leadAttributes['verification'])) {
            if (!empty($leadAttributes['lastVerification'])) {
                $sql .= " AND a.`verification_datetime` >= '" . $leadAttributes['lastVerification'] .  "'";
            } else {
                $sql .= " AND a.`verification_datetime` IS NOT NULL";
            }
        }

        if (!$leadAttributes['countOnly']) {
            if ($leadAttributes['queryName'] === false) {
                $sql .= " LIMIT " . $leadAttributes['count'];
            } else {
                $sql .= " LIMIT " . Random::getRandomBegin($leadAttributes['queryName'],$leadAttributes['count']) . "," . $leadAttributes['count'];
            }

            $result = $db->getArray($sql);
        } else {
            $result = $db->getUpperLeft($sql);
        }

        return $result;
    }
    //--------------------------------------------------------------------------


    public static function buildOperators($country, $state, $gender, $tldList)
    {
        $result['country']  = (isset($country))  ? '!=' : '=';
        $result['state']    = (isset($state))    ? '!=' : '=';
        $result['gender']   = (isset($gender))   ? '!=' : '=';
        $result['tldList']  = (isset($tldList))  ? 'NOT LIKE' : 'LIKE';

        return $result;
    }
    //--------------------------------------------------------------------------


    public static function buildQualifiers($country, $state, $tldList)
    {
        $result['country']  = (isset($country))  ? 'AND' : 'OR';
        $result['state']    = (isset($state))    ? 'AND' : 'OR';
        $result['tldList']  = (isset($tldList))  ? 'AND' : 'OR';

        return $result;
    }
    //--------------------------------------------------------------------------


    public static function buildTldArray(&$tldList)
    {
        if (empty($tldList) && $tldList !== false) {
            $tldList = array_merge(
                TldList::$aolTldList,
                TldList::$microsoftTldList,
                TldList::$gmailTldList,
                TldList::$unitedOnlineTldList,
                TldList::$cableTldList);
        }

        return;
    }
    //--------------------------------------------------------------------------


    public static function buildAttributes(&$sql, $countOnly)
    {
        if (!empty($countOnly)) {
            $sql  = "SELECT COUNT(*)";
        } else {
            $sql  = "SELECT a.`email`, a.`domain`, a.`md5_email`, a.`md5_domain`, a.`country`, a.`state`,";
            $sql .= "       a.`postal_code`, a.`gender`, a.`birth_day`, a.`birth_month`, a.`birth_year`,";
            $sql .= "       a.`hygiene_datetime`, a.`verification_datetime`, a.`source_campaign`";
        }

        $sql .= " FROM `" . self::tableName. "` AS a";
    }
    //--------------------------------------------------------------------------


    public static function buildSuppressionAvoidance(&$sql)
    {
        $sql .= " WHERE a.`email`      NOT IN (SELECT b.`email`      FROM `suppression_email`      AS b)";
        $sql .= " AND   a.`domain`     NOT IN (SELECT c.`domain`     FROM `suppression_domain`     AS c)";
        $sql .= " AND   a.`md5_email`  NOT IN (SELECT d.`email_md5`  FROM `suppression_email_md5`  AS d)";
        $sql .= " AND   a.`md5_domain` NOT IN (SELECT e.`domain_md5` FROM `suppression_domain_md5` AS e)";
    }
    //--------------------------------------------------------------------------


    public static function buildMd5Check(&$sql)
    {
        $sql .= " AND a.`md5_email`  IS NOT NULL";
        $sql .= " AND a.`md5_domain` IS NOT NULL";
    }
    //--------------------------------------------------------------------------


    public static function buildCountryList(&$sql, $countryList, $defaultCountryList, $operator, $qualifier)
    {
        if (!empty($countryList)) {

            $sql .= " AND (a.`country` " . $operator . " '" . $countryList[0] . "'";

            if (sizeof($countryList) > 1) {
                for ($countryCount = 1, $limit = count($countryList); $countryCount < $limit; $countryCount++) {
                    $sql .= " " . $qualifier . " a.`country` " . $operator . " '" . $countryList[$countryCount] . "'";
                }
            }

            $sql .= ")";
        } else {
            $sql .= " AND (a.`country` " . $operator . " '" . $defaultCountryList[0] . "'";

            if (sizeof($defaultCountryList) > 1) {
                for ($countryCount = 1, $limit = count($defaultCountryList); $countryCount < $limit; $countryCount++) {
                    $sql .= " " . $qualifier . " a.`country` " . $operator . " '" . $defaultCountryList[$countryCount] . "'";
                }
            }

            $sql .= ")";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildStateList(&$sql, $stateList, $operator, $qualifier)
    {
        if (!empty($stateList)) {
            $sql .= " AND (a.`state` " . $operator . " '" . $stateList[0] . "'";

            if (sizeof($stateList) > 1) {
                for ($stateCount = 1, $limit = count($stateList); $stateCount < $limit; $stateCount++) {
                    $sql .= " " . $qualifier . " a.`state` " . $operator . " '" . $stateList[$stateCount] . "'";
                }
            }

            $sql .= ")";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildLockAvoidance(&$sql)
    {
        $sql .= " AND a.`locked` = '0'";
    }
    //--------------------------------------------------------------------------


    public static function buildQueuedAvoidance(&$sql)
    {
        $sql .= " AND a.`email` NOT IN (SELECT j.`email` FROM `queue_build` AS j)";
        $sql .= " AND a.`email` NOT IN (SELECT j.`email` FROM `queue_send` AS j)";
    }
    //--------------------------------------------------------------------------


    public static function buildGenderList(&$sql, $genderList, $operator)
    {
        if (!empty($genderList)) {
            $sql .= " AND a.`gender` " . $operator . " '" . $genderList . "'";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildX1(&$X)
    {
        if (!empty($leadAttributes['countOnly'])) {
            $sql  = "SELECT COUNT(*)";
        } else {
            $sql  = "SELECT   a.`email`, a.`domain`, a.`md5_email`, a.`md5_domain`, a.`country`, a.`state`,";
            $sql .= "         a.`postal_code`, a.`gender`, a.`birth_day`, a.`birth_month`, a.`birth_year`,";
            $sql .= "         a.`hygiene_datetime`, a.`verification_datetime`";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildX2(&$X)
    {
        if (!empty($leadAttributes['countOnly'])) {
            $sql  = "SELECT COUNT(*)";
        } else {
            $sql  = "SELECT   a.`email`, a.`domain`, a.`md5_email`, a.`md5_domain`, a.`country`, a.`state`,";
            $sql .= "         a.`postal_code`, a.`gender`, a.`birth_day`, a.`birth_month`, a.`birth_year`,";
            $sql .= "         a.`hygiene_datetime`, a.`verification_datetime`";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildX3(&$X)
    {
        if (!empty($leadAttributes['countOnly'])) {
            $sql  = "SELECT COUNT(*)";
        } else {
            $sql  = "SELECT   a.`email`, a.`domain`, a.`md5_email`, a.`md5_domain`, a.`country`, a.`state`,";
            $sql .= "         a.`postal_code`, a.`gender`, a.`birth_day`, a.`birth_month`, a.`birth_year`,";
            $sql .= "         a.`hygiene_datetime`, a.`verification_datetime`";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildX4(&$X)
    {
        if (!empty($leadAttributes['countOnly'])) {
            $sql  = "SELECT COUNT(*)";
        } else {
            $sql  = "SELECT   a.`email`, a.`domain`, a.`md5_email`, a.`md5_domain`, a.`country`, a.`state`,";
            $sql .= "         a.`postal_code`, a.`gender`, a.`birth_day`, a.`birth_month`, a.`birth_year`,";
            $sql .= "         a.`hygiene_datetime`, a.`verification_datetime`";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildX5(&$X)
    {
        if (!empty($leadAttributes['countOnly'])) {
            $sql  = "SELECT COUNT(*)";
        } else {
            $sql  = "SELECT   a.`email`, a.`domain`, a.`md5_email`, a.`md5_domain`, a.`country`, a.`state`,";
            $sql .= "         a.`postal_code`, a.`gender`, a.`birth_day`, a.`birth_month`, a.`birth_year`,";
            $sql .= "         a.`hygiene_datetime`, a.`verification_datetime`";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildX6(&$X)
    {
        if (!empty($leadAttributes['countOnly'])) {
            $sql  = "SELECT COUNT(*)";
        } else {
            $sql  = "SELECT   a.`email`, a.`domain`, a.`md5_email`, a.`md5_domain`, a.`country`, a.`state`,";
            $sql .= "         a.`postal_code`, a.`gender`, a.`birth_day`, a.`birth_month`, a.`birth_year`,";
            $sql .= "         a.`hygiene_datetime`, a.`verification_datetime`";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildX7(&$X)
    {
        if (!empty($leadAttributes['countOnly'])) {
            $sql  = "SELECT COUNT(*)";
        } else {
            $sql  = "SELECT   a.`email`, a.`domain`, a.`md5_email`, a.`md5_domain`, a.`country`, a.`state`,";
            $sql .= "         a.`postal_code`, a.`gender`, a.`birth_day`, a.`birth_month`, a.`birth_year`,";
            $sql .= "         a.`hygiene_datetime`, a.`verification_datetime`";
        }
    }
    //--------------------------------------------------------------------------


    public static function buildX8(&$X)
    {
        if (!empty($leadAttributes['countOnly'])) {
            $sql  = "SELECT COUNT(*)";
        } else {
            $sql  = "SELECT   a.`email`, a.`domain`, a.`md5_email`, a.`md5_domain`, a.`country`, a.`state`,";
            $sql .= "         a.`postal_code`, a.`gender`, a.`birth_day`, a.`birth_month`, a.`birth_year`,";
            $sql .= "         a.`hygiene_datetime`, a.`verification_datetime`";
        }
    }
    //--------------------------------------------------------------------------


    public static function getLeadsWithoutMD5($count)
    {
        $db = new Database;

        $sql  = "SELECT `email` FROM `" . self::tableName . "`";
        $sql .= " WHERE `md5_email` IS NULL OR `md5_domain` IS NULL";
        $sql .= " LIMIT " . $count . "";

        $result = $db->getArray($sql);

        return $result;
    }
    //--------------------------------------------------------------------------


    public static function isSuppressed($email)
    {
        $db = new Database;

        $emailParts = explode('@',$email);

        $domain = $emailParts[1];

        $sql  = "SELECT COUNT(*) FROM `suppression_email`";
        $sql .= " WHERE `email` = '" . mysql_real_escape_string($email) . "'";

        $result = $db->getUpperLeft($sql);

        if ($result) { return true; }

        $sql  = "SELECT COUNT(*) FROM `suppression_domain`";
        $sql .= " WHERE `domain` = '" . mysql_real_escape_string($domain) . "'";

        $result = $db->getUpperLeft($sql);

        if ($result) { return true; }

        $sql  = "SELECT COUNT(*) FROM `suppression_email_md5`";
        $sql .= " WHERE `email_md5` = '" . mysql_real_escape_string(md5($email)) . "'";

        $result = $db->getUpperLeft($sql);

        if ($result) { return true; }

        $sql  = "SELECT COUNT(*) FROM `suppression_domain_md5`";
        $sql .= " WHERE `domain_md5` = '" . mysql_real_escape_string(md5($domain)) . "'";

        $result = $db->getUpperLeft($sql);

        if ($result) { return true; }

        return false;
    }
    //--------------------------------------------------------------------------


    public static function getLeadsWithoutDomain($count)
    {
        $db = new Database;

        $sql  = "SELECT `email` FROM `" . self::tableName . "`";
        $sql .= " WHERE `domain` IS NULL OR `domain` = ''";
        $sql .= " LIMIT " . $count . "";

        $result = $db->getArray($sql);

        return $result;
    }
    //--------------------------------------------------------------------------


    public static function getLeadsWithLocks($count = 10000, $interval = Config::LEAD_TIMEOUT)
    {
        $db = new Database;

        $currentDate = new Datetime();
        $currentDate->sub(new Dateinterval('PT' . $interval . 'S'));
        $cutOff      = $currentDate->format('Y-m-d H:i:s');

        $sql  = "SELECT `email` FROM `" . self::tableName . "`";
        $sql .= " WHERE `locked` = '1' AND `lock_datetime` < '" . $cutOff . "'";
        $sql .= " LIMIT " . $count . "";

        $result = $db->getArray($sql);

        return $result;
    }
    //--------------------------------------------------------------------------


    public static function updateMD5($email, $emailMD5, $domainMD5)
    {
        $db = new Database;

        $sql  = "UPDATE `" .self::tableName . "` ";
        $sql .= " SET `md5_email` = '" . mysql_real_escape_string($emailMD5) . "',";
        $sql .= " `md5_domain` = '" . mysql_real_escape_string($domainMD5) . "'";
        $sql .= " WHERE `email` = '" . mysql_real_escape_string($email) . "'";
        $sql .= " LIMIT 1";

        $result = $db->query($sql);

        return $result;
    }
    //--------------------------------------------------------------------------


    public static function isClicker($email)
    {
        $db = new Database;

        $sql = "SELECT `email` FROM `clickers` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $result = $db->getUpperLeft($sql);

        if ($result == $email) {
            return true;
        }

        return false;
    }
    //--------------------------------------------------------------------------


    public static function isOpener($email)
    {
        $db = new Database;

        $sql = "SELECT `email` FROM `openers` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $result = $db->getUpperLeft($sql);

        if ($result == $email) {
            return true;
        }

        return false;
    }
    //--------------------------------------------------------------------------


    public static function getLastHygiene($email)
    {
        $db = new Database;

        $sql = "SELECT `hygiene_datetime` FROM `leads` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $result = $db->getUpperLeft($sql);

        if (!empty($result)) {
            return $result;
        }

        return false;
    }
    //--------------------------------------------------------------------------


    public static function getLastVerification($email)
    {
        $db = new Database;

        $sql = "SELECT `verification_datetime` FROM `leads` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $result = $db->getUpperLeft($sql);

        if (!empty($result)) {
            return $result;
        }

        return false;
    }
    //--------------------------------------------------------------------------


    public static function getAddressByEmail($email)
    {
        $db = new Database;

        $sql = "SELECT `address` FROM `" . self::tableName . "` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $result = $db->getUpperLeft($sql);

        return $result;
    }
    //--------------------------------------------------------------------------


    public function getEmail()
    {
        return $this->email;
    }
    //--------------------------------------------------------------------------


    public function getSubscribeDate()
    {
        return $this->subscribeDate;
    }
    //--------------------------------------------------------------------------


    public function getSourceUrl()
    {
        return $this->sourceUrl;
    }
    //--------------------------------------------------------------------------


    public function getAddress()
    {
        return $this->address;
    }
    //--------------------------------------------------------------------------


    public function getFirstName()
    {
        return $this->firstName;
    }
    //--------------------------------------------------------------------------


    public function getLastName()
    {
        return $this->lastName;
    }
    //--------------------------------------------------------------------------


    public function getCountry()
    {
        return $this->country;
    }
    //--------------------------------------------------------------------------


    public function getPhone()
    {
        return $this->phone;
    }
    //--------------------------------------------------------------------------


    public function getOS()
    {
        return $this->os;
    }
    //--------------------------------------------------------------------------


    public function getLanguage()
    {
        return $this->language;
    }
    //--------------------------------------------------------------------------


    public function getState()
    {
        return $this->state;
    }
    //--------------------------------------------------------------------------


    public function getCity()
    {
        return $this->city;
    }
    //--------------------------------------------------------------------------


    public function getPostalCode()
    {
        return $this->postalCode;
    }
    //--------------------------------------------------------------------------


    public function getDomainName()
    {
        return $this->domainName;
    }
    //--------------------------------------------------------------------------


    public function getCampaign()
    {
        return $this->campaign;
    }
    //--------------------------------------------------------------------------


    public function getUsername()
    {
        return $this->username;
    }
    //--------------------------------------------------------------------------


    public function getIP()
    {
        return $this->ip;
    }
    //--------------------------------------------------------------------------


    public function getBirthDay()
    {
        return $this->birthDay;
    }
    //--------------------------------------------------------------------------


    public function getBirthMonth()
    {
        return $this->birthMonth;
    }
    //--------------------------------------------------------------------------


    public function getBirthYear()
    {
        return $this->birthYear;
    }
    //--------------------------------------------------------------------------


    public function getGender()
    {
        return $this->gender;
    }
    //--------------------------------------------------------------------------


    public function getSeeking()
    {
        return $this->seeking;
    }
    //--------------------------------------------------------------------------


    public static function scoreNewLead($email)
    {
        $db = new Database;

        $sql = "UPDATE `" . self::tableName. "` SET `score` = '" . Config::SCOREMOD_NEW . "' WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function scoreSend($email)
    {
        $db = new Database;

        $sql = "SELECT `score` FROM `" . self::tableName. "` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $oldScore = $db->getUpperLeft($sql);

        $newScore = ($oldScore + Config::SCOREMOD_SEND);

        if ($newScore < 0) {
            $newScore = 0;
        } else if ($newScore > 100) {
            $newScore = 100;
        }

        $sql = "UPDATE `" . self::tableName. "` SET `score` = '" . $newScore . "' WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function scoreOpen($email)
    {
        $db = new Database;

        $sql = "SELECT `score` FROM `" . self::tableName . "` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $oldScore = $db->getUpperLeft($sql);

        $newScore = ($oldScore + Config::SCOREMOD_OPEN);

        if ($newScore < 0) {
            $newScore = 0;
        } else if ($newScore > 100) {
            $newScore = 100;
        }

        $sql = "UPDATE `" . self::tableName . "` SET `score` = '" . $newScore . "' WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function scoreClick($email)
    {
        $db = new Database;

        $sql = "SELECT `score` FROM `" . self::tableName . "` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $oldScore = $db->getUpperLeft($sql);

        $newScore = ($oldScore + Config::SCOREMOD_CLICK);

        if ($newScore < 0) {
            $newScore = 0;
        } else if ($newScore > 100) {
            $newScore = 100;
        }

        $sql = "UPDATE `" . self::tableName . "` SET `score` = '" . $newScore . "' WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function scoreComplaint($email)
    {
        $db = new Database;

        $sql = "SELECT `score` FROM `" . self::tableName. "` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $oldScore = $db->getUpperLeft($sql);

        $newScore = ($oldScore + Config::SCOREMOD_COMPLAINT);

        if ($newScore < 0) {
            $newScore = 0;
        } else if ($newScore > 100) {
            $newScore = 100;
        }

        $sql = "UPDATE `" . self::tableName  . "` SET `score` = '" . $newScore . "' WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function scoreSoftBounce($email)
    {
        $db = new Database;

        $sql = "SELECT `score` FROM `" . self::tableName . "` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $oldScore = $db->getUpperLeft($sql);

        $newScore = ($oldScore + Config::SCOREMOD_SOFTBOUNCE);

        if ($newScore < 0) {
            $newScore = 0;
        } else if ($newScore > 100) {
            $newScore = 100;
        }

        $sql = "UPDATE `" . self::tableName . "` SET `score` = '" . $newScore . "' WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function scoreHardBounce($email)
    {
        $db = new Database;

        $sql = "SELECT `score` FROM `" . self::tableName . "` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $oldScore = $db->getUpperLeft($sql);

        $newScore = ($oldScore + Config::SCOREMOD_HARDBOUNCE);

        if ($newScore < 0) {
            $newScore = 0;
        } else if ($newScore > 100) {
            $newScore = 100;
        }

        $sql = "UPDATE `" . self::tableName . "` SET `score` = '" . $newScore . "' WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function scoreUnsubscribe($email)
    {
        $db = new Database;

        $sql = "SELECT `score` FROM `" . self::tableName . "` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $oldScore = $db->getUpperLeft($sql);

        $newScore = ($oldScore + Config::SCOREMOD_UNSUBSCRIBE);

        if ($newScore < 0) {
            $newScore = 0;
        } else if ($newScore > 100) {
            $newScore = 100;
        }

        $sql = "UPDATE `" . self::tableName . "` SET `score` = '" . $newScore . "' WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function scoreHygieneFail($email)
    {
        $db = new Database;

        $sql = "SELECT `score` FROM `" . self::tableName . "` WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $oldScore = $db->getUpperLeft($sql);

        $newScore = ($oldScore + Config::SCOREMOD_HYGIENEFAIL);

        if ($newScore < 0) {
            $newScore = 0;
        } else if ($newScore > 100) {
            $newScore = 100;
        }

        $sql = "UPDATE `" . self::tableName . "` SET `score` = '" . $newScore . "' WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function setLock($email)
    {
        $db = new Database;

        $sql = "UPDATE `" . self::tableName . "` SET `locked` = '1', `lock_datetime` = NOW() WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function removeLock($email)
    {
        $db = new Database;

        $sql = "UPDATE `" . self::tableName . "` SET `locked` = '0', `lock_datetime` = NULL WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function setHygieneDatetime($email)
    {
        $db = new Database;

        $sql = "UPDATE `" . self::tableName . "` SET `hygiene_datetime` = NOW() WHERE `email` = '" . mysql_real_escape_string($email) . "' LIMIT 1;";
        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function addUnsubscribe($email)
    {
        Suppression_Email::addEmailSuppression($email, 2, 6);

        return true;
    }
    //--------------------------------------------------------------------------


    public static function addUnsubscribeTransaction($email, $subId = NULL)
    {
        $db = new Database;

        $sql  = "INSERT INTO `transactions` (id, type, email, activity_id, datetime) VALUES";
        $sql .= " (NULL,";
        $sql .= " '" . Config::TRANSACTION_UNSUBSCRIBE . "',";
        $sql .= " '" . mysql_real_escape_string($email) . "',";

        if ($subId) {
            $sql .= " '" . mysql_real_escape_string($subId) . "',";
        } else {
            $sql .= " NULL,";
        }

        $sql .= " NOW())";

        $db->query($sql);

        return true;
    }
    //--------------------------------------------------------------------------
}