<?php

namespace PeskyORM\Lib;

class StringUtils {

    /**
     * Replaces variable placeholders inside a $str with any given $data. Each key in the $data array
     * corresponds to a variable placeholder name in $str.
     * Example: `String::insert(':name is :age years old.', array('name' => 'Bob', '65'));`
     * Returns: Bob is 65 years old.
     *
     * Available $options are:
     *
     * - before: The character or string in front of the name of the variable placeholder (Defaults to `:`)
     * - after: The character or string after the name of the variable placeholder (Defaults to null)
     * - escape: The character or string used to escape the before character / string (Defaults to `\`)
     * - format: A regex to use for matching variable placeholders. Default is: `/(?<!\\)\:%s/`
     *   (Overwrites before, after, breaks escape / clean)
     * - clean: A boolean or array with instructions for String::cleanInsert
     *
     * @param string $str A string containing variable placeholders
     * @param array $data A key => val array where each key stands for a placeholder variable name
     *     to be replaced with val
     * @param array $options An array of options, see description above
     * @return string
     */
    public static function insert($str, $data, $options = array()) {
        $defaults = array(
            'before' => ':', 'after' => null, 'escape' => '\\', 'format' => null, 'clean' => false
        );
        $options += $defaults;
        $format = $options['format'];
        $data = (array)$data;

        $str = str_ireplace(urlencode($options['before']), $options['before'], $str, $replacesDone);
        $urlEncode = $replacesDone > 0;
        if (empty($data)) {
            return ($options['clean']) ? self::cleanInsert($str, $options) : $str;
        }

        if (!isset($format)) {
            $format = sprintf(
                '/(?<!%s)%s%%s%s/',
                preg_quote($options['escape'], '/'),
                str_replace('%', '%%', preg_quote($options['before'], '/')),
                str_replace('%', '%%', preg_quote($options['after'], '/'))
            );
        }

        /*if (strpos($str, '?') !== false && is_numeric(key($data))) {
            $offset = 0;
            while (($pos = strpos($str, '?', $offset)) !== false) {
                $val = array_shift($data);
                $offset = $pos + strlen($val);
                $str = substr_replace($str, $val, $pos, 1);
            }
            return ($options['clean']) ? String::cleanInsert($str, $options) : $str;
        }*/

        asort($data);

        $dataKeys = array_keys($data);
        $hashKeys = array_map('crc32', $dataKeys);
        $tempData = array_combine($dataKeys, $hashKeys);
        krsort($tempData);

        foreach ($tempData as $key => $hashVal) {
            $key = sprintf($format, preg_quote($key, '/'));
            $str = preg_replace($key, $hashVal, $str);
        }
        $dataReplacements = array_combine($hashKeys, array_values($data));
        foreach ($dataReplacements as $tmpHash => $tmpValue) {
            if (is_array($tmpValue) || is_object($tmpValue) || is_callable($tmpValue)) {
                $tmpValue = '';
            } else if (is_bool($tmpValue)) {
                $tmpValue = $tmpValue ? '1' : '0';
            }
            $str = str_replace($tmpHash, $tmpValue, $str);
        }

        if (!isset($options['format']) && isset($options['before'])) {
            $str = str_replace($options['escape'] . $options['before'], $options['before'], $str);
        }

        // restore url encoded not used symbols
        if ($urlEncode) {
            $parts = explode('?', $str, 2);
            if (count($parts) > 1) {
                $parts[1] = preg_replace('%(=[^&]*?)' . preg_quote($options['before'], '%') . '%is', '$1' . urlencode($options['before']), $parts[1]);
            }
            $str = implode('?', $parts);
        }
        return ($options['clean']) ? self::cleanInsert($str, $options) : $str;
    }

    /**
     * Cleans up a String::insert() formatted string with given $options depending on the 'clean' key in
     * $options. The default method used is text but html is also available. The goal of this function
     * is to replace all whitespace and unneeded markup around placeholders that did not get replaced
     * by String::insert().
     *
     * @param string $str
     * @param array $options
     * @return string
     * @see String::insert()
     */
    protected static function cleanInsert($str, $options) {
        $clean = $options['clean'];
        if (!$clean) {
            return $str;
        }
        if ($clean === true) {
            $clean = array('method' => 'text');
        }
        if (!is_array($clean)) {
            $clean = array('method' => $options['clean']);
        }
        switch ($clean['method']) {
            case 'html':
                $clean = array_merge(array(
                    'word' => '[\w,.]+',
                    'andText' => true,
                    'replacement' => '',
                ), $clean);
                $kleenex = sprintf(
                    '/[\s]*[a-z]+=(")(%s%s%s[\s]*)+\\1/i',
                    preg_quote($options['before'], '/'),
                    $clean['word'],
                    preg_quote($options['after'], '/')
                );
                $str = preg_replace($kleenex, $clean['replacement'], $str);
                if ($clean['andText']) {
                    $options['clean'] = array('method' => 'text');
                    $str = self::cleanInsert($str, $options);
                }
                break;
            case 'text':
                $clean = array_merge(array(
                    'word' => '[\w,.]+',
                    'gap' => '[\s]*(?:(?:and|or)[\s]*)?',
                    'replacement' => '',
                ), $clean);

                $kleenex = sprintf(
                    '/(%s%s%s%s|%s%s%s%s)/',
                    preg_quote($options['before'], '/'),
                    $clean['word'],
                    preg_quote($options['after'], '/'),
                    $clean['gap'],
                    $clean['gap'],
                    preg_quote($options['before'], '/'),
                    $clean['word'],
                    preg_quote($options['after'], '/')
                );
                $str = preg_replace($kleenex, $clean['replacement'], $str);
                break;
        }
        return $str;
    }

