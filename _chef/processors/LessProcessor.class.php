<?php

require_once 'lessphp/lessc.inc.php';


class LessProcessor extends SimpleFileProcessor
{
    public function __construct()
    {
        parent::__construct('less', 'less', 'css');
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        lessc::ccompile($inputPath, $outputPath);
    }
}
