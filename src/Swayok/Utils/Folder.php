<?php
/**
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
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Swayok\Utils;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Folder structure browser, lists folders and files.
 * Provides an Object interface for Common directory related tasks.
 *
 * @package       Cake.Utility
 */
class Folder
{
    
    /**
     * Default scheme for Folder::copy
     * Recursively merges subfolders with the same name
     *
     * @constant MERGE
     */
    public const MERGE = 'merge';
    
    /**
     * Overwrite scheme for Folder::copy
     * subfolders with the same name will be replaced
     *
     * @constant OVERWRITE
     */
    public const OVERWRITE = 'overwrite';
    
    /**
     * Skip scheme for Folder::copy
     * if a subfolder with the same name exists it will be skipped
     *
     * @constant SKIP
     */
    public const SKIP = 'skip';
    
    /**
     * Path to Folder.
     *
     * @var string
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::$path
     */
    public $path = null;
    
    /**
     * Sortedness. Whether or not list results
     * should be sorted by name.
     *
     * @var boolean
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::$sort
     */
    public $sort = false;
    
    /**
     * Mode to be used on create. Does nothing on windows platforms.
     *
     * @var integer
     * http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::$mode
     */
    public $mode = 0755;
    
    /**
     * Holds messages from last method.
     *
     * @var array
     */
    protected $_messages = [];
    
    /**
     * Holds errors from last method.
     *
     * @var array
     */
    protected $_errors = [];
    
    /**
     * Holds array of complete directory paths.
     *
     * @var array
     */
    protected $_directories;
    
    /**
     * Holds array of complete file paths.
     *
     * @var array
     */
    protected $_files;
    
    /**
     * @var null|File
     */
    static protected $lastLoadedDir = null;
    
    public static function load(string $path, bool $create = false, ?int $folderAccess = null): Folder
    {
        self::$lastLoadedDir = new Folder($path, $create, $folderAccess);
        return self::$lastLoadedDir;
    }
    
    public static function add(string $path, ?int $folderAccess = null): Folder
    {
        return static::load($path, true, $folderAccess);
    }
    
    public static function exist(string $path): bool
    {
        self::$lastLoadedDir = new Folder($path);
        return self::$lastLoadedDir->exists();
    }
    
    public static function remove($path = false): bool
    {
        return static::load($path)->delete();
    }
    
    /**
     * Constructor.
     *
     * @param string $path Path to folder
     * @param boolean $create Create folder if not found
     * @param int|null $folderAccess Mode (CHMOD) to apply to created folder, false to ignore
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder
     */
    public function __construct(string $path, bool $create = false, ?int $folderAccess = null)
    {
        if ($folderAccess === null) {
            $this->mode = $folderAccess;
        }
        
        if ($create === true && !file_exists($path)) {
            $this->create($path, $this->mode);
        }
        if (!static::isAbsolute($path)) {
            $path = realpath($path);
        }
        if (!empty($path)) {
            $this->cd($path);
        }
    }
    
    /**
     * Return current path.
     *
     * @return string|null Current path
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::pwd
     */
    public function pwd(): ?string
    {
        return $this->path;
    }
    
