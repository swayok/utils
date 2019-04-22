<?php

namespace Swayok\Utils;

abstract class Curl {

    static public $curl = null;
    static public function curlPrepare($url, $options = false, $separateInstance = false) {
        if ($separateInstance) {
            $curl = curl_init($url);
        } else {
            if (empty(self::$curl)) {
                self::$curl = curl_init($url);
            } else {
                if (function_exists('curl_reset')) {
                    curl_reset(self::$curl);
                } else {
                    curl_close(self::$curl);
                    self::$curl = null;
                    self::$curl = curl_init($url);
                }
            }
            $curl = self::$curl;
        }
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_USERAGENT, 'Curl');
        if ($url) {
            $url = str_ireplace(' ', '%20', $url);
            curl_setopt($curl, CURLOPT_URL, $url);
        }
        if (!empty($options) && is_array($options)) {
            foreach ($options as $option => $value) {
                curl_setopt($curl, $option, $value);
            }
        }
        return $curl;
    }

    /**
     * @param resource|string $url - url string or curl resource
     * @param null|array $postData - not array: using GET request | array : POST data
     * @param bool|array $options
     * @param bool $close - true: close request after exec
     * @return array
     */
    static public function curlExec($url, $postData = null, $options = false, $close = true) {
        if (!is_array($options)) {
            $options = [];
        }
        /** @var array $options */
        if (!array_key_exists(CURLOPT_CUSTOMREQUEST, $options)) {
            if (!empty($postData)) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $postData;
            } else {
                if (!array_key_exists(CURLOPT_POST, $options)) {
                    $options[CURLOPT_POST] = false;
                }
                if (!array_key_exists(CURLOPT_POSTFIELDS, $options) && empty($options[CURLOPT_POST])) {
                    unset($options[CURLOPT_POSTFIELDS]); //< it seems that it is enough to set this to option to send post request
                }
            }
        }
        $curl = self::curlPrepare($url, $options);
        $response = curl_exec($curl);
        $ret = static::processResponse($curl, $response);
        if ($close) {
            curl_close($curl);
            self::$curl = null;
            unset($curl);
        }
        return $ret;
    }

    static public function processResponse($curl, $response) {
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($curl) ? curl_error($curl) : false;
        $requestUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        $result = array(
            'url' => $requestUrl,
            'http_code' => $code,
            'data' => $response,
            'curl_error' => $curlError
        );
        return $result;
    }

    static public function close() {
        if (self::$curl) {
            curl_close(self::$curl);
        }
    }

    static public function isValidResponse($curlResponse) {
        return empty($curlResponse['curl_error']) && $curlResponse['http_code'] < 400 && $curlResponse['http_code'] > 0;
    }

} 