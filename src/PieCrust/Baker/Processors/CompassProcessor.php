<?php

namespace PieCrust\Baker\Processors;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Baker\IBaker;


class CompassProcessor implements IProcessor
{
    protected $pieCrust;
    protected $logger;
    protected $baker;

    protected $initialized;
    protected $needsRunning;
    protected $needsRunningInTheme;

    protected $binDir;
    protected $configPath;
    protected $options;

    public function __construct()
    {
        $this->initialized = false;
    }

    public function getName()
    {
        return 'compass';
    }
    
    public function initialize(IPieCrust $pieCrust, $logger = null)
    {
        $this->pieCrust = $pieCrust;

        if ($logger == null)
            $logger = \Log::singleton('null', '', '');
        $this->logger = $logger;
    }

    public function getPriority()
    {
        return IProcessor::PRIORITY_DEFAULT;
    }

    public function onBakeStart(IBaker $baker)
    {
        $this->baker = $baker;
        $this->needsRunning = false;
        $this->needsRunningInTheme = false;
    }

    public function supportsExtension($extension)
    {
        // Initialize ourselves... if Compass is not specifically enabled,
        // don't support anything.
        $this->ensureInitialized();
        if (!$this->usingCompass)
            return false;

        return ($extension == 'scss' or $extension == 'sass');
    }

    public function isBypassingStructuredProcessing()
    {
        return true;
    }

    public function isDelegatingDependencyCheck()
    {
        return false;
    }

    public function getDependencies($path)
    {
        throw new PieCrustException("Compass processor should bypass structured processing.");
    }

    public function getOutputFilenames($filename)
    {
        throw new PieCrustException("Compass processor should bypass structured processing.");
    }

    public function process($inputPath, $outputDir)
    {
        // Nothing... Compass processes all files in the project, so we
        // run it when the bake is finished.
        $themeDir = $this->pieCrust->getThemeDir();
        if ($themeDir && strncmp($inputPath, $themeDir, count($themeDir)))
        {
            if (!$this->needsRunningInTheme)
                $this->logger->debug("Scheduling Compass execution in theme directory after the bake.");
            $this->needsRunningInTheme = true;
        }
        else
        {
            if (!$this->needsRunning)
                $this->logger->debug("Scheduling Compass execution after the bake.");
            $this->needsRunning = true;
        }
    }

    public function onBakeEnd()
    {
        $this->runCompass();
        $this->baker = null;
    }

    protected function runCompass()
    {
        // If no SCSS or SASS files were encountered during the bake,
        // there's no need to run Compass.
        $this->ensureInitialized();
        if (!$this->usingCompass)
            return;

        $runCompassIn = array();
        if ($this->needsRunning)
            $runCompassIn[] = $this->pieCrust->getRootDir();
        if ($this->needsRunningInTheme)
            $runCompassIn[] = $this->pieCrust->getThemeDir();
        if (count($runCompassIn) == 0)
            return;

        // Run Compass!
        foreach ($runCompassIn as $i => $dir)
        {
            $exePath = 'compass';
            if ($this->binDir != null)
                $exePath = $this->binDir . $exePath;

            $cssDir = $this->baker->getBakeDir() . 'stylesheets';

            $cmd = "{$exePath} compile --css-dir=\"{$cssDir}\" {$this->options}";
            $this->logger->debug("Running Compass in '{$dir}'");
            $this->logger->debug('$> '.$cmd);

            $cwd = getcwd();
            chdir($dir);
            shell_exec($cmd);
            chdir($cwd);
        }
    }

    protected function ensureInitialized()
    {
        if ($this->initialized)
            return;

        $config = $this->pieCrust->getConfig();
        if ($config->getValue('compass/use_compass') !== true)
        {
            // This processor is deactivated unless `use_compass` is
            // specifically set to `true`.
            $this->initialized = true;
            $this->usingCompass = false;
            return;
        }

        $this->logger->debug("Will use Compass for processing SCSS/SASS files.");

        // Get the compass binary location, if any.
        $this->binDir = $config->getValue('compass/bin_dir');
        if ($this->binDir != null)
            $this->binDir = rtrim($this->binDir, '/\\') . DIRECTORY_SEPARATOR;

        // Get the compass configuration location, if any.
        $autoGenConfig = $config->getValue('compass/auto_config');
        $this->configPath = $config->getValue('compass/config_path');
        if ($this->configPath == null)
            $this->configPath = $this->pieCrust->getRootDir() . 'config.rb';
        if (!$autoGenConfig && !is_file($this->configPath))
            throw new PieCrustException(
                "No such Compass configuration file '{$this->configPath}'. ".
                "Either specify an existing one with `compass/config_path`, ".
                "or set `compass/auto_config` to `true` to let PieCrust create it for you."
            );
        if ($autoGenConfig)
        {
            // Figure out where to put the compass configuration file.
            if ($this->pieCrust->isCachingEnabled())
                $this->configPath = $this->pieCrust->getCacheDir() . 'compass-config.rb';
            else
                $this->configPath = tempnam(sys_get_temp_dir(), 'compass-config');

            // Build the configuration file if it doesn't exist.
            if (!is_file($this->configPath))
                $this->autoGenConfig($this->configPath);
        }

        // Build the compass command line options.
        $this->options = '--config "' . $this->configPath . '"';

        $frameworks = $config->getValue('compass/frameworks');
        if (!$frameworks)
        {
            $frameworks = array();
        }
        elseif (!is_array($frameworks))
        {
            $frameworks = array($frameworks);
        }
        foreach ($frameworks as $f)
        {
            $this->options .= " --load {$p}";
        }

        $miscOptions = $config->getValue('compass/options');
        if ($miscOptions)
            $this->options .= ' ' . $miscOptions;

        $this->usingCompass = true;
        $this->runInTheme = false;
        $this->runInRoot = false;
        $this->initialized = true;
    }

    protected function autoGenConfig($configPath)
    {
        $this->logger->debug("Generating Compass config file at: {$configPath}");

        $configContents = '';
        $config = $this->pieCrust->getConfig();

        // Root HTTP url.
        $rootUrl = $config->getValue('site/root');
        if ($rootUrl)
            $configContents .= 'http_path = "' . addcslashes($rootUrl, '\\') . '"' . PHP_EOL;

        // CSS style.
        $style = $config->getValue('compass/style');
        if (!$style)
            $style = 'nested';
        $configContents .= 'output_style = :' . $style . PHP_EOL;

        // Cache location.
        if ($this->pieCrust->isCachingEnabled())
        {
            $cacheDir = $this->pieCrust->getCacheDir() . 'sass_cache';
            $escapedCacheDir = addcslashes($cacheDir, '\\');
            $configContents .= 'sass_options = { :cache_location => "' . $escapedCacheDir . '" }' . PHP_EOL;
        }
        else
        {
            $configContents .= 'sass_options = { :cache => false }' . PHP_EOL;
        }

        // Custom directories.
        $dirNames = array('sass', 'images', 'generated_images', 'javascripts', 'fonts');
        foreach ($dirNames as $dirName)
        {
            $dir = $config->getValue("compass/{$dirName}_dir");
            if ($dir)
            {
                $escapedDir = addcslashes($dir, '\\');
                $configContents .= $dirName . '_dir = "' . $escapedDir . '"' . PHP_EOL;
            }
        }

        // Done!
        file_put_contents($configPath, $configContents);
    }
}
