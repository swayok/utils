<?php

namespace Swayok\Utils;

abstract class Curl
{
    
    static public $curl;
    
    /**
     * @param string $url
     * @param array $options
     * @param bool $separateInstance - true: always create new curl instance | false: use self::$curl instance if possible
     * @return false|resource|null
     */
    public static function curlPrepare($url, array $options = [], $separateInstance = false)
    {
        if ($separateInstance) {
            $curl = curl_init($url);
        } else {
            if (empty(self::$curl)) {
                self::$curl = curl_init($url);
            } else {
                if (function_exists('curl_reset')) {
                    curl_reset(self::$curl);
                } else {
                    self::close();
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
        foreach ($options as $option => $value) {
            curl_setopt($curl, $option, $value);
        }
        return $curl;
    }
    
    /**
     * @param resource|string $url - url string or curl resource
     * @param null|array|string $postData
     *  - empty: GET request
     *  - array: POST data
     *  - string: encoded POST data (url-encoded or json)
     * @param array $options
     * @param bool $close - true: close request after exec
     * @return array
     */
    public static function curlExec($url, $postData = null, array $options = [], $close = true)
    {
        static::addHttpMethodOptions($options, $postData);
        $curl = self::curlPrepare($url, $options);
        $response = curl_exec($curl);
        $ret = static::processResponse($curl, $response);
        if ($close) {
            static::close();
            unset($curl);
        }
        return $ret;
    }
    
    /**
     * Request options:
     * - 'url' - required
     * - 'data' - optional, null or absent - GET request unless it is specified in 'options', array|string - POST request
     * - 'data_is_json' - optional, true: add http header 'Content-Type: application/json' and convert 'data' to json if it is an array
     * - 'options' - optional, array, list of curl options
     * - 'extra' - optional, mixed, pass this value to response
     *
     * Example:
     * $requests = [
     *      'profile' => [
     *          'url' => 'https://domain.com/api/user/profile,
     *          'extra' => [
     *              'some_key' => 'some_value'
     *          ]
     *      ],
     *      'json_profile' => [
     *          'url' => 'https://domain.com/api/user/profile,
     *          'options' => [
     *              CURLOPT_USERAGENT => 'Application',
     *              CURLOPT_HTTPHEADER => [
     *                  'Accept: application/json'
     *              ]
     *          ]
     *      ],
     *      'register' => [
     *          'url' => 'https://domain.com/api/user/register,
     *          'data' => ['login' => 'newuser', 'password' => '123123']
     *      ],
     * ]
     *
     * Response data:
     * - 'url' - string, requested URL
     * - 'http_code' - int, HTTP code received from requested URL
     * - 'data' - string, data received from requested URL
     * - 'curl_error' - int, curl error code
     * - 'is_success' - bool, was request successful or not? (analyzes 'http_code' and 'curl_error')
     * - 'extra' - mixed, data passed via 'extra' key in request options
     *
     * Example:
     * $responses = [
     *      'profile' => [
     *          'url' => 'https://domain.com/api/user/profile,
     *          'http_code' => 200,
     *          'data' => '<html><head>...</head><body>...</body></html>',
     *          'curl_error' => 0,
     *          'is_success' => true,
     *          'extra' => [
     *              'some_key' => 'some_value'
     *          ]
     *      ],
     *      'json_profile' => [
     *          'url' => 'https://domain.com/api/user/profile,
     *          'http_code' => 200,
     *          'data' => '{"login":"newuser","name":"the user"}',
     *          'curl_error' => 0,
     *          'is_success' => true,
     *      ],
     *      'register' => [
     *          'url' => 'https://domain.com/api/user/register,
     *          'http_code' => 500,
     *          'data' => '{"error":"Server error"}',
     *          'curl_error' => 0,
     *          'is_success' => false,
     *      ],
     * ]
     * @param array $requests - contains list of requests options. key - request id, value - request options
     * @return array - list of responses
     */
    public static function curlMultiExec(array $requests)
    {
        if (empty($requests)) {
            return [];
        }
        // create curl requests
        $multi = curl_multi_init();
        foreach ($requests as $requestId => &$requestInfo) {
            $postData = isset($requestInfo['data']) ? $requestInfo['data'] : null;
            $options = isset($requestInfo['options']) ? $requestInfo['options'] : null;
            if (!is_array($options)) {
                $options = [];
            }
            if (isset($postData)) {
                if (array_key_exists('data_is_json', $postData)) {
                    // json POST request
                    static::addJsonPostDataRequestOptions($options, $postData);
                } else {
                    // form data POST request
                    static::addHttpMethodOptions($options, $postData);
                }
            }
            $requestInfo['curl'] = static::curlPrepare($requestInfo['url'], $options, true);
            curl_multi_add_handle($multi, $requestInfo['curl']);
        }
        unset($requestInfo);
        
        // run and wait until all requests finished
        $running = count($requests);
        do {
            curl_multi_exec($multi, $running);
        } while ($running > 0);
        
        // handle responses
        $responses = [];
        foreach ($requests as $requestId => $requestInfo) {
            $response = curl_multi_getcontent($requestInfo['curl']);
            $responses[$requestId] = static::processResponse($requestInfo['curl'], $response, true);
            if (isset($requestInfo['extra'])) {
                $responses[$requestId]['extra'] = $requestInfo['extra'];
            }
            curl_multi_remove_handle($multi, $requestInfo['curl']);
            curl_close($requestInfo['curl']);
        }
        
        curl_multi_close($multi);
        
        return $responses;
    }
    
    /**
     * @param array $options
     * @param null|array|string $postData
     *  - empty: GET request
     *  - array: POST data
     *  - string: encoded POST data (url-encoded or json)
     */
    public static function addHttpMethodOptions(array &$options, $postData = null)
    {
        if (isset($options[CURLOPT_CUSTOMREQUEST])) {
            // ignore requests with custom type (PUT, DELETE, etc..)
            return;
        }
        if (!empty($postData)) {
            // POST request
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $postData;
        } elseif (!empty($options[CURLOPT_POSTFIELDS])) {
            // we have CURLOPT_POSTFIELDS option set so this is a POST request anyway
            $options[CURLOPT_POST] = true;
        } elseif (!isset($options[CURLOPT_POST])) {
            // going to do GET request unless CURLOPT_POST specified explicitly
            $options[CURLOPT_POST] = false;
        }
    }
    
    /**
     * @param array $options
     * @param string|array $json - array will be passed to json_encode()
     */
    public static function addJsonPostDataRequestOptions(array &$options, $json)
    {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = is_array($json) ? json_encode($json) : $json;
        if (!isset($options[CURLOPT_HTTPHEADER])) {
            $options[CURLOPT_HTTPHEADER] = [];
        }
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    
    /**
     * @param array $options
     * @param array $headers - headers to add. Format: ['Content-Type: application/json'] or ['Content-Type' => 'application/json']
     */
    public static function addHttpHeadersToOptions(array &$options, array $headers)
    {
        if (!isset($options[CURLOPT_HTTPHEADER])) {
            $options[CURLOPT_HTTPHEADER] = [];
        }
        foreach ($headers as $key => $value) {
            if (is_string($key)) {
                $options[CURLOPT_HTTPHEADER][] = $key . ': ' . $value;
            } else {
                $options[CURLOPT_HTTPHEADER][] = $value;
            }
        }
    }
    
    /**
     * @param resource $curl
     * @param string $response
     * @param bool $validate - true: perform static::isValidResponse($result) and return result in 'is_success' key
     * @return array = [
     *      'url' => string, requested URL
     *      'http_code' => int, HTTP code received from requested URL
     *      'data' => string, data received from requested URL
     *      'curl_error' => int, curl error code
     *      'is_success' => only if $validate === true - bool, was request successful or not? static::isValidResponse($result)
     * ]
     */
    public static function processResponse($curl, $response, $validate = false)
    {
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($curl) ? curl_error($curl) : false;
        $requestUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        $result = [
            'url' => $requestUrl,
            'http_code' => $code,
            'data' => $response,
            'curl_error' => $curlError,
        ];
        if ($validate) {
            $result['is_success'] = static::isValidResponse($result);
        }
        return $result;
    }
    
    public static function close()
    {
        if (self::$curl) {
            curl_close(self::$curl);
        }
        self::$curl = null;
    }
    
    /**
     * @param array $curlResponse
     * @return bool
     */
    public static function isValidResponse(array $curlResponse)
    {
        return empty($curlResponse['curl_error']) && $curlResponse['http_code'] < 400 && $curlResponse['http_code'] > 0;
    }
    
} 