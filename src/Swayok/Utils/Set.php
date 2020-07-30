<?php
/**
 * Library of array functions for Cake. Significantly shortened
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Utility
 * @since         CakePHP(tm) v 1.2.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Swayok\Utils;

/**
 * Class used for manipulation of arrays.
 *
 */
class Set {

    /**
     * Maps the given value as an object. If $value is an object,
     * it returns $value. Otherwise it maps $value as an object of
     * type $class, and if primary assign _name_ $key on first array.
     * If $value is not empty, it will be used to set properties of
     * returned object (recursively). If $key is numeric will maintain array
     * structure
     *
     * @param array $array Array to map
     * @param string $class Class name
     * @param boolean $primary whether to assign first array key as the _name_
     * @return mixed Mapped object
     */
    protected static function _map(&$array, $class, $primary = false) {
        if ($class === true) {
            $out = new \stdClass();
        } else {
            $out = new $class;
        }
        if (is_array($array)) {
            $keys = array_keys($array);
            foreach ($array as $key => $value) {
                if ($keys[0] === $key && $class !== true) {
                    $primary = true;
                }
                if (is_numeric($key)) {
                    if (is_object($out)) {
                        $out = get_object_vars($out);
                    }
                    $out[$key] = self::_map($value, $class);
                    if (is_object($out[$key])) {
                        if ($primary !== true && is_array($value) && self::countDim($value, true) === 2) {
                            if (!isset($out[$key]->_name_)) {
                                $out[$key]->_name_ = $primary;
                            }
                        }
                    }
                } elseif (is_array($value)) {
                    if ($primary === true) {
                        // @codingStandardsIgnoreStart Legacy junk
                        if (!isset($out->_name_)) {
                            $out->_name_ = $key;
                        }
                        // @codingStandardsIgnoreEnd
                        $primary = false;
                        foreach ($value as $key2 => $value2) {
                            $out->{$key2} = self::_map($value2, true);
                        }
                    } else {
                        if (!is_numeric($key)) {
                            $out->{$key} = self::_map($value, true, $key);
                            if (is_object($out->{$key}) && !is_numeric($key)) {
                                if (!isset($out->{$key}->_name_)) {
                                    $out->{$key}->_name_ = $key;
                                }
                            }
                        } else {
                            $out->{$key} = self::_map($value, true);
                        }
                    }
                } else {
                    $out->{$key} = $value;
                }
            }
        } else {
            $out = $array;
        }
        return $out;
    }

    /**
     * Checks to see if all the values in the array are numeric
     *
     * @param array $array The array to check. If null, the value of the current Set object
     * @return boolean true if values are numeric, false otherwise
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::numeric
     */
    protected static function numeric($array = null) {
        if (empty($array)) {
            return false;
        }
        $values = array_values($array);
        $str = implode('', $values);
        return (bool)ctype_digit($str);
    }

    /**
     * Returns a series of values extracted from an array, formatted in a format string.
     *
     * @param array $data Source array from which to extract the data
     * @param string $format Format string into which values will be inserted, see sprintf()
     * @param array $keys An array containing one or more self::extract()-style key paths
     * @return array An array of strings extracted from $keys and formatted with $format
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::format
     */
    public static function format($data, $format, $keys) {
        $extracted = array();
        $count = count($keys);

        if (!$count) {
            return false;
        }

        $data = self::prepareDataArray($data);
        for ($i = 0; $i < $count; $i++) {
            $extracted[] = self::extract($data, $keys[$i]);
        }
        $out = array();
        $data = $extracted;
        $count = count($data[0]);

        if (preg_match_all('/\{([0-9]+)\}/msi', $format, $keys2) && isset($keys2[1])) {
            $keys = $keys2[1];
            $format = preg_split('/\{([0-9]+)\}/msi', $format);
            $count2 = count($format);

            for ($j = 0; $j < $count; $j++) {
                $formatted = '';
                for ($i = 0; $i <= $count2; $i++) {
                    if (isset($format[$i])) {
                        $formatted .= $format[$i];
                    }
                    if (isset($keys[$i]) && isset($data[$keys[$i]][$j])) {
                        $formatted .= $data[$keys[$i]][$j];
                    }
                }
                $out[] = $formatted;
            }
        } else {
            $count2 = count($data);
            for ($j = 0; $j < $count; $j++) {
                $args = array();
                for ($i = 0; $i < $count2; $i++) {
                    if (array_key_exists($j, $data[$i])) {
                        $args[] = $data[$i][$j];
                    }
                }
                $out[] = vsprintf($format, $args);
            }
        }
        return $out;
    }

