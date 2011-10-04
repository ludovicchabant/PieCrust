<?php

namespace PieCrust\Baker\Processors;

require_once 'LessPhp/lessc.inc.php';


class LessProcessor extends SimpleFileProcessor
{
    public function __construct()
    {
        parent::__construct('less', 'less', 'css');
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        $less = new \lessc($inputPath);
        file_put_contents($outputPath, $less->parse());
    }
}
