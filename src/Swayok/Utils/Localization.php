<?php

namespace Swayok\Utils;

// regions and currencies
class Localization {

    const RUSSIAN = 'ru';
    const ENGLISH = 'en';
    const FRENCH = 'fr';
    const CHINESE = 'zh';

    const RUB = 'RUB';
    const USD = 'USD';
    const EUR = 'EUR';
    const GBP = 'GBP';
    const CNY = 'CNY';

    static public $languageCurrency = array(
        self::RUSSIAN => self::RUB,
        self::FRENCH => self::EUR,
        self::ENGLISH => self::USD,
        self::CHINESE => self::CNY,
    );

    const MOBILE_APP_LANGUAGE = self::ENGLISH;
    const DEFAULT_LANGUAGE = self::ENGLISH;

    static public $systemLanguages = array(
        self::RUSSIAN,
        self::ENGLISH
    );

    static public $languagesConvert = array(
        'uk' => self::RUSSIAN,
        'be' => self::RUSSIAN
    );

    static protected $language;

    static public function getSystemLanguage() {
        if (empty(self::$language)) {
            $browserLang = self::detectBrowserLanguage();
            self::$language = self::toKnownLanguageOrDefault($browserLang);
        }
        return self::$language;
    }

    /**
     * Detect language using info received from browser ($_COOKIE['lang'] or $_SERVER['HTTP_ACCEPT_LANGUAGE'])
     * @param bool $ignoreSignTest - true: will ignore !empty($_GET['sign']) test
     * @return string
     */
    static public function detectBrowserLanguage($ignoreSignTest = false) {
        if (!$ignoreSignTest && !empty($_GET['sign'])) {
            return self::MOBILE_APP_LANGUAGE;
        }
        if (Cookie::exists('lang') && strlen(Cookie::get('lang')) == 2) {
            return strtolower(Cookie::get('lang'));
        } else if (
            isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            && preg_match('%^([a-zA-Z]{2})(-.*)?(;|$)%is', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches)
        ) {
            return strtolower($matches[1]);
        }
        return false;
    }

    /**
     * Save language to cookies
     * @param string $lang
     * @param bool $forever - true: cookie will expire in a year | false: cookie will expire in a month
     * @param string $path
     * @param bool $forceReplace - true: replace lang cookie even if it have been already set
     */
    static public function saveLanguageToCookies($lang, $forever = false, $path = '/', $forceReplace = false) {
        if ($_SERVER['HTTP_USER_AGENT'] !== 'shell') {
            if ($forceReplace || !Cookie::exists('lang') || Cookie::get('lang') !== $lang || $forever) {
                Cookie::set('lang', $lang, ($forever ? 604800 : 0), array('path' => $path, 'encrypt' => false, 'httpOnly' => false));
            }
        }
    }

    static public function removeLanguageFromCookies($path = '/') {
        Cookie::delete('lang', '', $path);
    }

    static public function changeLanguage($lang, $forever = false, $path = '/', $forceReplace = false) {
        $lang = self::toKnownLanguage($lang);
        if (!empty($lang)) {
            self::$language = $lang;
            self::saveLanguageToCookies($lang, $forever, $path, $forceReplace);
        }
    }

    /**
     * @param $lang - language to test and possibly convert to any known language
     * @return bool|string - false: language is unknown | string: valid system language
     */
    static public function toKnownLanguage($lang) {
        if (empty($lang) || !is_string($lang)) {
            return false;
        }
        if (strlen($lang) > 2) {
            $lang = substr($lang, 0, 2);
        }
        $lang = strtolower($lang);
        if (in_array($lang, self::$systemLanguages)) {
            return $lang;
        } else if (!empty(self::$languagesConvert[$lang]) && in_array(self::$languagesConvert[$lang], self::$systemLanguages)) {
            return self::$languagesConvert[$lang];
        }
        return false;
    }

    static public function toKnownLanguageOrDefault($lang) {
        $lang = self::toKnownLanguage($lang);
        if (empty($lang)) {
            return strtolower(self::DEFAULT_LANGUAGE);
        } else {
            return $lang;
        }
    }
}
Localization::getSystemLanguage(); //< detect system language
