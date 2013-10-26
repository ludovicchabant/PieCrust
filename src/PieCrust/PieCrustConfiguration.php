<?php

namespace PieCrust;

use \Exception;
use Symfony\Component\Yaml\Yaml;
use PieCrust\IO\Cache;
use PieCrust\Util\Configuration;
use PieCrust\Util\ServerHelper;
use PieCrust\Util\UriBuilder;


/**
 * The configuration for a PieCrust application.
 */
class PieCrustConfiguration extends Configuration
{
    protected $paths;
    protected $cache;
    protected $fixupCallbacks;
    
    /**
     * Gets the paths to the composite configuration file.
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Adds a callback to fixup configuration data before it is merged
     * into the composite configuration.
     */
    public function addFixup($fixupCallback)
    {
        $this->fixupCallbacks[] = $fixupCallback;
    }
    
    /**
     * Builds a new instance of PieCrustConfiguration.
     */
    public function __construct(array $paths = null, $cache = false)
    {
        $this->paths = $paths;
        $this->cache = $cache;
        $this->fixupCallbacks = array();
    }

    /**
     * Applies a configuration variant stored within the configuration itself.
     */
    public function applyVariant($path, $throwIfNotFound = true)
    {
        $variant = $this->getValue($path);
        if ($variant === null)
        {
            if ($throwIfNotFound)
                throw new PieCrustException("No such configuration variant found: {$path}");
            return false;
        }
        if (!is_array($variant))
        {
            throw new PieCrustException("Configuration variant '{$path}' is not an array. Check your configuration file.");
        }
        $this->merge($variant);
    }
    
