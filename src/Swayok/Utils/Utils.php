<?php

namespace Swayok\Utils;

abstract class Utils
{
    
    public static function printToStr(): string
    {
        $ret = '';
        if (func_num_args()) {
            $data = print_r(func_get_args(), true);
            $ret .= "\n<PRE>\n" . $data . "\n</PRE>\n";
        }
        return $ret;
    }
    
    /**
     * @param bool $returnString
     * @param bool $printObjects
     * @param bool $htmlFormat
     * @param int $ignoreSomeLastTraces - ignore some traqces in the end (by default = 1 to ignore trace of this method)
     * @return string
     */
    public static function getBackTrace(
        bool $returnString = false,
        bool $printObjects = false,
        bool $htmlFormat = true,
        int $ignoreSomeLastTraces = 1
    ): string {
        $debug = debug_backtrace($printObjects);
        $ret = [];
        $file = 'unknown';
        $lineNum = '?';
        if ($ignoreSomeLastTraces) {
            $debug = array_slice($debug, 0, -$ignoreSomeLastTraces);
        }
        foreach ($debug as $index => $line) {
            if (!isset($line['file'])) {
                $line['file'] = '(probably) ' . $file;
                $line['line'] = '(probably) ' . $lineNum;
            } else {
                $file = $line['file'];
                $lineNum = $line['line'];
            }
            if (isset($line['class'])) {
                $function = $line['class'] . $line['type'] . $line['function'];
            } else {
                $function = $line['function'];
            }
            if (isset($line['args'])) {
                if (is_array($line['args'])) {
                    $args = [];
                    foreach ($line['args'] as $arg) {
                        if (is_array($arg)) {
                            $args[] = 'Array';
                        } elseif (is_object($arg)) {
                            $args[] = get_class($arg);
                        } elseif (is_null($arg)) {
                            $args[] = 'null';
                        } elseif ($arg === false) {
                            $args[] = 'false';
                        } elseif ($arg === true) {
                            $args[] = 'true';
                        } elseif (is_string($arg) && strlen($arg) > 200) {
                            $args[] = substr($arg, 0, 200);
                        } else {
                            $args[] = $arg;
                        }
                    }
                    $line['args'] = implode(' , ', $args);
                }
            } else {
                $line['args'] = '';
            }
            if ($htmlFormat) {
                $ret[] = '<b>#' . $index . '</b> [<span style="color: #FF7777">' . $line['file'] . '</span>]:' . $line['line'] . ' ' . $function . '(' . htmlentities(
                        $line['args']
                    ) . ')';
            } else {
                $ret[] = '#' . $index . ' [' . $line['file'] . ']:' . $line['line'] . ' ' . $function . '(' . $line['args'] . ')';
            }
            $log[] = '#' . $index . ' [' . $line['file'] . ']:' . $line['line'] . ' ' . $function . '(' . $line['args'] . ')';
        }
        if ($htmlFormat) {
            $ret = '<div style="position:relative; z-index:200; padding-left:10px; background-color:#DDDDDD; color:#000000; text-align:left;">' . implode(
                    '<br/>',
                    $ret
                ) . '</div><hr/>';
        } else {
            $ret = "\n" . implode("\n", $ret) . "\n";
        }
        
        if ($returnString) {
            return $ret;
        } else {
            echo $ret;
            return '';
        }
    }
    
    public static function isSuccessfullFileUpload($fileInfo): bool
    {
        return self::isFileUpload($fileInfo) && empty($fileInfo['error']) && !empty($fileInfo['size']);
    }
    
    public static function isFileUpload($fileInfo): bool
    {
        return is_array($fileInfo) && array_key_exists('tmp_name', $fileInfo) && array_key_exists('size', $fileInfo);
    }
    
    /**
     * Converts $value to required date-time format
     * @param int|string $value - int: unix timestamp | string: valid date/time/date-time string
     * @param string $format - resulting value format
     * @param string|int|bool $now - current unix timestamp or any valid strtotime() string
     * @return string
     */
    public static function formatDateTime($value, string $format, $now = 'now'): ?string
    {
        if (is_string($value) && strtotime($value) !== 0) {
            // convert string value to unix timestamp and then to required date format
            if (!is_string($now) && !is_int($now)) {
                $now = 'now';
            }
            if (empty($now) || strtolower($now) === 'now') {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $value = strtotime($value);
            } elseif (is_numeric($now)) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $value = strtotime($value, $now);
            } else {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $value = strtotime($value, strtotime($now));
            }
            if ($value === false) {
                return null;
            }
        }
        return ValidateValue::isInteger($value, false) ? date($format, $value) : null;
    }
}