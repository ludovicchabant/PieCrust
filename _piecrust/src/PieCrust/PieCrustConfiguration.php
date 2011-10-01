<?php

namespace PieCrust;

use \Exception;
use PieCrust\IO\Cache;

require_once 'sfYaml/lib/sfYamlParser.php';


/**
 * The configuration for a PieCrust application.
 */
class PieCrustConfiguration implements \ArrayAccess, \Iterator
{
    protected $parameters;
    protected $config;
    
    protected $path;
    /**
     * Gets the path to the configuration file.
     */
    public function getPath()
    {
        return $this->path;
    }
    
    /**
     * Builds a new instance of PieCrustConfiguration.
     */
    public function __construct(array $parameters = array(), $path = null)
    {
        if ($parameters == null)
        {
            $parameters = array();
        }
        $parameters = array_merge(
            array(
                'url_base' => '/',
                'cache_dir' => null,
                'cache' => false
            ),
            $parameters
        );
        $this->parameters = $parameters;
        $this->path = $path;
    }
    
    /**
     * Sets the entire configuration.
     */
    public function set(array $config)
    {
        $this->config = $this->validateConfig($config);
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
     * Gets a configuration section.
     */
    public function getSection($section)
    {
        $this->ensureLoaded();
        if (isset($this->config[$section]))
            return $this->config[$section];
        return null;
    }
    
    /**
     * Gets a configuration section value. Return null if it
     * can't be found.
     */
    public function getSectionValue($section, $key)
    {
        $this->ensureLoaded();
        if (!isset($this->config[$section]))
        {
            return null;
        }
        if (!isset($this->config[$section][$key]))
        {
            return null;
        }
        return $this->config[$section][$key];
    }
    
    /**
     * Gets a configuration section value without checking
     * for existence or validity.
     */
    public function getSectionValueUnchecked($section, $key)
    {
        $this->ensureLoaded();
        return $this->config[$section][$key];
    }
    
    /**
     * Sets a configuration section value.
     */
    public function setSectionValue($section, $key, $value)
    {
        $this->ensureLoaded();
        if (!isset($this->config[$section]))
        {
            $this->config[$section] = array($key => $value);
        }
        else
        {
            $this->config[$section][$key] = $value;
        }
    }
    
    protected function ensureLoaded()
    {
        if ($this->config === null)
        {
            // Cache a validated JSON version of the configuration for faster
            // boot-up time (this saves a couple milliseconds).
            $cache = $this->parameters['cache'] ? new Cache($this->parameters['cache_dir']) : null;
            $configTime = filemtime($this->path);
            if ($cache != null and $cache->isValid('config', 'json', $configTime))
            {
                $configText = $cache->read('config', 'json');
                $this->config = json_decode($configText, true);
            }
            else
            {
                try
                {
                    $yamlParser = new \sfYamlParser();
                    $config = $yamlParser->parse(file_get_contents($this->path));
                    $this->config = $this->validateConfig($config);
                }
                catch (Exception $e)
                {
                    throw new PieCrustException('An error was found in the PieCrust configuration file: ' . $e->getMessage());
                }
                
                $yamlMarkup = json_encode($this->config);
                if ($cache != null) $cache->write('config', 'json', $yamlMarkup);
            }
        }
    }
    
    protected function validateConfig($config)
    {
        if (!isset($config['site']))
        {
            $config['site'] = array();
        }
        $config['site'] = array_merge(array(
                        'title' => 'PieCrust Untitled Website',
                        'root' => $this->parameters['url_base'],
                        'default_format' => PIECRUST_DEFAULT_FORMAT,
                        'default_template_engine' => PIECRUST_DEFAULT_TEMPLATE_ENGINE,
                        'enable_gzip' => false,
                        'pretty_urls' => false,
                        'posts_fs' => 'flat',
                        'date_format' => 'F j, Y',
                        'blogs' => array(PIECRUST_DEFAULT_BLOG_KEY),
                        'cache_time' => 28800
                    ),
                    $config['site']);
        if (in_array(PIECRUST_DEFAULT_BLOG_KEY, $config['site']['blogs']) and count($config['site']['blogs']) > 1)
            throw new PieCrustException("'".PIECRUST_DEFAULT_BLOG_KEY."' cannot be specified as a blog key for multi-blog configurations. Please pick custom keys.");
        
        // Add default values for the blogs configurations, or use values
        // defined at the site level for easy site-wide configuration of multiple blogs
        // and backwards compatibility.
        $defaultValues = array(
            'post_url' => '%year%/%month%/%day%/%slug%',
            'tag_url' => 'tag/%tag%',
            'category_url' => '%category%',
            'posts_per_page' => 5
        );
        foreach (array_keys($defaultValues) as $key)
        {
            if (isset($config['site'][$key]))
                $defaultValues[$key] = $config['site'][$key];
        }
        foreach ($config['site']['blogs'] as $blogKey)
        {
            $prefix = '';
            if ($blogKey != PIECRUST_DEFAULT_BLOG_KEY)
            {
                $prefix = $blogKey . '/';
            }
            if (!isset($config[$blogKey]))
            {
                $config[$blogKey] = array();
            }
            $config[$blogKey] = array_merge(array(
                            'post_url' => $prefix . $defaultValues['post_url'],
                            'tag_url' => $prefix . $defaultValues['tag_url'],
                            'category_url' => $prefix . $defaultValues['category_url'],
                            'posts_per_page' => $defaultValues['posts_per_page'],
                            'date_format' => $config['site']['date_format']
                        ),
                        $config[$blogKey]);
        }
        
        return $config;
    }
    
    // {{{ ArrayAccess members
    public function __isset($name)
    {
        $this->ensureLoaded();
        return isset($this->config[$name]);
    }
    
    public function __get($name)
    {
        $this->ensureLoaded();
        return $this->config[$name];
    }
    
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
}
