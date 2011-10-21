<?php

namespace PieCrust\Baker\Processors;

require_once 'PhamlP/sass/SassParser.php';


class SassProcessor extends SimpleFileProcessor
{
    public function __construct()
    {
        parent::__construct('sass', array('sass', 'scss'), 'css');
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        $sassOptions = $this->pieCrust->getConfig('sass');
        if ($sassOptions == null) $sassOptions = array();
        $sass = new \SassParser($sassOptions);
        $css = $sass->toCss($inputPath);
        file_put_contents($outputPath, $css);
    }
}
