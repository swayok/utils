<?php

namespace Swayok\Utils;

abstract class ValidateValue {

    const INTEGER_REGEXP = '%^-?\d+$%i';
    const FLOAT_REGEXP = '%^-?\d+(\.\d+)?$%i';
    const IP_ADDRESS_REGEXP = '%^(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)$%i';
    //    const EMAIL_REGEXP = '%^(([^<>()\[\].,;:\s@"*\'#$\%\^&=+\\\/!\?]+(\.[^<>()\[\],;:\s@"*\'#$\%\^&=+\\\/!\?]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$%i';
    const EMAIL_REGEXP = "%^[a-z0-9!#\$\%&'*+/=?\^_`{|}~-]+(?:\.[a-z0-9!#\$\%&'*+/=?\^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$%i"; //< http://www.regular-expressions.info/email.html
    const SHA1_REGEXP = "%^[a-fA-F0-9]{40}$%i";
    const MD5_REGEXP = "%^[a-fA-F0-9]{32}$%i";
    static protected $imageTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg'
    ];

    static public function isInteger(&$value, $convert = false) {
        if (is_int($value)){
            return true;
        } else if (is_string($value) && preg_match(self::INTEGER_REGEXP, $value)) {
            if ($convert) {
                $value = (int)$value;
            }
            return true;
        } else {
            return false;
        }
    }

    static public function isFloat(&$value, $convert = false) {
        if (is_float($value) || is_int($value)) {
            return true;
        } else if (is_string($value) && preg_match(self::FLOAT_REGEXP, $value)) {
            if ($convert) {
                $value = (float)$value;
            }
            return true;
        } else {
            return false;
        }
    }

    static public function isBoolean(&$value, $convert = false) {
        if (is_bool($value)) {
            return true;
        } else if (is_string($value) && ($value === '1' || $value === '0')) {
            if ($convert) {
                $value = $value === '1';
            }
            return true;
        } else if (is_int($value) && ($value === 1 || $value === 0)) {
            if ($convert) {
                $value = $value === 1;
            }
            return true;
        } else {
            return false;
        }
    }

    static public function isDateTime(&$value, $convertToUnixTs = false) {
        if (ValidateValue::isInteger($value)) {
            return $value > 0;
        } else if (is_string($value) && strtotime($value) !== 0) {
            if ($convertToUnixTs) {
                $value = strtotime($value);
            }
            return true;
        } else {
            return false;
        }
    }

    static public function isIpAddress($value) {
        return is_string($value) && preg_match(self::IP_ADDRESS_REGEXP, $value);
    }

    static public function isEmail($value) {
        return is_string($value) && preg_match(self::EMAIL_REGEXP, $value);
    }

    static public function isSha1($value) {
        return is_string($value) && preg_match(self::SHA1_REGEXP, $value);
    }

    static public function isMd5($value) {
        return is_string($value) && preg_match(self::MD5_REGEXP, $value);
    }

    static public function isJson(&$value, $decode = false) {
        $decoded = json_decode($value, true);
        if ($decoded !== false) {
            if ($decode) {
                $value = $decoded;
            }
            return true;
        }
        return false;
    }

    static public function isUploadedFile($value) {
        if (is_array($value)) {
            return (
                array_key_exists('error', $value) && $value['error'] === UPLOAD_ERR_OK
                && !empty($value['size'])
                && array_key_exists('tmp_name', $value)
            );
        } else if (is_object($value) && get_class($value) === '\Symfony\Component\HttpFoundation\File\UploadedFile') {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $value */
            return $value->isValid();
        }
        return false;
    }

    static public function isUploadedImage($value) {
        if (static::isUploadedFile($value)) {
            if (is_array($value)) {
                $extRegexp = implode('|', array_keys(static::$imageTypes));
                return preg_match("%^.+\.($extRegexp)$%i", $value['name']) > 0;
            } else if (is_object($value)) {
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $value */
                $mime = $value->getMimeType();
                if ($mime) {
                    return in_array($mime, static::$imageTypes, true);
                } else {
                    return in_array($value->getExtension(), array_keys(static::$imageTypes), true);
                }
            }
        }
        return false;
    }

    /**
     * $value must be in format: hh:mm or integer.
     * Range actually should be between -12:00 and +14:00 but it is not validated
     * @param string $value
     * @return bool
     */
    static public function isTimezoneOffset($value) {
        return (
            static::isInteger($value)
            || is_string($value) && preg_match('%^(-|+)?([0-1]\d|2[0-4]):[0-5]\d$%', $value)
        );
    }

    static public function isPhoneNumber($value) {
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        return $phoneUtil->isViablePhoneNumber($value);
    }

    static public function isInternationalPhoneNumber(&$value, $convertToInternational = false) {
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        if ($phoneUtil->isViablePhoneNumber($value)) {
            try {
                $phoneNumber = $phoneUtil->parse($value, null);
                if (!$phoneUtil->isValidNumber($phoneNumber)) {
                    return false;
                }
                if ($convertToInternational) {
                    $value = $phoneUtil->format($phoneNumber, \libphonenumber\PhoneNumberFormat::E164);
                }
                return true;
            } catch (\libphonenumber\NumberParseException $exc) {
                return false;
            }
        }
        return false;
    }
}