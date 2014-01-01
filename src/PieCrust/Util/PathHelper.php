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
    public static function getAppRootDir($path, $isThemeSite = false)
    {
        $configName = $isThemeSite ?
            PieCrustDefaults::THEME_CONFIG_PATH :
            PieCrustDefaults::CONFIG_PATH;
        while (!is_file($path . DIRECTORY_SEPARATOR . $configName))
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

        if ($path[0] == '~')
        {
            $userDir = getenv('HOME'); // On MacOS/Linux
            if (!$userDir)
                $userDir = getenv('USERPROFILE'); // On Windows
            $path = $userDir . substr($path, 1);
        }
        if ($path[0] != '/' && $path[0] != '\\' && (strlen($path) < 2 || $path[1] != ':'))
        {
            $path = $rootPath . $path;
        }

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
     * Gets the relative file-system path from a given root.
     * The path _must_ be inside the root -- no '../..' will be added.
     */
    public static function getRelativePath($root, $path)
    {
        $len = strlen($root);
        if ($root[$len - 1] != '/' and $root[$len - 1] != '\\')
            $len = $len + 1;
        return substr($path, $len);
    }
    
    /**
     * Translate glob patterns to regex patterns.
     */
    public static function globToRegex($pattern)
    {
        if (substr($pattern, 0, 1) == "/" and
            substr($pattern, -1) == "/")
        {
            // Already a regex.
            return $pattern;
        }
        
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\\*', '[^\\/\\\\]*', $pattern);
        $pattern = str_replace('\\?', '[^\\/\\\\]', $pattern);
        return '/'.$pattern.'/';
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
        return false;
    }

    public static function getUserOrThemePath(IPieCrust $pieCrust, $relativePath, $themePath = false)
    {
        if (!is_array($relativePath))
            $relativePath = array($relativePath);

        $pagesDir = $pieCrust->getPagesDir();
        if ($pagesDir !== false)
        {
            foreach ($relativePath as $rp)
            {
                $path = $pagesDir . $rp;
                if (is_file($path))
                {
                    return $path;
                }
            }
        }

        if (!$themePath)
            $themePath = $relativePath;
        elseif (!is_array($themePath))
            $themePath = array($themePath);

        $themeDir = $pieCrust->getThemeDir();
        if ($themeDir !== false)
        {
            foreach ($themePath as $rp)
            {
                $path = $themeDir . '_content/pages/' . $rp;
                if (is_file($path))
                {
                    return $path;
                }
            }
        }

        return false;
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

    /**
     * Makes sure the given directory exists, and optionally makes
     * sure it's writable.
     *
     * Returns whether the directory was just created.
     */
    public static function ensureDirectory($dir, $writable = false)
    {
        if (!is_dir($dir))
        {
            if (!mkdir($dir, 0755, true))
                throw new PieCrustException("Can't create directory: {$dir}");
            return true;
        }
        else if ($writable && !is_writable($dir))
        {
            if (!chmod($dir, 0755))
                throw new PieCrustException("Can't make directory '{$dir}' writable.");
        }
        return false;
    }

    /**
     * Copies a directory recursively.
     */
    public static function copyDirectory($source, $destination, $skipPattern = null)
    { 
        self::copyDirectoryRecursive($source, $destination, $skipPattern, 0, '');
    }

    private static function copyDirectoryRecursive($source, $destination, $skipPattern, $relativeParent)
    {
        $files = new \FilesystemIterator($source);
        if (!is_dir($destination))
            mkdir($destination);
        foreach ($files as $file)
        {
            $relativePathname = $file->getFilename();
            if ($relativeParent != '')
                $relativePathname = $relativeParent . '/' . $file->getFilename();

            if ($skipPattern != null and preg_match($skipPattern, $relativePathname))
                continue;

            if ($file->isDir())
            {
                self::copyDirectoryRecursive(
                    $file->getPathname(), 
                    $destination . DIRECTORY_SEPARATOR . $file->getFilename(),
                    $skipPattern,
                    $relativePathname
                );
            }
            else
            {
                copy(
                    $file->getPathname(),
                    $destination . DIRECTORY_SEPARATOR . $file->getFilename()
                );
            }
        }
    }

    /**
     * Deletes the contents of a directory, but optionally skips files
     * matching a given pattern.
     */
    public static function deleteDirectoryContents($dir, $skipPattern = null)
    {
        self::deleteDirectoryContentsRecursive($dir, $skipPattern, 0, '');
    }
    
    private static function deleteDirectoryContentsRecursive($dir, $skipPattern, $level, $relativeParent)
    {
        $skippedFiles = false;
        $files = new \FilesystemIterator($dir);
        foreach ($files as $file)
        {
            $relativePathname = $file->getFilename();
            if ($relativeParent != '')
                $relativePathname = $relativeParent . '/' . $file->getFilename();

            if ($skipPattern != null and preg_match($skipPattern, $relativePathname))
            {
                $skippedFiles = true;
                continue;
            }
            
            if ($file->isDir())
            {
                $skippedFiles |= self::deleteDirectoryContentsRecursive(
                    $file->getPathname(), 
                    $skipPattern, 
                    $level + 1, 
                    $relativePathname
                );
            }
            else
            {
                if (!unlink($file->getPathname()))
                    throw new PieCrustException("Can't unlink file: ".$file);
            }
        }
        
        if ($level > 0 and !$skippedFiles and is_dir($dir))
        {
            if (!rmdir($dir))
                throw new PieCrustException("Can't rmdir directory: ".$dir);
        }
        return $skippedFiles;
    }
}
