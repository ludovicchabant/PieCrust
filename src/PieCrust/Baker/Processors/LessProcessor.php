<?php

namespace PieCrust\Baker\Processors;

use PieCrust\PieCrustException;


class LessProcessor extends SimpleFileProcessor
{
    public function __construct()
    {
        parent::__construct('less', 'less', 'css');
    }

    public function getDependencies($path)
    {
        $text = file_get_contents($path);
        
        // Find all '@import' statements.
        $imports = array();
        if (!preg_match_all('/^\s*@import\s+"([^"]+)"\s*;/m', $text, $imports))
            return null;

        $dependencies = array();
        $less = new \lessc($path);
        foreach ($imports[1] as $f)
        {
            // Imported CSS files are kepts as @import statements in the compiled
            // file so they don't count as bake dependencies.
            if (pathinfo($f, PATHINFO_EXTENSION) == 'css')
                continue;

            $import = $less->findImport($f);
            if ($import)
            {
                $dependencies[] = $import;
            }
            else
            {
                throw new PieCrustException("Can't find dependency '" . $f . "' for LESS file: " . $path);
            }
        }
        return $dependencies;
    }
    
    protected function doProcess($inputPath, $outputPath)
    {
        $less = new \lessc($inputPath);
        file_put_contents($outputPath, $less->parse());
    }
}

