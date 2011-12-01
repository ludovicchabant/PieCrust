<?php

namespace PieCrust\Baker\Processors;

require_once 'PhamlP/sass/SassFile.php';
require_once 'PhamlP/sass/SassParser.php';

use \SassFile;
use \SassParser;
use \Exception;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\Configuration;


class SassProcessor extends SimpleFileProcessor
{
    protected $sassOptions;

    public function __construct()
    {
        parent::__construct('sass', array('sass', 'scss'), 'css');
    }
    
    public function initialize(IPieCrust $pieCrust)
    {
        parent::initialize($pieCrust);

        // User can specify the options for PhamlP's Sass parser
        // through the 'sass' configuration section.
        $sassOptions = $pieCrust->getConfig()->getValue('sass');

        // Let's add the default values.
        if ($sassOptions == null)
        {
            $sassOptions = array();
        }
        $sassOptions = Configuration::mergeArrays(
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
                    'http_path' => $pieCrust->getConfig()->getValueUnchecked('site/root')
                );
            }
        }
        $this->sassOptions = $sassOptions;
    }

    public function getDependencies($path)
    {
        $text = file_get_contents($path);

        // Find all '@import' statements in the file.
        $imports = array();
        if (!preg_match_all('/^\s*@import\s+"([^"]+)"\s*(,\s*"([^"]+)"\s*)*;/m', $text, $imports, PREG_PATTERN_ORDER))
            return null;

        $importFilenames = array();
        foreach ($imports[1] as $i) if ($i) $importFilenames[] = $i;
        foreach ($imports[3] as $i) if ($i) $importFilenames[] = $i;

        // Build a Sass parser for the given file (we won't use it to compile the file...
        // you'll see in a little bit).
        $options = $this->sassOptions;
        $options['syntax'] = pathinfo($path, PATHINFO_EXTENSION);
        $sass = new SassParser($this->sassOptions);
        $relativeDir = dirname($path);

        $dependencies = array();
        foreach ($importFilenames as $f)
        {
            // Don't look at external (http://something) or CSS imports, since they're
            // kept as '@import' statements in the compiled file.
            $extension = pathinfo($f, PATHINFO_EXTENSION);
            if ($extension == 'css')
                continue;
            if (preg_match('#^https?\://#', $f))
                continue;

            // Look for the imported file using the Sass library's own algorithm.
            try
            {
                $dependencies[] = SassFile::getFile($f, $sass, $relativeDir);
            }
            catch (Exception $e)
            {
                throw new PieCrustException("Can't find dependency '" . $f . "' for SASS file: " . $path, 0, $e);
            }
        }
        return $dependencies;
    }

    protected function doProcess($inputPath, $outputPath)
    {
        $sass = new SassParser($this->sassOptions);
        $css = $sass->toCss($inputPath);
        file_put_contents($outputPath, $css);
    }
}
