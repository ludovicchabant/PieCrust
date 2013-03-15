<?php

namespace PieCrust\Util;

use Symfony\Component\Yaml\Yaml;
use PieCrust\PieCrustException;


/**
 * Represents the tree of settings (key/value pairs) that make up the configurations
 * of websites, pages, templates, etc.
 */
class Configuration implements \ArrayAccess, \Iterator
{
    protected $config;
    
    /**
     * Constructor.
     */
    public function __construct(array $config = null, $validate = true)
    {
        if ($config != null)
        {
            $this->set($config, $validate);
        }
    }
    
    /**
     * Sets the entire configuration.
     */
    public function set(array $config, $validate = true)
    {
        if ($validate)
            $this->config = $this->validateConfig($config);
        else
            $this->config = $config;
    }
    
    /**
     * Gets the entire configuration.
     */
    public function get()
    {
        $this->ensureLoaded();
        return $this->config;
    }
    
    /**
     * Gets whether a value exsists.
     */
    public function hasValue($keyPath)
    {
        $this->ensureLoaded();
        $keyPathBits = explode('/', $keyPath);
        $current = $this->config;
        foreach ($keyPathBits as $bit)
        {
            if (!isset($current[$bit]))
                return false;
            $current = $current[$bit];
        }
        return true;
    }
    
    /**
     * Gets a configuration section value. Return null if it
     * can't be found.
     */
    public function getValue($keyPath)
    {
        $this->ensureLoaded();
        $keyPathBits = explode('/', $keyPath);
        $current = $this->config;
        foreach ($keyPathBits as $bit)
        {
            if (!isset($current[$bit]))
                return null;
            $current = $current[$bit];
        }
        return $current;
    }
    
    /**
     * Gets a configuration section value without checking
     * for existence or validity.
     */
    public function getValueUnchecked($keyPath)
    {
        $this->ensureLoaded();
        $keyPathBits = explode('/', $keyPath);
        $current = $this->config;
        foreach ($keyPathBits as $bit)
        {
            $current = $current[$bit];
        }
        return $current;
    }
    
    /**
     * Sets a configuration section value.
     */
    public function setValue($keyPath, $value)
    {
        $this->ensureLoaded();
        $value = $this->validateConfigValue($keyPath, $value);
        $keyPathBits = explode('/', $keyPath);
        $keyPathBitsCount = count($keyPathBits);
        $current = &$this->config;
        $index = 0;
        foreach ($keyPathBits as $bit)
        {
            if ($index == ($keyPathBitsCount - 1))
            {
                $current[$bit] = $value;
            }
            else
            {
                if (!isset($current[$bit]))
                {
                    $current[$bit] = array();
                }
                $current = &$current[$bit];
            }
            ++$index;
        }
    }
    
    /**
     * Appends a value to an array value, or transforms a value into an array value.
     */
    public function appendValue($keyPath, $value)
    {
        $this->ensureLoaded();
        $value = $this->validateConfigValue($keyPath, $value);
        $keyPathBits = explode('/', $keyPath);
        $keyPathBitsCount = count($keyPathBits);
        $current = &$this->config;
        $index = 0;
        foreach ($keyPathBits as $bit)
        {
            if ($index == ($keyPathBitsCount - 1))
            {
                if (isset($current[$bit]))
                {
                    if (is_array($current[$bit]))
                    {
                        $current[$bit][] = $value;
                    }
                    else
                    {
                        $current[$bit] = array($current[$bit], $value);
                    }
                }
                else
                {
                    $current[$bit] = array($value);
                }
            }
            else
            {
                if (!isset($current[$bit]))
                {
                    $current[$bit] = array();
                }
                $current = &$current[$bit];
            }
            ++$index;
        }
    }
    
    /**
     * Merges one or several config sections into the current config.
     */
    public function merge(array $config)
    {
        $this->ensureLoaded();
        $this->mergeSectionRecursive($this->config, $config, null);
    }
    
    /**
     * Ensures this configuration has been loaded.
     */
    protected function ensureLoaded()
    {
        if ($this->config === null)
        {
            $this->loadConfig();
        }
    }
    
