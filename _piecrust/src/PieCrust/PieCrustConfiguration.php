<?php

namespace PieCrust;

require_once 'sfYaml/lib/sfYamlParser.php';

use \Exception;
use \sfYamlParser;
use PieCrust\IO\Cache;
use PieCrust\Util\Configuration;
use PieCrust\Util\ServerHelper;


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
            $configTime = @filemtime($this->path);
            if ($configTime === false)
            {
                throw new PieCrustException("The PieCrust configuration file is not readable, or doesn't exist: " . $this->path);
            }
            $cache = $this->cache ? new Cache($this->cache) : null;
            if ($cache != null and $cache->isValid('config', 'json', $configTime))
            {
                $configText = $cache->read('config', 'json');
                $this->config = json_decode($configText, true);

                // If the site root URL was automatically defined, we need to re-compute
                // it in case the website is being run from a different place.
                $isAutoRoot = $this->getValue('site/is_auto_root');
                if ($isAutoRoot === true or $isAutoRoot === null)
                {
                    $this->config['site']['root'] = ServerHelper::getSiteRoot($_SERVER);
                }
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
                        'default_format' => PieCrustDefaults::DEFAULT_FORMAT,
                        'default_template_engine' => PieCrustDefaults::DEFAULT_TEMPLATE_ENGINE,
                        'enable_gzip' => false,
                        'pretty_urls' => false,
                        'posts_fs' => PieCrustDefaults::DEFAULT_POSTS_FS,
                        'date_format' => PieCrustDefaults::DEFAULT_DATE_FORMAT,
                        'blogs' => array(PieCrustDefaults::DEFAULT_BLOG_KEY),
                        'cache_time' => 28800,
                        'display_errors' => false
                    ),
                    $config['site']);
        
        // Validate the site root URL, and remember if it was specified in the
        // source config.yml, because we won't be able to tell the difference from
        // the completely validated cache version.
        if ($config['site']['root'] == null)
        {
            $config['site']['root'] = ServerHelper::getSiteRoot($_SERVER);
            $config['site']['is_auto_root'] = true;
        }
        else
        {
            $config['site']['root'] = rtrim($config['site']['root'], '/') . '/';
            $config['site']['is_auto_root'] = false;
        }
        
        // Validate multi-blogs settings.
        if (in_array(PieCrustDefaults::DEFAULT_BLOG_KEY, $config['site']['blogs']) and count($config['site']['blogs']) > 1)
            throw new PieCrustException("'".PieCrustDefaults::DEFAULT_BLOG_KEY."' cannot be specified as a blog key for multi-blog configurations. Please pick custom keys.");
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
            if ($blogKey != PieCrustDefaults::DEFAULT_BLOG_KEY)
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
