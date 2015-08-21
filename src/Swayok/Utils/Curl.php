<?php

namespace Swayok\Utils;

abstract class Curl {

    static public $curl = null;
    static public function curlPrepare($url, $options = false) {
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
        curl_setopt(self::$curl, CURLOPT_HEADER, false);
        curl_setopt(self::$curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(self::$curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt(self::$curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(self::$curl, CURLOPT_MAXREDIRS, 5);
        curl_setopt(self::$curl, CURLOPT_ENCODING, '');
        curl_setopt(self::$curl, CURLOPT_USERAGENT, 'CamOnRoad.com');
        if ($url) {
            $url = str_ireplace(' ', '%20', $url);
            curl_setopt(self::$curl, CURLOPT_URL, $url);
        }
        if (!empty($options) && is_array($options)) {
            foreach ($options as $option => $value) {
                curl_setopt(self::$curl, $option, $value);
            }
        }
        return self::$curl;
    }

    /**
     * @param resource|string $url - url string or curl resource
     * @param null|array $postData - not array: using GET request | array : POST data
     * @param bool|array $options
     * @param bool $close - true: close request after exec
     * @return array
     */
    static public function curlExec($url, $postData = null, $options = false, $close = true) {
        $curl = self::curlPrepare($url, $options);
        if (!empty($postData)) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        } else {
            curl_setopt($curl, CURLOPT_POSTFIELDS, array());
            curl_setopt($curl, CURLOPT_POST, false);
        }
        $response = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($curl) ? curl_error($curl) : false;
        $requestUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        if ($close) {
            curl_close($curl);
            self::$curl = null;
            unset($curl);
        }
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