<?php

namespace Swayok\Utils;

abstract class ValidateValue {

    const INTEGER_REGEXP = '%^-?\d+(\.0+)?$%';
    const FLOAT_REGEXP = '%^-?\d+(\.\d+)?$%';
    const IP_ADDRESS_REGEXP = '%^(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)$%';
    //    const EMAIL_REGEXP = '%^(([^<>()\[\].,;:\s@"*\'#$\%\^&=+\\\/!\?]+(\.[^<>()\[\],;:\s@"*\'#$\%\^&=+\\\/!\?]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$%i';
    const EMAIL_REGEXP = "%^[a-z0-9!#\$\%&'*+/=?\^_`{|}~-]+(?:\.[a-z0-9!#\$\%&'*+/=?\^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$%i"; //< http://www.regular-expressions.info/email.html
    const SHA1_REGEXP = "%^[a-fA-F0-9]{40}$%";
    const MD5_REGEXP = "%^[a-fA-F0-9]{32}$%";
    const DATE_FORMAT = 'Y-m-d';
    const TIME_FORMAT = 'H:i:s';
    const DATETIME_FORMAT = 'Y-m-d H:i:s';
    const DATETIME_WITH_TZ_FORMAT = 'Y-m-d H:i:s P';
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
        } else if ((is_string($value) || is_numeric($value)) && preg_match(self::INTEGER_REGEXP, (string)$value)) {
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
            return (int)$value > 0;
        } else if (is_string($value) && strtotime($value) > 0) {
            if ($convertToUnixTs) {
                $value = strtotime($value);
            }
            return true;
        } else {
            return false;
        }
    }

    static public function isDateTimeWithTz($value) {
        if (is_string($value) && !is_numeric($value) && strtotime($value) > 0) {
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
        if (is_array($value) || is_bool($value) || $value === null) {
            return true;
        } else if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        $decoded = json_decode($value, true);
        if ($decoded !== null) {
            if ($decode) {
                $value = $decoded;
            }
            return true;
        }
        return false;
    }

    /**
     * @param mixed $value
     * @param bool $acceptNotUploadedFiles - true: use File::exist($value['tmp_name']) | false: use is_uploaded_file($value['tmp_name'])
     * @return bool
     */
    static public function isUploadedFile($value, $acceptNotUploadedFiles = false) {
        if (is_array($value)) {
            return (
                array_key_exists('error', $value)
                && $value['error'] === UPLOAD_ERR_OK
                && array_key_exists('size', $value)
                && (int)$value['size'] > 0
                && array_key_exists('tmp_name', $value)
                && array_key_exists('name', $value)
                && array_key_exists('type', $value)
                && (
                    ($acceptNotUploadedFiles && File::exist($value['tmp_name']))
                    || is_uploaded_file($value['tmp_name'])
                )
            );
        } else if (is_object($value) && $value instanceof \SplFileInfo) {
            $symphonyUpload = 'Symfony\Component\HttpFoundation\File\UploadedFile';
            if ($value instanceof $symphonyUpload) {
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $value */
                return (
                    $value->getError() === UPLOAD_ERR_OK
                    && $value->getSize() > 0
                    && (is_uploaded_file($value->getPathname()) || $acceptNotUploadedFiles)
                );
            } else if ($acceptNotUploadedFiles) {
                /** @var \SplFileInfo $value */
                return $value->isFile() && $value->getSize() > 0;
            }
        }
        return false;
    }

    /**
     * @param mixed $value
     * @param bool $acceptNotUploadedFiles - true: use File::exist($value['tmp_name']) | false: use is_uploaded_file($value['tmp_name'])
     * @return bool
     */
    static public function isUploadedImage($value, $acceptNotUploadedFiles = false) {
        if (static::isUploadedFile($value, $acceptNotUploadedFiles)) {
            if (is_array($value)) {
                $extRegexp = implode('|', array_keys(static::$imageTypes));
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($value['tmp_name']);
                return (
                    in_array($mime, static::$imageTypes, true)
                    || in_array($value['type'], static::$imageTypes, true)
                    || preg_match("%^.+\.($extRegexp)$%i", $value['name']) > 0
                );
            } else if ($value instanceof \SplFileInfo) {
                if (get_class($value) === 'Symfony\Component\HttpFoundation\File\UploadedFile') {
                    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $value */
                    $mime = $value->getMimeType();
                } else {
                    /** @var \SplFileInfo $value */
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($value->getRealPath());
                }
                if (!empty($mime)) {
                    return in_array($mime, static::$imageTypes, true);
                } else {
                    return array_key_exists($value->getExtension(), static::$imageTypes);
                }
            }
        }
        return false;
    }

    /**
     * $value must be in format: hh:mm or integer.
     * Range actually should be between -12:00 and +14:00 but it is not validated so it might be
     * in range from -23:59:59 to +23:59:59
     * @param string $value
     * @return bool
     */
    static public function isTimezoneOffset($value) {
        return (
            (static::isInteger($value, true) && $value > -86400 && $value < 86400)
            || (is_string($value) && preg_match('%^(-|\+)?([0-1]\d|2[0-3]):[0-5]\d(:[0-6]\d)?$%', $value))
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

    /**
     * Validates if jpeg file is not currupted
     * @param $filePath
     * @return bool
     */
    static public function isCorruptedJpeg($filePath) {
        $file = fopen($filePath, 'rb');
        $ret = false;
        // check for the existence of the EOI segment header at the end of the file
        if (0 !== fseek($file, -2, SEEK_END) || "\xFF\xD9" !== fread($file, 2)) {
            $ret = true;
        }
        fclose($file);
        return $ret;
    }
}