<?php

namespace PieCrust;

use \Exception;
use PieCrust\Environment\CachedEnvironment;
use PieCrust\Formatters\IFormatter;
use PieCrust\Page\Page;
use PieCrust\Plugins\PluginLoader;
use PieCrust\Util\PathHelper;
use PieCrust\Util\PieCrustHelper;


/**
 * The main PieCrust application class.
 *
 * This class contains the application's configuration and directory setup information,
 * and handles, among other things, routing and errors.
 */
class PieCrust implements IPieCrust
{
    protected $rootDir;
    /**
     * The root directory of the website.
     */
    public function getRootDir()
    {
        return $this->rootDir;
    }
    
    protected $cachingEnabled;
    /**
     * Gets whether caching is enabled.
     */
    public function isCachingEnabled()
    {
        return $this->cachingEnabled;
    }
    
    protected $debuggingEnabled;
    /**
     * Gets whether debugging is enabled.
     */
    public function isDebuggingEnabled()
    {
        return $this->debuggingEnabled;
    }

    protected $themeSite;
    /**
     * Gets whether this app is for a theme site.
     */
    public function isThemeSite()
    {
        return $this->themeSite;
    }
    
    protected $templatesDirs;
    /**
     * Gets the directories that contain templates and layouts ('/_content/templates' by default).
     */
    public function getTemplatesDirs()
    {
        if ($this->templatesDirs === null)
        {
            // Start with no template directories.
            $this->templatesDirs = array();
            
            // Add the custom template directories specified in the configuration.
            $additionalPaths = $this->getConfig()->getValue('site/templates_dirs');
            if ($additionalPaths)
            {
                $this->addTemplatesDir($additionalPaths);
            }

            // Add the default template directory if it exists.
            $default = $this->rootDir . PieCrustDefaults::CONTENT_TEMPLATES_DIR;
            if (is_dir($default))
                $this->templatesDirs[] = $default;

            // Add the theme's default template directory if it exists.
            if ($this->getThemeDir())
            {
                $themeDefault = $this->getThemeDir() . PieCrustDefaults::CONTENT_TEMPLATES_DIR;
                if (is_dir($themeDefault))
                    $this->templatesDirs[] = $themeDefault;
            }
        }
        return $this->templatesDirs;
    }
    
    /**
     * Sets the directories that contain templates and layouts. Directories can be
     * relative to the site's root directory.
     */
    public function setTemplatesDirs($dir)
    {
        $this->templatesDirs = array();
        $this->addTemplatesDir($dir);
    }
    
    /**
     * Adds a templates directory. It can be relative to the site's root directory.
     */
    public function addTemplatesDir($dir)
    {
        $this->getTemplatesDirs();  // Ensure defaults are created.
        
        if (!is_array($dir)) 
            $dir = array($dir);

        foreach ($dir as $d)
        {
            $absolute = PathHelper::getAbsolutePath($d, $this->getRootDir());
            if (is_dir($absolute) === false)
            {
                throw new PieCrustException("The specified templates directory doesn't exist: " . $absolute);
            }
            $this->templatesDirs[] = rtrim($absolute, '/\\') . '/';
        }
    }
    
    protected $pagesDir;
    /**
     * Gets the directory that contains the pages and their assets ('/_content/pages' by default).
     */
    public function getPagesDir()
    {
        if ($this->pagesDir === null)
        {
            $this->pagesDir = $this->rootDir . PieCrustDefaults::CONTENT_PAGES_DIR;
            if (!is_dir($this->pagesDir))
                $this->pagesDir = false;
        }
        return $this->pagesDir;
    }
    
    /**
     * Sets the directory that contains the pages and their assets.
     */
    public function setPagesDir($dir)
    {
        $this->pagesDir = rtrim($dir, '/\\') . '/';
        if (is_dir($this->pagesDir) === false)
        {
            throw new PieCrustException("The specified pages directory doesn't exist: " . $this->pagesDir);
        }
    }
    
    protected $postsDir;
    /**
     * Gets the directory that contains the posts and their assets ('/_content/posts' by default).
     */
    public function getPostsDir()
    {
        if ($this->postsDir === null)
        {
            $this->postsDir = $this->rootDir . PieCrustDefaults::CONTENT_POSTS_DIR;
            if (!is_dir($this->postsDir))
                $this->postsDir = false;
        }
        return $this->postsDir;
    }
    
    /**
     * Sets the directory that contains the posts and their assets.
     */
    public function setPostsDir($dir)
    {
        $this->postsDir = rtrim($dir, '/\\') . '/';
        if (is_dir($this->postsDir) === false)
        {
            throw new PieCrustException("The specified posts directory doesn't exist: " . $this->postsDir);
        }
    }

    protected $pluginsDirs;
    /**
     * Gets the directories that contain the user plugins.
     */
    public function getPluginsDirs()
    {
        if ($this->pluginsDirs === null)
        {
            // Add the default plugins directory if it exists.
            $this->pluginsDirs = array();
            $default = $this->rootDir . PieCrustDefaults::CONTENT_PLUGINS_DIR;
            if (is_dir($default))
                $this->pluginsDirs[] = $default;

            // Add custom plugin directories specified in the configuration.
            $additionalPaths = $this->getConfig()->getValue('site/plugins_dirs');
            if ($additionalPaths)
            {
                $this->addPluginsDir($additionalPaths);
            }
        }
        return $this->pluginsDirs;
    }
    