    /**
     * Loads the configuration (default implementation).
     */
    protected function loadConfig()
    {
        $this->config = $this->validateConfig(array());
    }
    
    /**
     * Validates the configuration (default implementation).
     */
    protected function validateConfig(array $config)
    {
        return $config;
    }
    
    /**
     * Validates a single configuration value (default implementation).
     */
    protected function validateConfigValue($keyPath, $value)
    {
        return $value;
    }
    
    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        $this->ensureLoaded();
        return isset($this->config[$offset]);
    }
    
    public function offsetGet($offset) 
    {
        $this->ensureLoaded();
        return $this->config[$offset];
    }
    
    public function offsetSet($offset, $value)
    {
        $this->ensureLoaded();
        $this->config[$offset] = $value;
    }
    
    public function offsetUnset($offset)
    {
        $this->ensureLoaded();
        unset($this->config[$offset]);
    }
    // }}}
    
    // {{{ Iterator members
    public function rewind()
    {
        $this->ensureLoaded();
        return reset($this->config);
    }
  
    public function current()
    {
        $this->ensureLoaded();
        return current($this->config);
    }
  
    public function key()
    {
        $this->ensureLoaded();
        return key($this->config);
    }
  
    public function next()
    {
        $this->ensureLoaded();
        return next($this->config);
    }
  
    public function valid()
    {
        $this->ensureLoaded();
        return key($this->config) !== null;
    }
    // }}}
    
    private function mergeSectionRecursive(array &$localSection, array $incomingSection, $parentPath)
    {
        foreach ($incomingSection as $key => $value)
        {
            $keyPath = $key;
            if ($parentPath != null)
            {
                $keyPath = ($parentPath . '/' . $key);
            }
            
            if (isset($localSection[$key]))
            {
                if (is_array($value) and is_array($localSection[$key]))
                {
                    $this->mergeSectionRecursive($localSection[$key], $value, $keyPath);
                }
                else
                {
                    $localSection[$key] = $this->validateConfigValue($keyPath, $value);
                }
            }
            else
            {
                $localSection[$key] = $this->validateConfigValue($keyPath, $value);
            }
        }
    }
    
    /**
     * Parse the YAML header.
     */
    public static function parseHeader($text)
    {
        $yamlHeaderMatches = array();
        $hasYamlHeader = preg_match('/\A(---\s*\n)((.*\n)*?)^(---\s*\n)/m', $text, $yamlHeaderMatches);
        if ($hasYamlHeader == true)
        {
            $yamlHeader = substr($text, strlen($yamlHeaderMatches[1]), strlen($yamlHeaderMatches[2]));
            try
            {
                $config = Yaml::parse($yamlHeader);
                if ($config == null)
                    $config = array();
            }
            catch (\Exception $e)
            {
                throw new PieCrustException('An error occured while reading the YAML header.', 0, $e);
            }
            $offset = strlen($yamlHeaderMatches[0]);
        }
        else
        {
            $config = array();
            $offset = 0;
        }
        
        return new ConfigurationHeader($config, $offset);
    }
    
    /**
     * Merges configuration arrays.
     *
     * This function does something in between array_merge and array_merge_recursive.
     */
    public static function mergeArrays($first, $second /*, $third, ... */)
    {
        $argCount = func_num_args();
        $current = $first;
        for ($i = 1; $i < $argCount; ++$i)
        {
            $next = func_get_arg($i);
            $current = self::mergeArrayRecursive($current, $next);
        }
        return $current;
    }
    
    private static function mergeArrayRecursive(array $first, array $second)
    {
        foreach ($second as $key => $value)
        {
            if (is_int($key))
            {
                array_unshift($first, $value);
            }
            else // string key
            {
                if (isset($first[$key]))
                {
                    if (is_array($value) and is_array($first[$key]))
                    {
                        $first[$key] = self::mergeArrayRecursive($first[$key], $value);
                    }
                    else
                    {
                        $first[$key] = $value;
                    }
                }
                else
                {
                    $first[$key] = $value;
                }
            }
        }
        return $first;
    }
}
