<?php

namespace Swayok\Utils;

/**
 * Note: all values must be validated via methods of class ValidateValue
 */
abstract class NormalizeValue {

    const DATE_FORMAT = 'Y-m-d';
    const TIME_FORMAT = 'H:i:s';
    const DATETIME_FORMAT = 'Y-m-d H:i:s';
    const DATETIME_WITH_TZ_FORMAT = 'Y-m-d H:i:s P';

    static public function normalizeBoolean($value) {
        return !in_array($value, [false, '0', 0], true);
    }

    static public function normalizeInteger($value) {
        return (int)$value;
    }

    static public function normalizeFloat($value) {
        return (float)$value;
    }

    static public function normalizeDate($value) {
        return date(static::DATE_FORMAT, is_numeric($value) ? $value : strtotime($value));
    }

    static public function normalizeTime($value) {
        return date(static::TIME_FORMAT, is_numeric($value) ? $value : strtotime($value));
    }

    static public function normalizeDateTime($value) {
        return date(static::DATETIME_FORMAT, is_numeric($value) ? $value : strtotime($value));
    }

    static public function normalizeTimezoneOffset($value) {
        return is_numeric($value) ? (int)$value : strtotime($value);
    }

    static public function normalizeJson($value) {
        if (is_string($value) && mb_strlen($value) >= 2 && preg_match('%^(\{.*\}|\[.*\])$%s', $value)) {
            return $value;
        } else {
            return ValidateValue::isInteger($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }
    }

}