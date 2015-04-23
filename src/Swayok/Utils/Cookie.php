<?php

namespace Swayok\Utils;

class CookieException extends \Exception {}

class Cookie {

    static public $encryptionKey;
    static protected $decryptedValues = array();
    static protected $encryptionPrefix = 'QxfH8kM2FtTk29t';

    /**
     * @return string
     * @throws CookieException
     */
    public static function getEncryptionKey() {
        if (empty(self::$encryptionKey)) {
            throw new CookieException('Encryption key is not provided');
        }
        return self::$encryptionKey;
    }

    /**
     * @param string $encryptionKey
     */
    public static function setEncryptionKey($encryptionKey) {
        self::$encryptionKey = $encryptionKey;
    }

    /**
     * Write a value to the $_COOKIE[$name];
     *
     * Note: By default all values are encrypted.
     * You must pass $encrypt false to store values as is
     *
     * Note: You must use this method before any output is sent to the browser.
     * Failure to do so will result in header already sent errors.
     *
     * @param string|array $name - array: associative array of cookies to set
     * @param mixed $value
     * @param int|string|null $expiresIn - int: number of seconds till expiration | string: valid value for strtotime() | empty: expires when browser closed
     * @param array $options = optional settings => array (
     *      'domain' => string|null,        //< restrict domain
     *      'path' => string|null,          //< restrict path where cookie can be
     *      'secure' => bool,               //< use cookie only in SSL,
     *      'encrypt' => bool,              //< false: don't encrypt value | true: encrypt value
     *      'httpOnly' => bool,             //< restrict using cookie only via http (default: true)
     * )
     * @return void
     */
    static public function set($name, $value = null, $expiresIn = 0, $options = array()) {
        if (headers_sent()) {
            return;
        }
        if (is_array($name) && empty($options) && !empty($value)) {
            $options = $value;
        }
        $defaults = array(
            'domain' => '',
            'path' => '/',
            'encrypt' => true,
            'secure' => false,
            'httpOnly' => true
        );
        if (!is_array($options)) {
            $options = $defaults;
        } else {
            $options = array_replace($defaults, $options);
        }
        if (!is_array($name)) {
            $name = array($name => $value);
        }

        foreach ($name as $key => $value) {
            self::$decryptedValues[$key] = $value;
            $expires = self::calcExpiration($expiresIn);
            if ($expires == 0 || $expires >= time()) {
                $_COOKIE[$key] = self::encrypt($value, $options['encrypt']);
            } else {
                $_COOKIE[$key] = '';
            }
            setcookie(
                $key,
                $_COOKIE[$key],
                $expires,
                $options['path'],
                $options['domain'],
                $options['secure'],
                $options['httpOnly']
            );
        }
    }

    /**
     * Calculate expiration time
     * @param int|string|null $time - int: number of seconds till expiration | string: valid value for strtotime() | empty: expires when browser closed
     * @return int
     */
    static protected function calcExpiration($time) {
        if (empty($time)) {
            $time = 0;
        } else if (is_numeric($time)) {
            $time = time() + intval($time);
        } else {
            $time = intval(strtotime($time));
        }
        return $time;
    }

    /**
     * Read the value of the $_COOKIE[$name];
     * @param string $name - Key of the value to be obtained. If none specified, obtain map key => values
     * @return string|null - value for specified key
     */
    static public function get($name = null) {
        if (is_null($name)) {
            // all cookies
            self::$decryptedValues = self::decrypt($_COOKIE);
            return self::$decryptedValues;
        } else if (empty(self::$decryptedValues[$name]) && isset($_COOKIE[$name])) {
            // single key
            self::$decryptedValues[$name] = self::decrypt($_COOKIE[$name]);
        }

        if (isset(self::$decryptedValues[$name])) {
            return self::$decryptedValues[$name];
        } else {
            return null;
        }
    }

    /**
     * Return true if given variable is set in cookie.
     * @param string $name - cookie key to test
     * @return boolean - true: cookie exists
     */
    static public function exists($name = null) {
        if (empty($name)) {
            return false;
        }
        return self::get($name) !== null;
    }

    /**
     * Delete a cookie value
     * Note: You must use this method before any output is sent to the browser.
     * Failure to do so will result in header already sent errors.
     * @param $name
     * @param null|string $domain - cookie domain, null: use Server::cookies_domain()
     * @param string $path - path where cookie can be used
     * @param bool $httpOnly - cookie used only via http (default: true)
     * @param bool $secure - cookie used only in SSL
     */
    static public function delete($name, $domain = '', $path = '/', $httpOnly = false, $secure = false) {
        $options = array(
            'domain' => $domain,
            'path' => $path,
            'secure' => $secure,
            'httpOnly' => $httpOnly,
        );
        self::set($name, '', -42000, $options);
        unset(self::$decryptedValues[$name], $_COOKIE[$name]);
    }

    /**
     * Encrypt $value using public $type method in Security class
     * @param string $value - Value to encrypt
     * @param bool $enctrypt - false: no encryption
     * @return string - Encoded values
     */
    static protected function encrypt($value, $enctrypt = true) {
        if (is_array($value)) {
            $value = self::implode($value);
        }
        if ($enctrypt && function_exists('mcrypt_encrypt')) {
            $value = self::$encryptionPrefix . base64_encode(Crypt::cookie_encrypt($value, self::getEncryptionKey()));
        }
        return $value;
    }

    /**
     * Decrypt $value using public $type method in Security class
     * @param array $values Values to decrypt
     * @return string decrypted string
     */
    static protected function decrypt($values) {
        $decrypted = null;
        if (!is_array($values)) {
            $values = array($values);
            $decrypted = null;
        } else {
            $decrypted = array();
        }
        foreach ((array)$values as $name => $value) {
            if (is_array($value)) {
                $decryptedVal = self::decrypt($value);
            } else {
                $pos = strpos($value, self::$encryptionPrefix);
                if ($pos === false) {
                    $decryptedVal = self::explode($value);
                } else {
                    $value = substr($value, strlen(self::$encryptionPrefix));
                    $decryptedVal = self::explode(Crypt::cookie_decrypt(base64_decode($value), self::getEncryptionKey()));
                }
            }
            if (is_array($decrypted)) {
                $decrypted[$name] = $decryptedVal;
            } else {
                $decrypted = $decryptedVal;
            }
        }
        return $decrypted;
    }

    /**
     * Used to implode arrays to store then in cookie values
     * @param array $array
     * @return string - A json encoded string.
     */
    static protected function implode(array $array) {
        return json_encode($array);
    }

    /**
     * Decodes imploded arrays stored in cookie values (reverts imploding)
     * @param string $string - A string containing JSON encoded data, or a bare string.
     * @return array|string - array: Map of key and values | string: $string
     */
    static protected function explode($string) {
        $first = substr($string, 0, 1);
        if ($first === '{' || $first === '[') {
            $ret = json_decode($string, true);
            return ($ret) ? $ret : $string;
        }
        return $string;
    }
}
