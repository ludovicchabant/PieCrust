<?php

namespace PieCrust\Baker\Processors;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class YUICompressorProcessor extends SimpleFileProcessor
{
    protected $exe;
    protected $options;

    public function __construct()
    {
        parent::__construct('YUICompressor', array('js', 'css'), array('js', 'css'));

        $this->exe = null;
        $this->options = null;
    }

    public function supportsExtension($extension)
    {
        if (!parent::supportsExtension($extension))
            return false;

        $this->ensureInitialized();
        return ($this->exe !== false);
    }

    protected function doProcess($inputPath, $outputPath)
    {
        $extension = pathinfo($inputPath, PATHINFO_EXTENSION);
        $cmd = "{$this->exe} {$this->options} -o \"{$outputPath}\" \"{$inputPath}\"";
        $this->logger->debug('$> '.$cmd);
        shell_exec($cmd);
    }

    protected function ensureInitialized()
    {
        if ($this->exe !== null)
            return;

        $this->exe = false;
        $this->options = false;

        $yuiSection = $this->pieCrust->getConfig()->getValue('yui/compressor');
        if ($yuiSection == null)
            return;
        if (!isset($yuiSection['jar']))
            return;

        $this->exe = 'java -jar "'.$yuiSection['jar'].'"';
        $this->options = '';
        if (isset($yuiSection['options']))
            $this->options = $yuiSection['options'];
        $this->logger->debug("Will use YUICompressor: {$this->exe}");
    }
}

