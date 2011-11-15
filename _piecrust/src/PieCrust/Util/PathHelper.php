<?php

namespace PieCrust\Util;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class PathHelper
{
    /**
     * Gets the relative file-system path from the app root.
     */
    public static function getRelativePath(IPieCrust $pieCrust, $path, $stripExtension = false)
    {
        $rootDir = $pieCrust->getRootDir();
        $relativePath = substr($path, strlen($rootDir));
        if ($stripExtension) $relativePath = preg_replace('/\.[a-zA-Z0-9]+$/', '', $relativePath);
        return $relativePath;
    }
    
    /**
     * Finds a named template in an app's template paths.
     */
    public static function getTemplatePath(IPieCrust $pieCrust, $templateName)
    {
        $templateName = self::validateTemplateName($templateName);
        foreach ($pieCrust->getTemplatesDirs() as $dir)
        {
            $path = $dir . '/' . $templateName;
            if (is_file($path))
            {
                return $path;
            }
        }
        throw new PieCrustException("Couldn't find template '" . $templateName . "' in: " . implode(', ', $pieCrust->getTemplateDirs()));
    }
    
    private static function validateTemplateName($name)
    {
        $name = preg_replace('#/{2,}#', '/', strtr($name, '\\', '/'));
        
        $parts = explode('/', $name);
        $level = 0;
        foreach ($parts as $part)
        {
            if ('..' === $part)
            {
                --$level;
            }
            elseif ('.' !== $part)
            {
                ++$level;
            }

            if ($level < 0)
            {
                throw new PieCrustException(sprintf('Looks like you try to load a template outside configured directories (%s).', $name));
            }
        }
        
        return $name;
    }
}
