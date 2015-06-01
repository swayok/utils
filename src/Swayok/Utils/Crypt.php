<?php

namespace Swayok\Utils;

class Crypt {

    const ENCRYPT = 'encrypt';
    const DECRYPT = 'decrypt';
    const AES256_CBC_NOPAD = 'aes256CbcNoPad';
    const COOKIE = 'cookie';

    /**
     * Encrypt $value using public $type method in Security class
     * @param string $method - encryprion method
     * @param string $data - Value to encrypt
     * @param string $password - Encryption key
     * @return string - Encoded values
     */
    static public function encrypt($method, $data, $password) {
        if (is_array($data)) {
            $data = Utils::jsonEncodeCyrillic($data);
        }
        if (function_exists('mcrypt_encrypt')) {
            $method = $method . '_encrypt';
            $data = base64_encode(self::$method($data, $password));
        }
        return $data;
    }

    /**
     * Decrypt $value using public $type method in Security class
     * @param string $method - encryprion method
     * @param array|string $data - Values to decrypt
     * @param string $password - Encryption key
     * @param bool $jsonExpected
     * @return string|array - decrypted string or array of decrypted strings
     */
    static public function decrypt($method, $data, $password, $jsonExpected = false) {
        $decrypted = null;
        if (!is_array($data)) {
            $data = array($data);
            $decrypted = null;
        } else {
            $decrypted = array();
        }
        foreach ((array)$data as $name => $value) {
            if (is_array($value)) {
                $decryptedVal = self::decrypt($method, $value, $password);
            } else {
                $method = $method . '_decrypt';
                $decryptedVal = self::$method(base64_decode($value), $password);
                if ($jsonExpected) {
                    $decryptedValJson = json_decode(preg_replace('%(.*[\}\]])[^\}\]]*?$%s', '$1', $decryptedVal), true);
                    if (!empty($decryptedValJson)) {
                        $decryptedVal = $decryptedValJson;
                    }
                } else if (
                    in_array($decryptedVal[0], array('{', '[')) &&
                    in_array($decryptedVal[strlen($decryptedVal) - 1], array('}', ']'))
                ) {
                    $decryptedValJson = json_decode($decryptedVal, true);
                    if (!empty($decryptedValJson)) {
                        $decryptedVal = $decryptedValJson;
                    }
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
     * Encrypt a text using AES256/CBC/NOPAD method.
     * @param string $text - Normal string to encrypt
     * @param string $password
     * @return string
     * @throws \Exception
     */
    static public function aes256CbcNoPad_encrypt($text, $password) {
        if (empty($password)) {
            throw new \Exception('Crypt: Empty security key');
        }
        $algorithm = MCRYPT_RIJNDAEL_128;
        $mode = MCRYPT_MODE_CBC;

        $ivSize = mcrypt_get_iv_size($algorithm, $mode);
        $iv = mcrypt_create_iv($ivSize, MCRYPT_RAND);
        $cryptKey = md5($password);

        return $iv . '$$' . mcrypt_encrypt($algorithm, $cryptKey, $text, $mode, $iv);
    }

    /**
     * Decrypt a text using AES256/CBC/NOPAD method.
     * @param string $text - Encrypted string to decrypt
     * @param string $password
     * @return string
     * @throws \Exception
     */
    static public function aes256CbcNoPad_decrypt($text, $password) {
        if (empty($password)) {
            throw new \Exception('Crypt: Empty security key');
        }
        $algorithm = MCRYPT_RIJNDAEL_128;
        $mode = MCRYPT_MODE_CBC;

        $ivSize = mcrypt_get_iv_size($algorithm, $mode);
        $iv = substr($text, 0, $ivSize);
        $cryptKey = md5($password);
        // Backwards compatible decrypt with fixed iv
        $text = (substr($text, $ivSize, 2) === '$$')
            ? substr($text, $ivSize + 2)
            : substr($text, $ivSize);

        return rtrim(mcrypt_decrypt($algorithm, $cryptKey, $text, $mode, $iv), "\x0b\n \r\t");
    }

    /**
     * Encrypt a text using RIJNDAEL256/CBC/NOPAD method.
     * @param string $text - Normal string to encrypt
     * @param string $password
     * @return string
     * @throws \Exception
     */
    static public function cookie_encrypt($text, $password) {
        if (strlen($password) < 32) {
            throw new CookieException('Crypt: security key must contain at least 32 symbols');
        }
        $algorithm = MCRYPT_RIJNDAEL_256;
        $mode = MCRYPT_MODE_CBC;

        $ivSize = mcrypt_get_iv_size($algorithm, $mode);
        $iv = mcrypt_create_iv($ivSize, MCRYPT_RAND);
        $cryptKey = substr($password, 0, 32);

        return $iv . '$$' . mcrypt_encrypt($algorithm, $cryptKey, $text, $mode, $iv);
    }

    /**
     * Decrypt a text using RIJNDAEL256/CBC/NOPAD method.
     * @param string $text - Encrypted string to decrypt
     * @param string $password
     * @return string
     * @throws \Exception
     */
    static public function cookie_decrypt($text, $password) {
        if (strlen($password) < 32) {
            throw new CookieException('Crypt: security key must contain at least 32 symbols');
        }

        $algorithm = MCRYPT_RIJNDAEL_256;
        $mode = MCRYPT_MODE_CBC;

        $ivSize = mcrypt_get_iv_size($algorithm, $mode);
        $iv = substr($text, 0, $ivSize);
        if ($ivSize + 2 >= strlen($text)) {
            throw new \Exception('Hack attempt via encrypted cookie');
        }
        $text = substr($text, $ivSize + 2);
        $cryptKey = substr($password, 0, 32);

        return rtrim(mcrypt_decrypt($algorithm, $cryptKey, $text, $mode, $iv), "\0");
    }
}