    /**
     * Change directory to $path.
     *
     * @param string $path Path to the directory to change to
     * @return string The new path. Returns false on failure
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::cd
     */
    public function cd(string $path): ?string
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $path = $this->realpath($path);
        if ($path && is_dir($path)) {
            $this->path = $path;
            return $path;
        }
        return null;
    }
    
    /**
     * Check if folder exists
     * @return bool
     */
    public function exists(): bool
    {
        return file_exists($this->path) && is_dir($this->path);
    }
    
    /**
     * Returns an array of the contents of the current directory.
     * The returned array holds two arrays: One of directories and one of files.
     *
     * @param boolean $sort Whether you want the results sorted, set this and the sort property
     *   to false to get unsorted results.
     * @param array|bool $exceptions Either an array or boolean true will not grab dot files
     * @param boolean $fullPath True returns the full path
     * @return array Contents of current directory as an array, an empty array on failure
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::read
     */
    public function read(bool $sort = true, $exceptions = false, bool $fullPath = false): array
    {
        $dirs = $files = [];
        
        if (!$this->pwd()) {
            return [$dirs, $files];
        }
        if (is_array($exceptions)) {
            $exceptions = array_flip($exceptions);
        }
        $skipHidden = isset($exceptions['.']) || $exceptions === true;
        
        try {
            $iterator = new \DirectoryIterator($this->path);
        } catch (\Exception $e) {
            return [$dirs, $files];
        }
        /** @var $item \DirectoryIterator */
        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }
            $name = $item->getFileName();
            if (($skipHidden && $name[0] === '.') || isset($exceptions[$name])) {
                continue;
            }
            if ($fullPath) {
                $name = $item->getPathName();
            }
            if ($item->isDir()) {
                $dirs[] = $name;
            } else {
                $files[] = $name;
            }
        }
        if ($sort || $this->sort) {
            sort($dirs);
            sort($files);
        }
        return [$dirs, $files];
    }
    
    /**
     * Returns an array of all matching files in current directory.
     *
     * @param string $regexpPattern Preg_match pattern (Defaults to: .*)
     * @param boolean $sort Whether results should be sorted.
     * @return array Files that match given pattern
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::find
     */
    public function find(string $regexpPattern = '.*', bool $sort = false): array
    {
        [, $files] = $this->read($sort);
        return array_values(preg_grep('/^' . $regexpPattern . '$/i', $files));
    }
    
    /**
     * Returns an array of all matching files in and below current directory.
     *
     * @param string $pattern Preg_match pattern (Defaults to: .*)
     * @param boolean $sort Whether results should be sorted.
     * @return array Files matching $pattern
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::findRecursive
     */
    public function findRecursive(string $pattern = '.*', bool $sort = false): array
    {
        if (!$this->pwd()) {
            return [];
        }
        $startsOn = $this->path;
        $out = $this->_findRecursive($pattern, $sort);
        $this->cd($startsOn);
        return $out;
    }
    
    /**
     * Private helper function for findRecursive.
     *
     * @param string $pattern Pattern to match against
     * @param boolean $sort Whether results should be sorted.
     * @return array Files matching pattern
     */
    protected function _findRecursive(string $pattern, bool $sort = false): array
    {
        [$dirs, $files] = $this->read($sort);
        $found = [];
        
        foreach ($files as $file) {
            if (preg_match('/^' . $pattern . '$/i', $file)) {
                $found[] = static::addPathElement($this->path, $file);
            }
        }
        $start = $this->path;
        
        foreach ($dirs as $dir) {
            $this->cd(static::addPathElement($start, $dir));
            $found = array_merge($found, $this->findRecursive($pattern, $sort));
        }
        return $found;
    }
    
    /**
     * Returns true if given $path is a Windows path.
     *
     * @param string $path Path to check
     * @return boolean true if windows path, false otherwise
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::isWindowsPath
     */
    public static function isWindowsPath(string $path): bool
    {
        return (preg_match('/^[A-Z]:\\\\/i', $path) || strpos($path, '\\\\') === 0);
    }
    
    /**
     * Returns true if given $path is an absolute path.
     *
     * @param string $path Path to check
     * @return boolean true if path is absolute.
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::isAbsolute
     */
    public static function isAbsolute(string $path): bool
    {
        return !empty($path) && ($path[0] === '/' || preg_match('/^[A-Z]:\\\\/i', $path) || strpos($path, '\\\\') === 0);
    }
    
    /**
     * Returns a correct set of slashes for given $path. (\\ for Windows paths and / for other paths.)
     *
     * @param string $path Path to check
     * @return string Set of slashes ("\\" or "/")
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::normalizePath
     */
    public static function normalizePath(string $path): string
    {
        return static::correctSlashFor($path);
    }
    
    /**
     * Returns a correct set of slashes for given $path. (\\ for Windows paths and / for other paths.)
     *
     * @param string $path Path to check
     * @return string Set of slashes ("\\" or "/")
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::correctSlashFor
     */
    public static function correctSlashFor(string $path): string
    {
        return (static::isWindowsPath($path)) ? '\\' : '/';
    }
    
    /**
     * Returns $path with added terminating slash (corrected for Windows or other OS).
     *
     * @param string $path Path to check
     * @return string Path with ending slash
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::slashTerm
     */
    public static function slashTerm(string $path): string
    {
        if (static::isSlashTerm($path)) {
            return $path;
        }
        return $path . static::correctSlashFor($path);
    }
    
    /**
     * Returns $path with $element added, with correct slash in-between.
     *
     * @param string $path Path
     * @param string $element Element to and at end of path
     * @return string Combined path
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::addPathElement
     */
    public static function addPathElement(string $path, string $element): string
    {
        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $element;
    }
    
    /**
     * Returns true if the File is in given path.
     *
     * @param string $path The path to check that the current pwd() resides with in.
     * @param boolean $reverse Reverse the search, check that pwd() resides within $path.
     * @return boolean
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::inPath
     */
    public function inPath(string $path = '', bool $reverse = false): bool
    {
        $dir = static::slashTerm($path);
        $current = static::slashTerm($this->pwd());
        
        if (!$reverse) {
            $return = preg_match('/^(.*)' . preg_quote($dir, '/') . '(.*)/', $current);
        } else {
            $return = preg_match('/^(.*)' . preg_quote($current, '/') . '(.*)/', $dir);
        }
        return (bool)$return;
    }
    
    /**
     * Change the mode on a directory structure recursively. This includes changing the mode on files as well.
     *
     * @param string $path The path to chmod
     * @param integer|null $folderAccess octal value 0755
     * @param boolean $recursive chmod recursively, set to false to only change the current directory.
     * @param array $exceptions array of files, directories to skip
     * @return boolean Returns TRUE on success, FALSE on failure
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::chmod
     */
    public function chmod(string $path, ?int $folderAccess = null, bool $recursive = true, array $exceptions = []): bool
    {
        if (!$folderAccess) {
            $folderAccess = $this->mode;
        }
        
        if ($recursive === false && is_dir($path)) {
            //@codingStandardsIgnoreStart
            if (@chmod($path, intval($folderAccess, 8))) {
                //@codingStandardsIgnoreEnd
                $this->_messages[] = sprintf('%s changed to %s', $path, $folderAccess);
                return true;
            }
            
            $this->_errors[] = sprintf('%s NOT changed to %s', $path, $folderAccess);
            return false;
        }
        
        if (is_dir($path)) {
            $paths = $this->tree($path);
            
            foreach ($paths as $type) {
                foreach ($type as $fullpath) {
                    $check = explode(DIRECTORY_SEPARATOR, $fullpath);
                    $count = count($check);
                    
                    if (in_array($check[$count - 1], $exceptions, true)) {
                        continue;
                    }
                    
                    //@codingStandardsIgnoreStart
                    if (@chmod($fullpath, intval($folderAccess, 8))) {
                        //@codingStandardsIgnoreEnd
                        $this->_messages[] = sprintf('%s changed to %s', $fullpath, $folderAccess);
                    } else {
                        $this->_errors[] = sprintf('%s NOT changed to %s', $fullpath, $folderAccess);
                    }
                }
            }
            
            if (empty($this->_errors)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Returns an array of nested directories and files in each directory
     *
     * @param string|null $path the directory path to build the tree from
     * @param array|boolean $exceptions Either an array of files/folder to exclude
     *   or boolean true to not grab dot files/folders
     * @param string|null $type either 'file' or 'dir'. null returns both files and directories
     * @return array of nested directories and files in each directory
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::tree
     */
    public function tree(string $path = null, $exceptions = false, ?string $type = null): array
    {
        if (!$path) {
            $path = $this->path;
        }
        $files = [];
        $directories = [$path];
        
        if (is_array($exceptions)) {
            $exceptions = array_flip($exceptions);
        }
        $skipHidden = false;
        if ($exceptions === true) {
            $skipHidden = true;
        } elseif (isset($exceptions['.'])) {
            $skipHidden = true;
            unset($exceptions['.']);
        }
        
        try {
            $directory = new RecursiveDirectoryIterator(
                $path,
                RecursiveDirectoryIterator::KEY_AS_PATHNAME | RecursiveDirectoryIterator::CURRENT_AS_SELF
            );
            $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);
        } catch (Exception $e) {
            if ($type === null) {
                return [[], []];
            }
            return [];
        }
        /** @var RecursiveDirectoryIterator $fsIterator */
        foreach ($iterator as $itemPath => $fsIterator) {
            if ($skipHidden) {
                $subPathName = $fsIterator->getSubPathname();
                if ($subPathName[0] === '.' || strpos($subPathName, DIRECTORY_SEPARATOR . '.') !== false) {
                    continue;
                }
            }
            $item = $fsIterator->current();
            if (!empty($exceptions) && isset($exceptions[$item->getFilename()])) {
                continue;
            }
            
            if ($item->isFile()) {
                $files[] = $itemPath;
            } elseif ($item->isDir() && !$item->isDot()) {
                $directories[] = $itemPath;
            }
        }
        if ($type === null) {
            return [$directories, $files];
        }
        if ($type === 'dir') {
            return $directories;
        }
        return $files;
    }
    
    /**
     * Create a directory structure recursively. Can be used to create
     * deep path structures like `/foo/bar/baz/shoe/horn`
     *
     * @param string $pathname The directory structure to create
     * @param integer|null $folderAccess octal value 0755
     * @return boolean Returns TRUE on success, FALSE on failure
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::create
     */
    public function create(string $pathname, ?int $folderAccess = null): bool
    {
        if (empty($pathname) || is_dir($pathname)) {
            return true;
        }
        
        if (!$folderAccess) {
            $folderAccess = $this->mode;
        }
        
        if (is_file($pathname)) {
            $this->_errors[] = sprintf('%s is a file', $pathname);
            return false;
        }
        $pathname = rtrim(preg_replace('%[/\\\]%', DIRECTORY_SEPARATOR, $pathname), DIRECTORY_SEPARATOR);
        $nextPathname = substr($pathname, 0, strrpos($pathname, DIRECTORY_SEPARATOR));
        
        if ($this->create($nextPathname, $folderAccess) && !file_exists($pathname)) {
            $old = umask(0);
            if (mkdir($pathname, $folderAccess)) {
                umask($old);
                $this->_messages[] = sprintf('%s created', $pathname);
                return true;
            }
            umask($old);
            $this->_errors[] = sprintf('%s NOT created', $pathname);
            return false;
        }
        return false;
    }
    
    /**
     * Returns the size in bytes of this Folder and its contents.
     *
     * @return integer size in bytes of current folder
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::dirsize
     */
    public function dirsize(): int
    {
        $size = 0;
        $directory = static::slashTerm($this->path);
        $stack = [$directory];
        $count = count($stack);
        for ($i = 0, $j = $count; $i < $j; ++$i) {
            if (is_file($stack[$i])) {
                $size += filesize($stack[$i]);
            } elseif (is_dir($stack[$i])) {
                $dir = dir($stack[$i]);
                if ($dir) {
                    while (false !== ($entry = $dir->read())) {
                        if ($entry === '.' || $entry === '..') {
                            continue;
                        }
                        $add = $stack[$i] . $entry;
                        
                        if (is_dir($stack[$i] . $entry)) {
                            $add = static::slashTerm($add);
                        }
                        $stack[] = $add;
                    }
                    $dir->close();
                }
            }
            $j = count($stack);
        }
        return $size;
    }
    
    /**
     * Recursively Remove directories if the system allows.
     *
     * @return boolean Success
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::delete
     */
    public function delete(): bool
    {
        $path = $this->pwd();
        if (!$path) {
            return false;
        }
        $path = self::slashTerm($path);
        if (is_dir($path)) {
            try {
                $directory = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::CURRENT_AS_SELF);
                $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);
            } catch (Exception $e) {
                return false;
            }
            /** @var RecursiveDirectoryIterator $item */
            foreach ($iterator as $item) {
                $filePath = $item->getPathname();
                if ($item->isFile() || $item->isLink()) {
                    //@codingStandardsIgnoreStart
                    if (@File::remove($filePath)) {
                        //@codingStandardsIgnoreEnd
                        $this->_messages[] = sprintf('%s removed', $filePath);
                    } else {
                        $this->_errors[] = sprintf('%s NOT removed', $filePath);
                    }
                } elseif ($item->isDir() && !$item->isDot()) {
                    //@codingStandardsIgnoreStart
                    if (@rmdir($filePath)) {
                        //@codingStandardsIgnoreEnd
                        $this->_messages[] = sprintf('%s removed', $filePath);
                    } else {
                        $this->_errors[] = sprintf('%s NOT removed', $filePath);
                        return false;
                    }
                }
            }
            
            $path = rtrim($path, DIRECTORY_SEPARATOR);
            //@codingStandardsIgnoreStart
            if (@rmdir($path)) {
                //@codingStandardsIgnoreEnd
                $this->_messages[] = sprintf('%s removed', $path);
            } else {
                $this->_errors[] = sprintf('%s NOT removed', $path);
                return false;
            }
        }
        return true;
    }
    
    /**
     * Recursive directory copy.
     *
     * ### Options
     *
     * - `to` The directory to copy to.
     * - `from` The directory to copy from, this will cause a cd() to occur, changing the results of pwd().
     * - `mode` The mode to copy the files/directories with.
     * - `skip` Files/directories to skip.
     * - `scheme` Folder::MERGE, Folder::OVERWRITE, Folder::SKIP
     *
     * @param array|string $options Either an array of options (see above) or a string of the destination directory.
     * @return boolean Success
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::copy
     */
    public function copy($options): bool
    {
        if (!$this->pwd()) {
            return false;
        }
        $to = null;
        if (is_string($options)) {
            $to = $options;
            $options = [];
        }
        $options = array_merge(['to' => $to, 'from' => $this->path, 'mode' => $this->mode, 'skip' => [], 'scheme' => static::MERGE], $options);
        
        $fromDir = $options['from'];
        $toDir = $options['to'];
        $mode = $options['mode'];
        
        if (!$this->cd($fromDir)) {
            $this->_errors[] = sprintf('%s not found', $fromDir);
            return false;
        }
        
        if (!is_dir($toDir)) {
            $this->create($toDir, $mode);
        }
        
        if (!is_writable($toDir)) {
            $this->_errors[] = sprintf('%s not writable', $toDir);
            return false;
        }
        
        $exceptions = array_merge(['.', '..', '.svn'], $options['skip']);
        $handle = @opendir($fromDir);
        if ($handle) {
            while (($item = readdir($handle)) !== false) {
                $to = static::addPathElement($toDir, $item);
                if (($options['scheme'] !== static::SKIP || !is_dir($to)) && !in_array($item, $exceptions, true)) {
                    $from = static::addPathElement($fromDir, $item);
                    if (is_file($from)) {
                        if (copy($from, $to)) {
                            chmod($to, intval($mode, 8));
                            touch($to, filemtime($from));
                            $this->_messages[] = sprintf('%s copied to %s', $from, $to);
                        } else {
                            $this->_errors[] = sprintf('%s NOT copied to %s', $from, $to);
                        }
                    }
                    
                    if ($options['scheme'] === static::OVERWRITE && is_dir($from) && file_exists($to)) {
                        $this->delete($to);
                    }
                    
                    if (is_dir($from) && !file_exists($to)) {
                        $old = umask(0);
                        if (mkdir($to, $mode)) {
                            umask($old);
                            $old = umask(0);
                            chmod($to, $mode);
                            umask($old);
                            $this->_messages[] = sprintf('%s created', $to);
                            $options = array_merge($options, ['to' => $to, 'from' => $from]);
                            $this->copy($options);
                        } else {
                            $this->_errors[] = sprintf('%s not created', $to);
                        }
                    } elseif ($options['scheme'] === static::MERGE && is_dir($from)) {
                        $options = array_merge($options, ['to' => $to, 'from' => $from]);
                        $this->copy($options);
                    }
                }
            }
            closedir($handle);
        } else {
            return false;
        }
        
        if (!empty($this->_errors)) {
            return false;
        }
        return true;
    }
    
    /**
     * Recursive directory move.
     *
     * ### Options
     *
     * - `to` The directory to copy to.
     * - `from` The directory to copy from, this will cause a cd() to occur, changing the results of pwd().
     * - `mode` The mode to copy the files/directories with.
     * - `skip` Files/directories to skip.
     * - `scheme` Folder::MERGE, Folder::OVERWRITE, Folder::SKIP
     *
     * @param array|string $options (to, from, chmod, skip, scheme)
     * @return boolean Success
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::move
     */
    public function move($options): bool
    {
        $to = null;
        if (is_string($options)) {
            $to = $options;
            $options = [];
        }
        $options = array_merge(
            ['to' => $to, 'from' => $this->path, 'mode' => $this->mode, 'skip' => []],
            $options
        );
        
        if ($this->copy($options) && static::remove($options['from'])) {
            return (bool)$this->cd($options['to']);
        }
        return false;
    }
    
    /**
     * get messages from latest method
     *
     * @param boolean $reset Reset message stack after reading
     * @return array
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::messages
     */
    public function messages(bool $reset = true): array
    {
        $messages = $this->_messages;
        if ($reset) {
            $this->_messages = [];
        }
        return $messages;
    }
    
    /**
     * get error from latest method
     *
     * @param boolean $reset Reset error stack after reading
     * @return array
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::errors
     */
    public function errors(bool $reset = true): array
    {
        $errors = $this->_errors;
        if ($reset) {
            $this->_errors = [];
        }
        return $errors;
    }
    
    /**
     * Get the real path (taking ".." and such into account)
     *
     * @param string $path Path to resolve
     * @return string The resolved path
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::realpath
     */
    public function realpath(string $path): ?string
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, trim($path));
        if (strpos($path, '..') === false) {
            if (!static::isAbsolute($path)) {
                $path = static::addPathElement($this->path, $path);
            }
            return $path;
        }
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $newparts = [];
        $newpath = '';
        if ($path[0] === DIRECTORY_SEPARATOR) {
            $newpath = DIRECTORY_SEPARATOR;
        }
        
        while (($part = array_shift($parts)) !== null) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                if (!empty($newparts)) {
                    array_pop($newparts);
                    continue;
                }
                return null;
            }
            $newparts[] = $part;
        }
        $newpath .= implode(DIRECTORY_SEPARATOR, $newparts);
        
        return static::slashTerm($newpath);
    }
    
    /**
     * Returns true if given $path ends in a slash (i.e. is slash-terminated).
     *
     * @param string $path Path to check
     * @return boolean true if path ends with slash, false otherwise
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#Folder::isSlashTerm
     */
    public static function isSlashTerm(string $path): bool
    {
        if (!$path) {
            return false;
        }
        $lastChar = $path[strlen($path) - 1];
        return $lastChar === '/' || $lastChar === '\\';
    }
    
}