    /**
     * Get model name form controller name (not a controller's class name) or db table name
     * @param string $underscoredString
     * @return string
     */
    static public function modelize($underscoredString) {
        return self::classify(self::singularize($underscoredString));
    }

    /**
     * Converts underscored text to class name ('user_actions' -> 'UserActions')
     * @param string $underscoredString
     * @return string
     */
    static public function classify($underscoredString) {
        return str_replace(' ', '', ucwords(preg_replace('%[^a-z\d]+%is', ' ', $underscoredString)));
    }

    /**
     * Converts class name, camel-cased text or plain text to underscored name
     * ('userActions -> 'user_actions', 'UserActions' -> user_actions, 'User Actions' -> user_actions)
     * @param string $camelCasedWord
     * @return string
     */
    static public function underscore($camelCasedWord) {
        return strtolower(preg_replace(
            array('/\s+/',  '/(?<=\\w)([A-Z])/',    '/_+/'),
            array('_',      '_\\1',                 '_'),
            $camelCasedWord
        ));
    }

    protected static $_cache = array();
    /**
     * Cache inflected values, and return if already available
     *
     * @param string $type Inflection type
     * @param string $key Original value
     * @param string|bool $value Inflected value
     * @return string Inflected value, from cache
     */
    protected static function _cache($type, $key, $value = false) {
        $key = '_' . $key;
        $type = '_' . $type;
        if ($value !== false) {
            self::$_cache[$type][$key] = $value;
            return $value;
        }
        if (!isset(self::$_cache[$type][$key])) {
            return false;
        }
        return self::$_cache[$type][$key];
    }

    protected static $_plural = array(
        'rules' => array(
            '/(s)tatus$/i' => '\1\2tatuses',
            '/(quiz)$/i' => '\1zes',
            '/^(ox)$/i' => '\1\2en',
            '/([m|l])ouse$/i' => '\1ice',
            '/(matr|vert|ind)(ix|ex)$/i' => '\1ices',
            '/(x|ch|ss|sh)$/i' => '\1es',
            '/([^aeiouy]|qu)y$/i' => '\1ies',
            '/(hive)$/i' => '\1s',
            '/(?:([^f])fe|([lre])f)$/i' => '\1\2ves',
            '/sis$/i' => 'ses',
            '/([ti])um$/i' => '\1a',
            '/(p)erson$/i' => '\1eople',
            '/(m)an$/i' => '\1en',
            '/(c)hild$/i' => '\1hildren',
            '/(buffal|tomat)o$/i' => '\1\2oes',
            '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|vir)us$/i' => '\1i',
            '/us$/i' => 'uses',
            '/(alias)$/i' => '\1es',
            '/(ax|cris|test)is$/i' => '\1es',
            '/s$/' => 's',
            '/^$/' => '',
            '/$/' => 's',
        ),
        'uninflected' => array(
            '.*[nrlm]ese', '.*deer', '.*fish', '.*measles', '.*ois', '.*pox', '.*sheep', 'people'
        ),
        'irregular' => array(
            'atlas' => 'atlases',
            'beef' => 'beefs',
            'brother' => 'brothers',
            'cafe' => 'cafes',
            'child' => 'children',
            'cookie' => 'cookies',
            'corpus' => 'corpuses',
            'cow' => 'cows',
            'ganglion' => 'ganglions',
            'genie' => 'genies',
            'genus' => 'genera',
            'graffito' => 'graffiti',
            'hoof' => 'hoofs',
            'loaf' => 'loaves',
            'man' => 'men',
            'money' => 'monies',
            'mongoose' => 'mongooses',
            'move' => 'moves',
            'mythos' => 'mythoi',
            'niche' => 'niches',
            'numen' => 'numina',
            'occiput' => 'occiputs',
            'octopus' => 'octopuses',
            'opus' => 'opuses',
            'ox' => 'oxen',
            'penis' => 'penises',
            'person' => 'people',
            'sex' => 'sexes',
            'soliloquy' => 'soliloquies',
            'testis' => 'testes',
            'trilby' => 'trilbys',
            'turf' => 'turfs',
            'potato' => 'potatoes',
            'hero' => 'heroes',
            'tooth' => 'teeth',
            'goose' => 'geese',
            'foot' => 'feet'
        )
    );

