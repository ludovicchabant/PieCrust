<?php

namespace PieCrust\Formatters;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class HamlFormatter implements IFormatter
{
    protected $pieCrust;
    protected $hamlExe;
    protected $hamlOptions;

    public function initialize(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->hamlExe = null;
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_DEFAULT;
    }

    public function isExclusive()
    {
        return true;
    }
    
    public function supportsFormat($format)
    {
        return preg_match('/haml/i', $format);
    }
    
    public function format($text)
    {
        $this->ensureLoaded();

        $cmd = "{$this->hamlExe} {$this->hamlOptions} --stdin";
        $descriptors = array(
            0 => array("pipe", "r"),  // STDIN
            1 => array("pipe", "w"),  // STDOUT
            2 => array("pipe", "w")   // STDERR
        );
        $pipes = array();
        $process = proc_open($cmd, $descriptors, $pipes);

        if ($process === false)
            throw new PieCrustException("Can't spawn HAML process: {$cmd}");

        fwrite($pipes[0], $text);
        fclose($pipes[0]);

        $formattedText = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode != 0)
            throw new PieCrustException("HAML returned exit code '{$exitCode}', reported errors: {$errors}");

        return $formattedText;
    }

    protected function ensureLoaded()
    {
        if ($this->hamlExe !== null)
            return;

        $this->hamlExe = $this->pieCrust->getConfig()->getValue('haml/bin');
        if (!$this->hamlExe)
            $this->hamlExe = 'haml';

        $this->hamlOptions = $this->pieCrust->getConfig()->getValue('haml/options');
        if (!$this->hamlOptions)
            $this->hamlOptions = '';
    }
}
