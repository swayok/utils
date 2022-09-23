<?php

namespace Swayok\Utils;

/**
 * Note: all values must be validated via methods of class ValidateValue
 */
abstract class NormalizeValue
{
    
    const DATE_FORMAT = 'Y-m-d';
    const TIME_FORMAT = 'H:i:s';
    const DATETIME_FORMAT = 'Y-m-d H:i:s';
    const DATETIME_WITH_TZ_FORMAT = 'Y-m-d H:i:s P';
    
    /**
     * @param mixed $value
     * @return bool - false: value equals one of false, '0', 0; true: otherwise
     */
    public static function normalizeBoolean($value)
    {
        return (bool)$value;
    }
    
    /**
     * @param mixed $value
     * @param array $additionalFalseOptions - for example: ['n', 'no']
     * @return bool - false: value equals one of [false, '0', 0] or $additionalFalseOptions; true: otherwise
     */
    public static function normalizeBooleanExtended($value, array $additionalFalseOptions)
    {
        return !((bool)$value === false || in_array($value, $additionalFalseOptions, true));
    }
    
    public static function normalizeInteger($value)
    {
        return (int)$value;
    }
    
    public static function normalizeFloat($value)
    {
        return (float)$value;
    }
    
    public static function normalizeDate($value)
    {
        return date(static::DATE_FORMAT, is_numeric($value) ? $value : strtotime($value));
    }
    
    public static function normalizeTime($value)
    {
        return date(static::TIME_FORMAT, is_numeric($value) ? $value : strtotime($value));
    }
    
    public static function normalizeDateTime($value)
    {
        return date(static::DATETIME_FORMAT, is_numeric($value) ? $value : strtotime($value));
    }
    
    public static function normalizeDateTimeWithTz($value)
    {
        return date(static::DATETIME_WITH_TZ_FORMAT, is_numeric($value) ? $value : strtotime($value));
    }
    
    public static function normalizeJson($value)
    {
        if (is_string($value) && mb_strlen($value) >= 2 && preg_match('%^(\{.*\}|\[.*\])$%s', $value)) {
            return $value;
        } elseif ($value === '') {
            return '""';
        } elseif ($value === null) {
            return null;
        } elseif (is_string($value) && preg_match('%^".*"$%', $value) && json_decode($value) !== null) {
            return $value;
        } else {
            return !is_string($value) && ValidateValue::isFloat($value) ? "$value" : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
    }
    
}