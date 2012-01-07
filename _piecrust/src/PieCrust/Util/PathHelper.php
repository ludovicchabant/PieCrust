<?php

namespace PieCrust\Util;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


class PathHelper
{
    /**
     * Given a path, figure out if that path is inside a PieCrust website by
     * looking for a `_content/config.yml` file somewhere up in the hierarchy.
     * Returns `null` if no website is found.
     */
    public static function getAppRootDir($path)
    {
        while (!is_file($path . DIRECTORY_SEPARATOR . '_content' . DIRECTORY_SEPARATOR . 'config.yml'))
        {
            $pathParent = rtrim(dirname($path), '/\\');
            if ($path == $pathParent)
            {
                return null;
            }
            $path = $pathParent;
        }
        return $path;
    }

    /**
     * Gets an absolute path, like `realpath`, but without resolving symbolic links.
     *
     * A root path can be specified, if the path must be made absolute relative
     * to a different directory than the current one. It must not end with a slash
     * or backslash.
     */
    public static function getAbsolutePath($path, $rootPath = null)
    {
        if ($rootPath == null)
            $rootPath = getcwd();

        if ($rootPath[strlen($rootPath) - 1] != '/' &&
            $rootPath[strlen($rootPath) - 1] != '\\')
            $rootPath .= DIRECTORY_SEPARATOR;

        if ($path[0] != '/' && $path[0] != '\\' && (strlen($path) < 2 || $path[1] != ':'))
            $path = $rootPath . $path;

        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $absolutes = array();
        foreach ($parts as $part)
        {
            if ('.' == $part)
                continue;
            if ('..' == $part)
                array_pop($absolutes);
            else
                $absolutes[] = $part;
        }
        return implode('/', $absolutes);
    }

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

    /**
     * Gets the plugins directories for a given plugin type.
     */
    public static function getPluginsDirs(IPieCrust $pieCrust, $pluginType, $defaultDir = null)
    {
        $result = array();

        // Add the default/built-in directory, if any.
        if ($defaultDir)
            $result[] = PieCrustDefaults::APP_DIR . '/' . $defaultDir;

        // Look in the app's plugins directories, and for each, look at the plugins
        // inside, looking for the correct class type.
        $locations = $pieCrust->getPluginsDirs();
        foreach ($locations as $loc)
        {
            $dirs = new \FilesystemIterator($loc);
            foreach ($dirs as $d)
            {
                if (!$d->isDir())
                    continue;

                $pluginDir = $d->getPathname() . '/src/' . $pluginType;
                if (is_dir($pluginDir))
                {
                    $result[] = $pluginDir;
                }
            }
        }

        return $result;
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
