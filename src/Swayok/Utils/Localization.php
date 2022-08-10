<?php

namespace Swayok\Utils;

// regions and currencies
abstract class Localization
{
    
    const RUSSIAN = 'ru';
    const ENGLISH = 'en';
    const FRENCH = 'fr';
    const CHINESE = 'zh';
    
    const RUB = 'RUB';
    const USD = 'USD';
    const EUR = 'EUR';
    const GBP = 'GBP';
    const CNY = 'CNY';
    
    static public $languageCurrency = [
        self::RUSSIAN => self::RUB,
        self::FRENCH => self::EUR,
        self::ENGLISH => self::USD,
        self::CHINESE => self::CNY,
    ];
    
    const MOBILE_APP_LANGUAGE = self::ENGLISH;
    const DEFAULT_LANGUAGE = self::ENGLISH;
    
    static public $systemLanguages = [
        self::RUSSIAN,
        self::ENGLISH,
    ];
    
    static public $languagesConvert = [
        'uk' => self::RUSSIAN,
        'be' => self::RUSSIAN,
    ];
    
    static protected $language;
    
    public static function getSystemLanguage()
    {
        if (empty(self::$language)) {
            $browserLang = self::detectBrowserLanguage();
            self::$language = self::toKnownLanguageOrDefault($browserLang);
        }
        return self::$language;
    }
    
    /**
     * Detect language using info received from browser ($_COOKIE['lang'] or $_SERVER['HTTP_ACCEPT_LANGUAGE'])
     * @param bool $ignoreSignTest - true: will ignore !empty($_GET['sign']) test
     * @param bool $ignoreCookies
     * @return string
     */
    public static function detectBrowserLanguage($ignoreSignTest = false, $ignoreCookies = false)
    {
        if (!$ignoreSignTest && !empty($_GET['sign'])) {
            return self::MOBILE_APP_LANGUAGE;
        }
        if (!$ignoreCookies && Cookie::exists('lang') && strlen(Cookie::get('lang')) == 2) {
            return strtolower(Cookie::get('lang'));
        } elseif (
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
    public static function saveLanguageToCookies($lang, $forever = false, $path = '/', $forceReplace = false)
    {
        if ($_SERVER['HTTP_USER_AGENT'] !== 'shell') {
            if ($forceReplace || !Cookie::exists('lang') || Cookie::get('lang') !== $lang || $forever) {
                Cookie::set('lang', $lang, ($forever ? 604800 : 0), ['path' => $path, 'encrypt' => false, 'httpOnly' => false]);
            }
        }
    }
    
    public static function removeLanguageFromCookies($path = '/')
    {
        Cookie::delete('lang', '', $path);
    }
    
    public static function changeLanguage($lang, $forever = false, $path = '/', $forceReplace = false)
    {
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
    public static function toKnownLanguage($lang)
    {
        if (empty($lang) || !is_string($lang)) {
            return false;
        }
        if (strlen($lang) > 2) {
            $lang = substr($lang, 0, 2);
        }
        $lang = strtolower($lang);
        if (in_array($lang, self::$systemLanguages)) {
            return $lang;
        } elseif (!empty(self::$languagesConvert[$lang]) && in_array(self::$languagesConvert[$lang], self::$systemLanguages)) {
            return self::$languagesConvert[$lang];
        }
        return false;
    }
    
    public static function toKnownLanguageOrDefault($lang)
    {
        $lang = self::toKnownLanguage($lang);
        if (empty($lang)) {
            return strtolower(self::DEFAULT_LANGUAGE);
        } else {
            return $lang;
        }
    }
}

Localization::getSystemLanguage(); //< detect system language
