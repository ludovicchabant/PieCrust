<?php

namespace PieCrust\Baker\Processors;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class SassProcessor extends SimpleFileProcessor
{
    protected $initialized;
    protected $usingCompass;

    protected $binDir;
    protected $sassOptions;

    public function __construct()
    {
        parent::__construct('sass', array('scss', 'sass'), 'css');

        $this->initialized = false;
    }

    public function supportsExtension($extension)
    {
        if (!parent::supportsExtension($extension))
            return false;

        $this->ensureInitialized();
        return !$this->usingCompass;
    }

    public function isDelegatingDependencyCheck()
    {
        return false;
    }

    public function getOutputFilenames($filename)
    {
        return pathinfo($filename, PATHINFO_FILENAME) . '.css';
    }

    protected function doProcess($inputPath, $outputPath)
    {
        $this->ensureInitialized();

        // Run either `scss` or `sass` depending on the extension
        // of the input file.
        $exePath = pathinfo($inputPath, PATHINFO_EXTENSION);
        if ($this->binDir != null)
            $exePath = $this->binDir . $exePath;

        $cmd = "{$exePath} {$this->sassOptions} --update \"{$inputPath}\":\"{$outputPath}\"";
        $this->logger->debug('$> '.$cmd);
        shell_exec($cmd);
    }
    
    protected function ensureInitialized()
    {
        if ($this->initialized)
            return;

        $config = $this->pieCrust->getConfig();
        if ($config->getValue('compass/use_compass') === true)
        {
            // Deactivate this processor, the user wants to use
            // Compass instead.
            $this->initialized = true;
            $this->usingCompass = true;
            return;
        }

        $this->binDir = $config->getValue('sass/bin_dir');
        if ($this->binDir != null)
            $this->binDir = rtrim($this->binDir, '/\\') . DIRECTORY_SEPARATOR;

        $style = $config->getValue('sass/style');
        if (!$style)
            $style = 'nested';

        $loadPaths = $config->getValue('sass/load_paths');
        if (!$loadPaths)
            $loadPaths = array();
        if (!is_array($loadPaths))
            $loadPaths = array($loadPaths);

        $this->sassOptions = '--style ' . $style;
        if ($this->pieCrust->isCachingEnabled())
            $this->sassOptions .= ' --cache-location "' . $this->pieCrust->getCacheDir() . '"';
        else
            $this->sassOptions .= ' --no-cache';
        foreach ($loadPaths as $p)
        {
            $this->sassOptions .= ' -I "' . $p . '"';
        }

        $miscOptions = $config->getValue('sass/options');
        if ($miscOptions)
            $this->sassOptions .= ' ' . $miscOptions . ' ';

        $this->usingCompass = false;
        $this->initialized = true;
    }
}
