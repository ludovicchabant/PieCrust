<?php

namespace PieCrust\Baker\Processors;

require_once 'PhamlP/sass/SassParser.php';

use \SassParser;
use PieCrust\PieCrust;


class SassProcessor extends SimpleFileProcessor
{
    protected $sassOptions;

    public function __construct()
    {
        parent::__construct('sass', array('sass', 'scss'), 'css');
    }
    
    public function initialize(PieCrust $pieCrust)
    {
        parent::initialize($pieCrust);

        // User can specify the options for PhamlP's Sass parser
        // through the 'sass' configuration section.
        $sassOptions = $pieCrust->getConfigValue('sass');

        // Let's add the default values.
        if ($sassOptions == null)
        {
            $sassOptions = array();
        }
        $sassOptions = array_merge(
            array(
                'cache' => $pieCrust->isCachingEnabled(),
                'cache_location' => $pieCrust->isCachingEnabled() ? $pieCrust->getCacheDir() . 'sass' : false, 
                'css_location' => $pieCrust->getRootDir() . 'css',
                'load_paths' => $pieCrust->getTemplatesDirs(),
                'no_default_extensions' => false,
                'extensions' => array()
            ),
            $sassOptions
        );
        if (!is_array($sassOptions['extensions']))
        {
            $sassOptions['extensions'] = array($sassOptions['extensions']);
        }

        // Add the default extensions (right now, just Compass).
        if (!$sassOptions['no_default_extensions'])
        {
            if (!isset($sassOptions['extensions']['compass']))
            {
                $sassOptions['extensions']['compass'] = array(
                    'project_path' => rtrim($pieCrust->getRootDir(), '/\\'),
                    'http_path' => $pieCrust->getConfigValueUnchecked('site', 'root')
                );
            }
        }
        $this->sassOptions = $sassOptions;
    }

    protected function doProcess($inputPath, $outputPath)
    {
        $sass = new SassParser($this->sassOptions);
        $css = $sass->toCss($inputPath);
        file_put_contents($outputPath, $css);
    }
}
