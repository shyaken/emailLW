<?php

class HTML
{

    public static function encodeToken($email, $subId, $link)
    {
        $token  = self::encodeHash($email) . Config::SEPARATOR_EMAIL;
        $token .= self::encodeHash($subId) . Config::SEPARATOR_SUBID;
        $token .= self::encodeHash($link);

        return $token;
    }
    //--------------------------------------------------------------------------


    public static function decodeToken($token)
    {
        $explodeEmail = explode(Config::SEPARATOR_EMAIL, $token);

        if (isset($explodeEmail) && is_array($explodeEmail) && !empty($explodeEmail[1])) {
            $parts['email'] = $explodeEmail[0];

            if (!strpos($token, Config::SEPARATOR_SUBID)) {
                $explodeCampaign = explode(Config::SEPARATOR_CAMPAIGN, $explodeEmail[1]);
                $parts['campaign'] = $explodeCampaign[0];

                if (!empty($explodeCampaign[1])) {
                    $explodeOffer = explode(Config::SEPARATOR_OFFER, $explodeCampaign[1]);
                    $parts['offer'] = $explodeOffer[0];
                }

                if (!empty($explodeOffer[1])) {
                    $explodeLink = explode(Config::SEPARATOR_LINK, $explodeOffer[1]);
                    $parts['link'] = $explodeLink[0];
                }
            } else {
                $explodeSubId = explode(Config::SEPARATOR_SUBID, $explodeEmail[1]);
                $parts['subid'] = $explodeSubId[0];

                if (!empty($explodeSubId[1])) {
                    $explodeLink = explode(Config::SEPARATOR_LINK, $explodeSubId[1]);
                    $parts['link'] = $explodeLink[0];
                }
            }

            $decodedToken['email'] = self::decodeHash($parts['email']);

            if (isset($parts['campaign'])) {
                $decodedToken['campaign'] = self::decodeHash($parts['campaign']);
            }

            if (isset($parts['offer'])) {
                $decodedToken['offer'] = self::decodeHash($parts['offer']);
            }

            if (isset($parts['subid'])) {
                $decodedToken['subid'] = self::decodeHash($parts['subid']);
            }

            if (isset($parts['link'])) {
                $decodedToken['link'] = self::decodeHash($parts['link']);
            }

            return $decodedToken;
        } else {
            Logging::logDebugging('HTML Helper Token Error', $token);
            return false;
        }
    }
    //--------------------------------------------------------------------------


    public static function getTokenFromUrl($url)
    {
        $explodeUrl = explode('token=', $url);

        return $explodeUrl[1];
    }
    //--------------------------------------------------------------------------


    public static function getPixelLink($email, $subId, $senderDomain)
    {
        return "<img src=\"http://" . Config::$subdomains['images'] . '.' . $senderDomain . "/email/tracking/open.php?token=" . self::encodeToken($email, $subId, 'pixel') . "\" width=\"1\" height=\"1\" border=\"0\">";
    }
    //--------------------------------------------------------------------------


    public static function getEncodedLink($email, $subId, $link, $senderDomain)
    {
        return 'http://' . Config::$subdomains['clicks'] . '.' . $senderDomain . "/email/tracking/click.php?token=" . self::encodeToken($email, $subId, $link);
    }
    //--------------------------------------------------------------------------


    public static function encodeHash($text)
    {
        return urlencode(base64_encode($text));
    }
    //--------------------------------------------------------------------------


    public static function decodeHash($text)
    {
        if (empty($text) || $text == '') {
            return false;
        }

        return base64_decode(rawurldecode($text));
    }
    //--------------------------------------------------------------------------


    public static function getUnsubscribeUrl()
    {
        if (isset(Config::$unsubscribeUrl) && !empty(Config::$unsubscribeUrl)) {
            return Config::$unsubscribeUrl;
        } else {
            return false;
        }
    }
    //--------------------------------------------------------------------------


    public static function doEncoding($email, $subId, $senderDomain, &$htmlBody, &$textBody)
    {
        $regexp = "<a\s[^>]*href\s*=\s*([\"\']??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
        if(preg_match_all("/$regexp/siU", $htmlBody, $matches, PREG_SET_ORDER)) {
            foreach ($matches AS $match) {
                $encodedLink = self::getEncodedLink($email, $subId, $match[2], $senderDomain);
                $htmlBody = str_replace($match[2],$encodedLink,$htmlBody);
                $textBody = str_replace($match[2],$encodedLink,$textBody);
            }
        }
    }
    //--------------------------------------------------------------------------


    public static function addHtmlFooter($email, $subId, $senderDomain, $clickSubdomain, &$htmlBody, $footer)
    {
        $htmlBody .= $footer->addHtml($footer->getHtml(), $clickSubdomain, $senderDomain, $email, $subId);
    }
    //--------------------------------------------------------------------------


    public static function addTextFooter($email, $subId, $senderDomain, $clickSubdomain, &$textBody, $footer)
    {
        $textBody .= $footer->addText($footer->getText(), $clickSubdomain, $senderDomain, $email, $subId);
    }
    //--------------------------------------------------------------------------


    public static function addHtmlPixel($email, $subId, $senderDomain, &$htmlBody)
    {
        $htmlBody .= "<br /><br />" . self::getPixelLink($email, $subId, $senderDomain);
    }
    //--------------------------------------------------------------------------


    public static function getUnsub($clickSubdomain, $senderDomain, $email, $subId)
    {
        $emailHash  = HTML::encodeHash($email);
        $subidHash  = HTML::encodeHash($subId);
        $unsubUrl   = 'http://' . $clickSubdomain . '.' . $senderDomain;
        $unsubUrl  .= '/email/tracking/unsubscribe.php';
        $unsubUrl  .= '?id='. $emailHash . '&sub=' . $subidHash;

        return $unsubUrl;
    }
    //--------------------------------------------------------------------------
}