<?php

namespace PieCrust;

require_once 'sfYaml/lib/sfYamlParser.php';

use \Exception;
use \sfYamlParser;
use PieCrust\IO\Cache;
use PieCrust\Util\Configuration;


/**
 * The configuration for a PieCrust application.
 */
class PieCrustConfiguration extends Configuration
{
    protected $path;
    protected $cache;
    
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
    public function __construct($path = null, $cache = false)
    {
        $this->path = $path;
        $this->cache = $cache;
    }
    
    protected function loadConfig()
    {
        if ($this->path)
        {
            // Cache a validated JSON version of the configuration for faster
            // boot-up time (this saves a couple milliseconds).
            $cache = $this->cache ? new Cache($this->cache) : null;
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
                    $yamlParser = new sfYamlParser();
                    $config = $yamlParser->parse(file_get_contents($this->path));
                    if ($config == null) $config = array();
                    $this->config = $this->validateConfig($config);
                }
                catch (Exception $e)
                {
                    throw new PieCrustException('An error was found in the PieCrust configuration file: ' . $e->getMessage(), 0, $e);
                }
                
                $yamlMarkup = json_encode($this->config);
                if ($cache != null)
                {
                    $cache->write('config', 'json', $yamlMarkup);
                }
            }
        }
        else
        {
            // No path given. Just create a default configuration.
            $this->config = $this->validateConfig(array());
        }
    }
    
    protected function validateConfig(array $config)
    {
        return self::getValidatedConfig($config);
    }
    
    protected function validateConfigValue($keyPath, $value)
    {
        if ($keyPath == 'site/root')
        {
            return rtrim($value, '/') . '/';
        }
        return $value;
    }
    
    /**
     * Returns a validated version of the given site configuration.
     *
     * This is exposed as a public static function for convenience (unit tests,
     * etc.)
     */
    public static function getValidatedConfig($config)
    {
        if (!$config)
        {
            $config = array();
        }
        if (!isset($config['site']))
        {
            $config['site'] = array();
        }
        $config['site'] = array_merge(array(
                        'title' => 'Untitled PieCrust Website',
                        'root' => null,
                        'default_format' => PieCrust::DEFAULT_FORMAT,
                        'default_template_engine' => PieCrust::DEFAULT_TEMPLATE_ENGINE,
                        'enable_gzip' => false,
                        'pretty_urls' => false,
                        'posts_fs' => PieCrust::DEFAULT_POSTS_FS,
                        'date_format' => PieCrust::DEFAULT_DATE_FORMAT,
                        'blogs' => array(PieCrust::DEFAULT_BLOG_KEY),
                        'cache_time' => 28800
                    ),
                    $config['site']);
        
        // Validate the site root URL.
        if ($config['site']['root'] == null)
        {
            if (isset($_SERVER['HTTP_HOST']))
            {
                $host = ((isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                $folder = rtrim(dirname($_SERVER['PHP_SELF']), '/') .'/';
                $config['site']['root'] = $host . $folder;
            }
            else
            {
                $config['site']['root'] = '/';
            }
        }
        else
        {
            $config['site']['root'] = rtrim($config['site']['root'], '/') . '/';
        }
        
        // Validate multi-blogs settings.
        if (in_array(PieCrust::DEFAULT_BLOG_KEY, $config['site']['blogs']) and count($config['site']['blogs']) > 1)
            throw new PieCrustException("'".PieCrust::DEFAULT_BLOG_KEY."' cannot be specified as a blog key for multi-blog configurations. Please pick custom keys.");
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
            if ($blogKey != PieCrust::DEFAULT_BLOG_KEY)
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
}