    /**
     * Sets the directories that contain the user plugins.
     */
    public function setPluginsDirs($dir)
    {
        $this->pluginsDirs = array();
        $this->addPluginsDir($dir);
    }

    /**
     * Adds a directory that contains some user plugins.
     */
    public function addPluginsDir($dir)
    {
        $this->getPluginsDirs();  // Ensure defaults are created.
        
        if (!is_array($dir)) 
            $dir = array($dir);

        foreach ($dir as $d)
        {
            $absolute = PathHelper::getAbsolutePath($d, $this->getRootDir());
            if (is_dir($absolute) === false)
            {
                throw new PieCrustException("The specified plugins directory doesn't exist: " . $absolute);
            }
            $this->pluginsDirs[] = rtrim($absolute, '/\\') . '/';
        }
    }

    protected $themeDir;
    /**
     * Gets the directory that contains the current theme, if any.
     */
    public function getThemeDir()
    {
        if ($this->themeDir === null)
        {
            $this->themeDir = $this->rootDir . PieCrustDefaults::CONTENT_THEME_DIR;
            if (!is_dir($this->themeDir))
                $this->themeDir = PieCrustDefaults::RES_DIR() . 'theme/';
        }
        return $this->themeDir;
    }

    /**
     * Sets the directory that contains the current theme, if any.
     */
    public function setThemeDir($dir)
    {
        $this->themeDir = rtrim($dir, '/\\') . '/';
        if (is_dir($this->themeDir) === false)
        {
            throw new PieCrustException("The specified theme directory doesn't exist: " . $this->themeDir);
        }
    }

    protected $cacheDir;
    /**
     * Gets the cache directory ('/_cache' by default).
     */
    public function getCacheDir()
    {
        if ($this->cacheDir === null)
        {
            if ($this->cachingEnabled)
                $this->setCacheDir($this->rootDir . PieCrustDefaults::CACHE_DIR);
            else
                $this->cacheDir = false;
        }
        return $this->cacheDir;
    }
    
    /**
     * Sets the cache directory ('/_cache' by default).
     */
    public function setCacheDir($dir)
    {
        $this->cacheDir = rtrim($dir, '/\\') . '/';
        if (is_writable($this->cacheDir) === false)
        {
            try
            {
                if (!is_dir($this->cacheDir))
                {
                    mkdir($dir, 0777, true);
                }
                else
                {
                    chmod($this->cacheDir, 0777);
                }
            }
            catch (Exception $e)
            {
                throw new PieCrustException("The cache directory must exist and be writable, and we can't create it or change the permissions ourselves: " . $this->cacheDir);
            }
        }
    }
    
    protected $pluginLoader;
    /**
     * Gets the plugin loader for this app.
     */
    public function getPluginLoader()
    {
        return $this->pluginLoader;
    }

    protected $config;
    /**
     * Gets the application's configuration.
     */
    public function getConfig()
    {
        $this->ensureConfig();
        return $this->config;
    }

    protected $environment;
    /**
     * Gets the applicaiton's execution environment.
     */
    public function getEnvironment()
    {
        return $this->environment;
    }
    
    /**
     * Creates a new PieCrust instance with the given base URL.
     */
    public function __construct(array $parameters = array())
    {
        $parameters = array_merge(
            array(
                'root' => null,
                'cache' => true,
                'debug' => false,
                'environment' => null,
                'theme_site' => false
            ),
            $parameters
        );

        if (!$parameters['root'])
            throw new PieCrustException("No root directory was specified.");

        $this->rootDir = rtrim($parameters['root'], '/\\') . '/';
        $this->debuggingEnabled = (bool)$parameters['debug'];
        $this->cachingEnabled = (bool)$parameters['cache'];
        $this->themeSite = (bool)$parameters['theme_site'];
        $this->pluginLoader = new PluginLoader($this);

        $this->environment = $parameters['environment'];
        if (!$this->environment)
            $this->environment = new CachedEnvironment();
        $this->environment->initialize($this);
    }
    
    /**
     * Ensures the configuration has been loaded.
     */
    protected function ensureConfig()
    {
        if ($this->config == null)
        {
            $configCache = $this->cachingEnabled ? $this->getCacheDir() : false;

            $configPaths = array();
            if ($this->isThemeSite())
            {
                $themeDir = false;
                $configPaths[] = $this->rootDir . PieCrustDefaults::THEME_CONFIG_PATH;
            }
            else
            {
                $themeDir = $this->getThemeDir();
                if ($themeDir !== false)
                    $configPaths[] = $themeDir . PieCrustDefaults::THEME_CONFIG_PATH;
                $configPaths[] = $this->rootDir . PieCrustDefaults::CONFIG_PATH;
            }

            $this->config = new PieCrustConfiguration($configPaths, $configCache);
            if ($themeDir !== false)
            {
                // We'll need to patch the templates directories to be relative
                // to the site's root, as opposed to the theme root.
                $relativeThemeDir = PathHelper::getRelativePath($this->rootDir, $themeDir);
                $this->config->addFixup(function ($i, &$c) use ($relativeThemeDir) {
                    if ($i == 0)
                    {
                        if (!isset($c['site']))
                            return;
                        if (!isset($c['site']['templates_dirs']))
                            return;
                        if (!is_array($c['site']['templates_dirs']))
                            $c['site']['templates_dirs'] = array($c['site']['templates_dirs']);
                        foreach ($c['site']['templates_dirs'] as &$dir)
                        {
                            $dir = $relativeThemeDir . $dir;
                        }
                    }
                });
            }
        }
    }
}
