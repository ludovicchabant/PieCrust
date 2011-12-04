<?php

namespace PieCrust\Util;

use PieCrust\IPage;
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
     * Gets the relative file-system path from the pages or posts directories.
     */
    public static function getRelativePagePath(IPieCrust $pieCrust, $path, $pageType = IPage::TYPE_REGULAR, $stripExtension = false)
    {
        switch ($pageType)
        {
            case IPage::TYPE_REGULAR:
            case IPage::TYPE_CATEGORY:
            case IPage::TYPE_TAG:
                $basePath = $pieCrust->getPagesDir();
                break;
            case IPage::TYPE_POST:
                $basePath = $pieCrust->getPostsDir();
                break;
            default:
                throw new InvalidArgumentException("Unknown page type given: " . $pageType);
        }
        if (!$basePath)
            throw new PieCrustException("Can't get a relative page path if no pages or posts directory exsists in the website.");
        
        $relativePath = substr($path, strlen($basePath));
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