    /**
     * Implements partial support for XPath 2.0. If $path does not contain a '/' the call
     * is delegated to self::classicExtract(). Also the $path and $data arguments are
     * reversible.
     *
     * #### Currently implemented selectors:
     *
     * - /User/id (similar to the classic {n}.User.id)
     * - /User[2]/name (selects the name of the second User)
     * - /User[id>2] (selects all Users with an id > 2)
     * - /User[id>2][<5] (selects all Users with an id > 2 but < 5)
     * - /Post/Comment[author_name=john]/../name (Selects the name of all Posts that have at least one Comment written by john)
     * - /Posts[name] (Selects all Posts that have a 'name' key)
     * - /Comment/.[1] (Selects the contents of the first comment)
     * - /Comment/.[:last] (Selects the last comment)
     * - /Comment/.[:first] (Selects the first comment)
     * - /Comment[text=/cakephp/i] (Selects the all comments that have a text matching the regex /cakephp/i)
     * - /Comment/@* (Selects the all key names of all comments like foreach($comments as $comment) array_keys($comments))
     * - /Comment/@ (Selects the indexes/keys of all comments like array_keys($comments))
     *
     * #### Other limitations:
     *
     * - Only absolute paths starting with a single '/' are supported right now
     *
     * **Warning**: Even so it has plenty of unit tests the XPath support has not gone through a lot of
     * real-world testing. Please report Bugs as you find them. Suggestions for additional features to
     * implement are also very welcome!
     *
     * @param string $path An absolute XPath 2.0 path
     * @param array $data An array of data to extract from
     * @param array $options Currently only supports 'flatten' which can be disabled for higher XPath-ness
     * @return array An array of matched items
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::extract
     */
    public static function extract($path, $data = null, $options = array()) {
        if (is_string($data)) {
            $tmp = $data;
            $data = $path;
            $path = $tmp;
        }
        if (empty($data)) {
            return array();
        }
        if ($path === '/') {
            return $data;
        } else if ($path === '@') {
            return array_keys($data);
        }

        $contexts = $data;
        $options = array_merge(array('flatten' => true), $options);
        if (!isset($contexts[0])) {
            $current = current($data);
            if ((is_array($current) && count($data) < 1) || !is_array($current) || !self::numeric(array_keys($data))) {
                $contexts = array($data);
            }
        }
        $tokens = array_slice(preg_split('/(?<!=|\\\\)\/(?![a-z-\s]*\])/', $path), 1);

        do {
            $token = array_shift($tokens);
            /** @var array|bool $conditions */
            $conditions = false;
            if (preg_match_all('/\[([^=]+=\/[^\/]+\/|[^\]]+)\]/', $token, $m)) {
                $conditions = $m[1];
                $token = substr($token, 0, strpos($token, '['));
            }
            $matches = array();
            foreach ($contexts as $key => $context) {
                if (!isset($context['trace'])) {
                    $context = array('trace' => array(null), 'item' => $context, 'key' => $key);
                }
                if ($token === '..') {
                    if (count($context['trace']) === 1) {
                        $context['trace'][] = $context['key'];
                    }
                    $parent = implode('/', $context['trace']) . '/.';
                    $context['item'] = self::extract($parent, $data);
                    $context['key'] = array_pop($context['trace']);
                    if (isset($context['trace'][1]) && $context['trace'][1] > 0) {
                        $context['item'] = $context['item'][0];
                    } elseif (!empty($context['item'][$key])) {
                        $context['item'] = $context['item'][$key];
                    } else {
                        $context['item'] = array_shift($context['item']);
                    }
                    $matches[] = $context;
                    continue;
                }
                if ($token === '@' && is_array($context['item'])) {
                    $matches[] = array(
                        'trace' => array_merge($context['trace'], (array)$key),
                        'key' => $key,
                        'item' => $key,
                    );
                } elseif ($token === '@*' && is_array($context['item'])) {
                    $matches[] = array(
                        'trace' => array_merge($context['trace'], (array)$key),
                        'key' => $key,
                        'item' => array_keys($context['item']),
                    );
                } elseif (is_array($context['item'])
                    && array_key_exists($token, $context['item'])
                    && !(strval($key) === strval($token) && count($tokens) === 1 && $tokens[0] === '.')) {
                    $items = $context['item'][$token];
                    if (!is_array($items)) {
                        $items = array($items);
                    } elseif (!isset($items[0])) {
                        $current = current($items);
                        $currentKey = key($items);
                        if (!is_array($current) || (is_array($current) && count($items) <= 1 && !is_numeric($currentKey))) {
                            $items = array($items);
                        }
                    }

                    foreach ($items as $key2 => $item) {
                        $ctext = array($context['key']);
                        if (!is_numeric($key2)) {
                            $ctext[] = $token;
                            $tok = array_shift($tokens);
                            if (isset($items[$tok])) {
                                $ctext[] = $tok;
                                $item = $items[$tok];
                                $matches[] = array(
                                    'trace' => array_merge($context['trace'], $ctext),
                                    'key' => $tok,
                                    'item' => $item,
                                );
                                break;
                            } elseif ($tok !== null) {
                                array_unshift($tokens, $tok);
                            }
                        } else {
                            $key2 = $token;
                        }

                        $matches[] = array(
                            'trace' => array_merge($context['trace'], $ctext),
                            'key' => $key2,
                            'item' => $item,
                        );
                    }
                } elseif ($key === $token || (ctype_digit($token) && $key == $token) || $token === '.') {
                    $context['trace'][] = $key;
                    $matches[] = array(
                        'trace' => $context['trace'],
                        'key' => $key,
                        'item' => $context['item'],
                    );
                }
            }
            if ($conditions) {
                foreach ($conditions as $condition) {
                    $filtered = array();
                    $length = count($matches);
                    foreach ($matches as $i => $match) {
                        if (self::matches(array($condition), $match['item'], $i + 1, $length)) {
                            $filtered[$i] = $match;
                        }
                    }
                    $matches = $filtered;
                }
            }
            $contexts = $matches;

            if (empty($tokens)) {
                break;
            }
        } while (1);

        $r = array();

        foreach ($matches as $match) {
            if ((!$options['flatten'] || is_array($match['item'])) && !is_int($match['key'])) {
                $r[] = array($match['key'] => $match['item']);
            } else {
                $r[] = $match['item'];
            }
        }
        return $r;
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    static protected function prepareDataArray($data) {
        if (is_object($data)) {
            if ($data instanceof \Traversable) {
                $data = self::convertIteratableObjectToArray($data);
            } else if (!($data instanceof \ArrayAccess)) {
                $data = get_object_vars($data);
            }
        }
        return $data;
    }

    /**
     * @param \Traversable $object
     * @return array;
     */
    static protected function convertIteratableObjectToArray(\Traversable $object) {
        $ret = array();
        foreach ($object as $key => $item) {
            if ($item instanceof \Traversable) {
                $ret[$key] = static::convertIteratableObjectToArray($item);
            } else {
                $ret[$key] = $item;
            }
        }
        return $ret;
    }

    /**
     * This function can be used to see if a single item or a given xpath match certain conditions.
     *
     * @param string|array $conditions An array of condition strings or an XPath expression
     * @param array $data An array of data to execute the match on
     * @param integer $i Optional: The 'nth'-number of the item being matched.
     * @param integer $length
     * @return boolean
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::matches
     */
    protected static function matches($conditions, $data = array(), $i = null, $length = null) {
        if (empty($conditions)) {
            return true;
        }
        $data = self::prepareDataArray($data);
        if (is_string($conditions)) {
            return (bool)self::extract($conditions, $data);
        }
        foreach ($conditions as $condition) {
            if ($condition === ':last') {
                if ($i !== $length) {
                    return false;
                }
                continue;
            } elseif ($condition === ':first') {
                if ($i !== 1) {
                    return false;
                }
                continue;
            }
            if (!preg_match('/(.+?)([><!]?[=]|[><])(.*)/', $condition, $match)) {
                if (ctype_digit($condition)) {
                    if ($i != $condition) {
                        return false;
                    }
                } elseif (preg_match_all('/(?:^[0-9]+|(?<=,)[0-9]+)/', $condition, $matches)) {
                    return in_array($i, $matches[0]);
                } elseif (!array_key_exists($condition, $data)) {
                    return false;
                }
                continue;
            }
            list(, $key, $op, $expected) = $match;
            if (!(isset($data[$key]) || array_key_exists($key, $data))) {
                return false;
            }

            $val = $data[$key];

            if ($op === '=' && $expected && $expected[0] === '/') {
                return preg_match($expected, $val);
            }
            if ($op === '=' && $val != $expected) {
                return false;
            }
            if ($op === '!=' && $val == $expected) {
                return false;
            }
            if ($op === '>' && $val <= $expected) {
                return false;
            }
            if ($op === '<' && $val >= $expected) {
                return false;
            }
            if ($op === '<=' && $val > $expected) {
                return false;
            }
            if ($op === '>=' && $val < $expected) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if a particular path is set in an array
     *
     * @param string|array $data Data to check on
     * @param string|array $path A dot-separated string.
     * @return boolean true if path is found, false otherwise
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::check
     */
    public static function check($data, $path = null) {
        if (empty($path)) {
            return $data;
        }
        if (!is_array($path)) {
            $path = explode('.', $path);
        }

        foreach ($path as $i => $key) {
            if ((is_numeric($key) && (int)$key > 0) || $key === '0') {
                $key = (int)$key;
            }
            if ($i === count($path) - 1) {
                return (is_array($data) && array_key_exists($key, $data));
            }

            if (!is_array($data) || !array_key_exists($key, $data)) {
                return false;
            }
            $data =& $data[$key];
        }
        return true;
    }

    /**
     * Computes the difference between a Set and an array, two Sets, or two arrays
     *
     * @param mixed $val1 First value
     * @param mixed $val2 Second value
     * @return array Returns the key => value pairs that are not common in $val1 and $val2
     * The expression for this function is($val1 - $val2) + ($val2 - ($val1 - $val2))
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::diff
     */
    public static function diff($val1, $val2 = null) {
        if (empty($val1)) {
            return (array)$val2;
        }
        if (empty($val2)) {
            return (array)$val1;
        }
        $intersection = array_intersect_key($val1, $val2);
        while (($key = key($intersection)) !== null) {
            if ($val1[$key] == $val2[$key]) {
                unset($val1[$key]);
                unset($val2[$key]);
            }
            next($intersection);
        }

        return $val1 + $val2;
    }

    /**
     * Determines if one Set or array contains the exact keys and values of another.
     *
     * @param array $val1 First value
     * @param array $val2 Second value
     * @return boolean true if $val1 contains $val2, false otherwise
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::contains
     */
    public static function contains($val1, $val2 = null) {
        if (empty($val1) || empty($val2)) {
            return false;
        }

        foreach ($val2 as $key => $val) {
            if (is_numeric($key)) {
                self::contains($val, $val1);
            } else {
                if (!isset($val1[$key]) || $val1[$key] != $val) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Counts the dimensions of an array. If $all is set to false (which is the default) it will
     * only consider the dimension of the first element in the array.
     *
     * @param array $array Array to count dimensions on
     * @param boolean $all Set to true to count the dimension considering all elements in array
     * @param integer $count Start the dimension count at this number
     * @return integer The number of dimensions in $array
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::countDim
     */
    protected static function countDim($array = null, $all = false, $count = 0) {
        if ($all) {
            $depth = array($count);
            if (is_array($array) && reset($array) !== false) {
                foreach ($array as $value) {
                    $depth[] = self::countDim($value, true, $count + 1);
                }
            }
            $return = max($depth);
        } else {
            if (is_array(reset($array))) {
                $return = self::countDim(reset($array)) + 1;
            } else {
                $return = 1;
            }
        }
        return $return;
    }

    /**
     * Creates an associative array using a $path1 as the path to build its keys, and optionally
     * $path2 as path to get the values. If $path2 is not specified, all values will be initialized
     * to null (useful for self::merge). You can optionally group the values by what is obtained when
     * following the path specified in $groupPath.
     *
     * If you use "/@" or "/subarray/@" as $path1 with $groupPath then items in each group will be indexed from 0
     *
     * @param array|object $data Array or object from where to extract keys and values
     * @param string|array $path1 As an array, or as a dot-separated string.
     * @param string|array $path2 As an array, or as a dot-separated string.
     * @param string $groupPath As an array, or as a dot-separated string.
     * @return array Combined array
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::combine
     */
    public static function combine($data, $path1 = null, $path2 = null, $groupPath = null) {
        if (empty($data)) {
            return array();
        }

        $data = self::prepareDataArray($data);

        if (is_array($path1)) {
            $format = array_shift($path1);
            $keys = self::format($data, $format, $path1);
        } else {
            $keys = self::extract($data, $path1);
        }
        if (empty($keys)) {
            return array();
        }
        $vals = array();
        if (!empty($path2) && is_array($path2)) {
            $format = array_shift($path2);
            $vals = self::format($data, $format, $path2);
        } elseif (!empty($path2)) {
            $vals = self::extract($data, $path2);
        } else {
            $count = count($keys);
            for ($i = 0; $i < $count; $i++) {
                $vals[$i] = null;
            }
        }

        if ($groupPath) {
            $group = self::extract($data, $groupPath);
            $useIndexesAsKeys = substr($path1, -1) === '@';
            if (!empty($group)) {
                $c = count($keys);
                $out = array();
                for ($i = 0; $i < $c; $i++) {
                    if (!isset($group[$i])) {
                        $group[$i] = 0;
                    }
                    if (!isset($out[$group[$i]])) {
                        $out[$group[$i]] = array();
                    }
                    if ($useIndexesAsKeys) {
                        $out[$group[$i]][] = $vals[$i];
                    } else {
                        $out[$group[$i]][$keys[$i]] = $vals[$i];
                    }
                }
                return $out;
            }
        }
        if (empty($vals)) {
            return array();
        }
        return array_combine($keys, $vals);
    }

    /**
     * Collapses a multi-dimensional array into a single dimension, using a delimited array path for
     * each array element's key, i.e. array(array('Foo' => array('Bar' => 'Far'))) becomes
     * array('0.Foo.Bar' => 'Far').)
     *
     * @param array $data Array to flatten
     * @param string $separator String used to separate array key elements in a path, defaults to '.'
     * @return array
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::flatten
     */
    public static function flatten(array $data, $separator = '.') {
        $result = array();
        $stack = array();
        $path = null;

        reset($data);
        while (!empty($data)) {
            $key = key($data);
            $element = $data[$key];
            unset($data[$key]);

            if (is_array($element) && !empty($element)) {
                if (!empty($data)) {
                    $stack[] = array($data, $path);
                }
                $data = $element;
                reset($data);
                $path .= $key . $separator;
            } else {
                $result[$path . $key] = $element;
            }

            if (empty($data) && !empty($stack)) {
                list($data, $path) = array_pop($stack);
                reset($data);
            }
        }
        return $result;
    }

    /**
     * Flattens an array for sorting
     *
     * @param array $results
     * @param string $key
     * @return array
     */
    protected static function _flatten($results, $key = null) {
        $stack = array();
        foreach ($results as $k => $r) {
            $id = $k;
            if (!is_null($key)) {
                $id = $key;
            }
            if (is_array($r) && !empty($r)) {
                $stack = array_merge($stack, self::_flatten($r, $id));
            } else {
                $stack[] = array('id' => $id, 'value' => $r);
            }
        }
        return $stack;
    }

    /**
     * Sorts an array by any value, determined by a Set-compatible path
     *
     * @param array $data An array of data to sort
     * @param string $path A Set-compatible path to the array value
     * @param string $dir Direction of sorting - either ascending (ASC), or descending (DESC)
     * @return array Sorted array of data
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::sort
     */
    public static function sort($data, $path, $dir) {
        if (empty($data)) {
            return $data;
        }
        $data = self::prepareDataArray($data);
        $originalKeys = array_keys($data);
        $numeric = false;
        if (is_numeric(implode('', $originalKeys))) {
            $data = array_values($data);
            $numeric = true;
        }
        $result = self::_flatten(self::extract($data, $path));
        list($keys, $values) = array(self::extract($result, '{n}.id'), self::extract($result, '{n}.value'));

        $dir = strtolower($dir);
        if ($dir === 'asc') {
            $dir = SORT_ASC;
        } elseif ($dir === 'desc') {
            $dir = SORT_DESC;
        }
        array_multisort($values, $dir, $keys, $dir);
        $sorted = array();
        $keys = array_unique($keys);

        foreach ($keys as $k) {
            if ($numeric) {
                $sorted[] = $data[$k];
            } else {
                if (isset($originalKeys[$k])) {
                    $sorted[$originalKeys[$k]] = $data[$originalKeys[$k]];
                } else {
                    $sorted[$k] = $data[$k];
                }
            }
        }
        return $sorted;
    }

    /**
     * Allows the application of a callback method to elements of an
     * array extracted by a self::extract() compatible path.
     *
     * @param mixed $path Set-compatible path to the array value
     * @param array $data An array of data to extract from & then process with the $callback.
     * @param mixed $callback Callback method to be applied to extracted data.
     * See http://ca2.php.net/manual/en/language.pseudo-types.php#language.types.callback for examples
     * of callback formats.
     * @param array $options Options are:
     *                       - type : can be pass, map, or reduce. Map will handoff the given callback
     *                                to array_map, reduce will handoff to array_reduce, and pass will
     *                                use call_user_func_array().
     * @return mixed Result of the callback when applied to extracted data
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/set.html#Set::apply
     */
    public static function apply($path, $data, $callback, $options = array()) {
        $defaults = array('type' => 'pass');
        $options = array_merge($defaults, $options);
        $data = self::prepareDataArray($data);
        $extracted = self::extract($path, $data);

        if ($options['type'] === 'map') {
            return array_map($callback, $extracted);
        } elseif ($options['type'] === 'reduce') {
            return array_reduce($extracted, $callback);
        } elseif ($options['type'] === 'pass') {
            return call_user_func_array($callback, array($extracted));
        }
        return null;
    }

    /**
     * Takes in a flat array and returns a nested array
     *
     * @param mixed $data
     * @param string $idPath
     * @param string $parentPath
     * @param string $childrenKey
     * @return array of results, nested
     */
    public static function nest($data, $idPath = '/id', $parentPath = '/parent_id', $childrenKey = 'children') {
        if (!$data) {
            return $data;
        }

        $data = self::prepareDataArray($data);
        $return = $idMap = array();
        $ids = self::extract($data, $idPath);
        $idKeys = explode('/', trim($idPath, '/'));
        $parentKeys = explode('/', trim($parentPath, '/'));

        foreach ($data as $result) {
            $result[$childrenKey] = array();

            $id = self::get($result, $idKeys);
            $parentId = self::get($result, $parentKeys);

            if (isset($idMap[$id][$childrenKey])) {
                $idMap[$id] = array_merge($result, (array)$idMap[$id]);
            } else {
                $idMap[$id] = array_merge($result, array($childrenKey => array()));
            }
            if (!$parentId || !in_array($parentId, $ids, true)) {
                $return[] =& $idMap[$id];
            } else {
                $idMap[$parentId][$childrenKey][] =& $idMap[$id];
            }
        }
        return array_values($return);
    }

    /**
     * Return the value at the specified position
     *
     * @param array $input an array
     * @param string|array $path string or array of array keys
     * @param null $default
     * @return mixed the value at the specified position or null if it doesn't exist
     */
    public static function get($input, $path = null, $default = null) {
        if (empty($path)) {
            return $input;
        }
        if (is_string($path)) {
            if (strpos($path, '/') !== false) {
                $keys = explode('/', trim($path, '/'));
            } else {
                $keys = explode('.', trim($path, '.'));
            }
        } else {
            $keys = $path;
        }
        if (empty($input)) {
            return $default;
        }
        foreach ($keys as $key) {
            if (is_array($input) && isset($input[$key])) {
                $input =& $input[$key];
            } else {
                return $default;
            }
        }
        return $input;
    }

}
