<?php

namespace PieCrust\Baker\Processors;

use PieCrust\PieCrustException;


class LessProcessor extends SimpleFileProcessor
{
    public function __construct()
    {
        parent::__construct('less', 'less', 'css');
    }

    public function isDelegatingDependencyCheck()
    {
        return false;
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        $less = new \lessc($inputPath);
        file_put_contents($outputPath, $less->parse());
    }
}

