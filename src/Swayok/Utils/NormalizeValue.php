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

    /**
     * @param mixed $value
     * @return bool - false: value equals one of false, '0', 0; true: otherwise
     */
    static public function normalizeBoolean($value) {
        return (bool)$value;
    }

    /**
     * @param mixed $value
     * @param array $additionalFalseOptions - for example: ['n', 'no']
     * @return bool - false: value equals one of [false, '0', 0] or $additionalFalseOptions; true: otherwise
     */
    static public function normalizeBooleanExtended($value, array $additionalFalseOptions) {
        return !((bool)$value === false || in_array($value, $additionalFalseOptions, true));
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

    static public function normalizeDateTimeWithTz($value) {
        return date(static::DATETIME_WITH_TZ_FORMAT, is_numeric($value) ? $value : strtotime($value));
    }

    static public function normalizeJson($value) {
        if (is_string($value) && mb_strlen($value) >= 2 && preg_match('%^(\{.*\}|\[.*\])$%s', $value)) {
            return $value;
        } else if ($value === '') {
            return '""';
        } else if ($value === null) {
            return null;
        } else if (is_string($value) && preg_match('%^".*"$%', $value) && json_decode($value) !== null) {
            return $value;
        } else {
            return !is_string($value) && ValidateValue::isFloat($value) ? "$value" : json_encode($value, JSON_UNESCAPED_UNICODE);
        }
    }

}