    protected function loadConfig()
    {
        if ($this->paths)
        {
            // Cache a validated JSON version of the configuration for faster
            // boot-up time (this saves a couple milliseconds).
            // 
            // First, get the times of the different configuration files. There's
            // usually one or two of them (one for the site configuration, an optional
            // one for the current theme's configuration).
            $pathTimes = array();
            foreach ($this->paths as $path)
            {
                $pathTimes[] = @filemtime($path);
            }
            $validPathTimes = array_filter($pathTimes, function ($t) { return $t !== false; });
            if (count($validPathTimes) == 0)
            {
                throw new PieCrustException("No PieCrust configuration file is readable, or none exists: " . implode(', ', $this->paths));
            }

            // Then compute the cache key that we'll be using to further validate
            // the cache (if the keys don't match, it means something happened like
            // PieCrust was updated to a new version and the cache probably needs
            // to be re-generated).
            $cacheKey = md5(
                "version=" . PieCrustDefaults::VERSION .
                "&cache=" . PieCrustDefaults::CACHE_VERSION
            );

            // Compare the modification times of those configuration files with the
            // cached JSON version.
            $cache = $this->cache ? new Cache($this->cache) : null;
            if ($cache != null and $cache->isValid('config', 'json', $validPathTimes))
            {
                $configText = $cache->read('config', 'json');
                $this->config = json_decode($configText, true);

                // Check the cache key.
                if (isset($this->config['__cache_key']) &&
                    $this->config['__cache_key'] == $cacheKey)
                {
                    // If the site root URL was automatically defined, we need to re-compute
                    // it in case the website is being run from a different place.
                    $isAutoRoot = $this->getValue('site/is_auto_root');
                    if ($isAutoRoot === true or $isAutoRoot === null)
                    {
                        $this->config['site']['root'] = ServerHelper::getSiteRoot($_SERVER);
                    }

                    // Some things in the config change the PHP envrionment.
                    // Do that now before we do the early return.
                    $this->applyEnvironmentChanges();

                    return;
                }
            }

            // Either the cache doesn't exist, is out of date, or has an
            // incorrect cache key. Parse the original config file.
            $config = array();
            foreach ($this->paths as $i => $path)
            {
                try
                {
                    $curConfig = Yaml::parse(file_get_contents($path));
                    if ($curConfig != null) 
                    {
                        foreach ($this->fixupCallbacks as $fixup)
                        {
                            $fixup($i, $curConfig);
                        }
                        $config = self::mergeArrays($config, $curConfig);
                    }
                }
                catch (Exception $e)
                {
                    throw new PieCrustException("An error was found in the PieCrust configuration file: {$path}", 0, $e);
                }
            }

            // Call the final fixup callback.
            foreach ($this->fixupCallbacks as $fixup)
            {
                $fixup(count($this->paths), $config);
            }

            // Validate the configuration.
            try
            {
                $this->config = $this->validateConfig($config);
            }
            catch (Exception $e)
            {
                throw new PieCrustException("Error while validating PieCrust configuration.", 0, $e);
            }
            
            // Create a validation key and save a JSON version in the cache.
            $this->config['__cache_key'] = $cacheKey;
            $yamlMarkup = json_encode($this->config);
            if ($cache != null)
            {
                $cache->write('config', 'json', $yamlMarkup);
            }
        }
        else
        {
            // No path given. Just create a default configuration.
            $this->config = $this->validateConfig(array());
        }

        // Some things in the config change the PHP envrionment.
        $this->applyEnvironmentChanges();
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
        // Validate defaults.
        if (!$config)
        {
            $config = array();
        }
        if (!isset($config['site']))
        {
            $config['site'] = array();
        }
        $config['site'] = array_merge(
            array(
                'title' => 'Untitled PieCrust Website',
                'root' => null,
                'theme_root' => null,
                'default_format' => PieCrustDefaults::DEFAULT_FORMAT,
                'default_template_engine' => PieCrustDefaults::DEFAULT_TEMPLATE_ENGINE,
                'enable_gzip' => false,
                'pretty_urls' => false,
                'slugify' => 'transliterate|lowercase',
                'timezone' => false,
                'locale' => false,
                'posts_fs' => PieCrustDefaults::DEFAULT_POSTS_FS,
                'date_format' => PieCrustDefaults::DEFAULT_DATE_FORMAT,
                'blogs' => array(PieCrustDefaults::DEFAULT_BLOG_KEY),
                'plugins_sources' => array(PieCrustDefaults::DEFAULT_PLUGIN_SOURCE),
                'themes_sources' => array(PieCrustDefaults::DEFAULT_THEME_SOURCE),
                'auto_formats' => array(),
                'cache_time' => 28800,
                'display_errors' => true,
                'enable_debug_info' => true
            ),
            $config['site']);

        $siteConfig = &$config['site'];

        // Validate the site root URL, and remember if it was specified in the
        // source config.yml, because we won't be able to tell the difference from
        // the completely validated cache version.
        if ($siteConfig['root'] == null)
        {
            $siteConfig['root'] = ServerHelper::getSiteRoot($_SERVER);
            $siteConfig['is_auto_root'] = true;
        }
        else
        {
            $siteConfig['root'] = rtrim($siteConfig['root'], '/') . '/';
            $siteConfig['is_auto_root'] = false;
        }

        // Validate auto-format extensions: make sure the HTML extension is in
        // there.
        if (!is_array($siteConfig['auto_formats']))
        {
            throw new PieCrustException("The 'site/auto_formats' configuration setting must be an array.");
        }
        $siteConfig['auto_formats'] = array_filter(
            array_merge(
                array(
                    'html' => '', 
                    'md' => 'markdown', 
                    'textile' => 'textile'
                ),
                $siteConfig['auto_formats']
            ),
            function ($item) { return $item !== false; }
        );

        // Validate the plugins sources.
        if (!is_array($siteConfig['plugins_sources']))
        {
            $siteConfig['plugins_sources'] = array($siteConfig['plugins_sources']);
        }

        // Validate the themes sources.
        if (!is_array($siteConfig['themes_sources']))
        {
            $siteConfig['themes_sources'] = array($siteConfig['themes_sources']);
        }
        
        // Validate multi-blogs settings.
        if (in_array(PieCrustDefaults::DEFAULT_BLOG_KEY, $siteConfig['blogs']) and 
            count($siteConfig['blogs']) > 1)
        {
            throw new PieCrustException("'".PieCrustDefaults::DEFAULT_BLOG_KEY."' cannot be specified as a blog key for multi-blog configurations. Please pick custom keys.");
        }
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
            if (isset($siteConfig[$key]))
                $defaultValues[$key] = $siteConfig[$key];
        }
        foreach ($siteConfig['blogs'] as $blogKey)
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
                            'date_format' => $siteConfig['date_format']
                        ),
                        $config[$blogKey]);
        }

        // Validate the slugify mode and optional flags.
        $slugifySetting = preg_split("/[\\s\\|,]+/", $siteConfig['slugify']);
        $slugifyModes = array(
            'none' => UriBuilder::SLUGIFY_MODE_NONE,
            'transliterate' => UriBuilder::SLUGIFY_MODE_TRANSLITERATE,
            'encode' => UriBuilder::SLUGIFY_MODE_ENCODE,
            'dash' => UriBuilder::SLUGIFY_MODE_DASHES,
            'iconv' => UriBuilder::SLUGIFY_MODE_ICONV
        );
        $slugifyFlags = array(
            'lowercase' => UriBuilder::SLUGIFY_FLAG_LOWERCASE
        );
        $finalSlugify = 0;
        foreach ($slugifySetting as $i => $m)
        {
            if ($i == 0)
            {
                if (!isset($slugifyModes[$m]))
                    throw new PieCrustException("Unsupported slugify mode: {$m}");
                $finalSlugify |= $slugifyModes[$m];
            }
            else
            {
                if (!isset($slugifyFlags[$m]))
                    throw new PieCrustException("Unsupported slugify flag: {$m}");
                $finalSlugify |= $slugifyFlags[$m];
            }
        }
        // We always want to slugify the `.` (dot) character, at least for tags
        // and categories, because it would screw up how we figure out what
        // extension to use for output files.
        $finalSlugify |= UriBuilder::SLUGIFY_FLAG_DOT_TO_DASH;
        $siteConfig['slugify_flags'] = $finalSlugify;

        return $config;
    }

    private function applyEnvironmentChanges()
    {
        $siteConfig = &$this->config['site'];

        // Set the timezone if it's specified. Otherwise, check that we have
        // a valid timezone.
        if ($siteConfig['timezone'])
        {
            date_default_timezone_set($siteConfig['timezone']);
        }
        else
        {
            try
            {
                date_default_timezone_get();
            }
            catch (\ErrorException $ex)
            {
                throw new PieCrustException(
                    "Your PHP installation is incomplete: you need to specify the `date.timezone` setting in your `php.ini` file. You can alternatively specify a `site/timezone` setting in your site configuration.",
                    0,
                    $ex
                );
            }
        }

        // Set the locale if it's specified.
        if ($siteConfig['locale'])
        {
            setlocale(LC_ALL, $siteConfig['locale']);
        }
        else
        {
            $siteConfig['locale'] = setlocale(LC_ALL, '0');
        }
    }
}