    static protected $_singular = array(
        'rules' => array(
            '/(s)tatuses$/i' => '\1\2tatus',
            '/^(.*)(menu)s$/i' => '\1\2',
            '/(quiz)zes$/i' => '\\1',
            '/(matr)ices$/i' => '\1ix',
            '/(vert|ind)ices$/i' => '\1ex',
            '/^(ox)en/i' => '\1',
            '/(alias)(es)*$/i' => '\1',
            '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|viri?)i$/i' => '\1us',
            '/([ftw]ax)es/i' => '\1',
            '/(cris|ax|test)es$/i' => '\1is',
            '/(shoe|slave)s$/i' => '\1',
            '/(o)es$/i' => '\1',
            '/ouses$/' => 'ouse',
            '/([^a])uses$/' => '\1us',
            '/([m|l])ice$/i' => '\1ouse',
            '/(x|ch|ss|sh)es$/i' => '\1',
            '/(m)ovies$/i' => '\1\2ovie',
            '/(s)eries$/i' => '\1\2eries',
            '/([^aeiouy]|qu)ies$/i' => '\1y',
            '/([lre])ves$/i' => '\1f',
            '/([^fo])ves$/i' => '\1fe',
            '/(tive)s$/i' => '\1',
            '/(hive)s$/i' => '\1',
            '/(drive)s$/i' => '\1',
            '/(^analy)ses$/i' => '\1sis',
            '/(analy|diagno|^ba|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
            '/([ti])a$/i' => '\1um',
            '/(p)eople$/i' => '\1\2erson',
            '/(m)en$/i' => '\1an',
            '/(c)hildren$/i' => '\1\2hild',
            '/(n)ews$/i' => '\1\2ews',
            '/eaus$/' => 'eau',
            '/^(.*us)$/' => '\\1',
            '/s$/i' => ''
        ),
        'uninflected' => array(
            '.*[nrlm]ese', '.*deer', '.*fish', '.*measles', '.*ois', '.*pox', '.*sheep', '.*ss'
        ),
        'irregular' => array(
            'foes' => 'foe',
            'waves' => 'wave',
            'curves' => 'curve'
        )
    );

    protected static $_uninflected = array(
        'Amoyese', 'bison', 'Borghese', 'bream', 'breeches', 'britches', 'buffalo', 'cantus',
        'carp', 'chassis', 'clippers', 'cod', 'coitus', 'Congoese', 'contretemps', 'corps',
        'debris', 'diabetes', 'djinn', 'eland', 'elk', 'equipment', 'Faroese', 'flounder',
        'Foochowese', 'gallows', 'Genevese', 'Genoese', 'Gilbertese', 'graffiti',
        'headquarters', 'herpes', 'hijinks', 'Hottentotese', 'information', 'innings',
        'jackanapes', 'Kiplingese', 'Kongoese', 'Lucchese', 'mackerel', 'Maltese', '.*?media',
        'mews', 'moose', 'mumps', 'Nankingese', 'news', 'nexus', 'Niasese',
        'Pekingese', 'Piedmontese', 'pincers', 'Pistoiese', 'pliers', 'Portuguese',
        'proceedings', 'rabies', 'rice', 'rhinoceros', 'salmon', 'Sarawakese', 'scissors',
        'sea[- ]bass', 'series', 'Shavese', 'shears', 'siemens', 'species', 'swine', 'testes',
        'trousers', 'trout', 'tuna', 'Vermontese', 'Wenchowese', 'whiting', 'wildebeest',
        'Yengeese'
    );

    /**
     * Return $word in singular form.
     *
     * @param string $word - word in plural
     * @return string - word in singular
     */
    public static function singularize($word) {
        if (isset(self::$_cache['singularize'][$word])) {
            return self::$_cache['singularize'][$word];
        }

        if (!isset(self::$_singular['merged']['uninflected'])) {
            self::$_singular['merged']['uninflected'] = array_merge(
                self::$_singular['uninflected'],
                self::$_uninflected
            );
        }

        if (!isset(self::$_singular['merged']['irregular'])) {
            self::$_singular['merged']['irregular'] = array_merge(
                self::$_singular['irregular'],
                array_flip(self::$_plural['irregular'])
            );
        }

        if (!isset(self::$_singular['cacheUninflected']) || !isset(self::$_singular['cacheIrregular'])) {
            self::$_singular['cacheUninflected'] = '(?:' . implode('|', self::$_singular['merged']['uninflected']) . ')';
            self::$_singular['cacheIrregular'] = '(?:' . implode('|', array_keys(self::$_singular['merged']['irregular'])) . ')';
        }

        if (preg_match('/(.*)\\b(' . self::$_singular['cacheIrregular'] . ')$/i', $word, $regs)) {
            self::$_cache['singularize'][$word] = $regs[1] . substr($word, 0, 1) . substr(self::$_singular['merged']['irregular'][strtolower($regs[2])], 1);
            return self::$_cache['singularize'][$word];
        }

        if (preg_match('/^(' . self::$_singular['cacheUninflected'] . ')$/i', $word, $regs)) {
            self::$_cache['singularize'][$word] = $word;
            return $word;
        }

        foreach (self::$_singular['rules'] as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                self::$_cache['singularize'][$word] = preg_replace($rule, $replacement, $word);
                return self::$_cache['singularize'][$word];
            }
        }
        self::$_cache['singularize'][$word] = $word;
        return $word;
    }

}