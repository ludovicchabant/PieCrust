<?php

namespace PieCrust\Repositories;

use Symfony\Component\Yaml\Yaml;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\ArchiveHelper;
use PieCrust\Util\PathHelper;


/**
 * A repository stored on the local file-system.
 */
class FileSystemRepository implements IRepository
{
    // IRepository members {{{

    public function initialize(IPieCrust $pieCrust)
    {
    }
    
    public function supportsSource($source)
    {
        return preg_match(
            ',^(([a-z]:)|~)?[/\\a-z0-9 \(\)\-_\.]+$,i',
            $source
        );
    }

    public function getPlugins($source)
    {
        return $this->getSubDirectoriesInfos($source, 'plugin_info.yml');
    }

    public function getThemes($source)
    {
        return $this->getSubDirectoriesInfos($source, 'theme_info.yml');
    }

    public function installPlugin($plugin, $context)
    {
        $destination = $context->getLocalPluginDir($plugin['name'], false);
        if (is_dir($destination))
            throw new PieCrustException("'{$plugin['name']}' is already installed.");
        $this->copySubDirectory($context, $destination, $plugin['dir']);
    }

    public function installTheme($theme, $context)
    {
        $destination = $context->getLocalThemeDir(false);
        $this->copySubDirectory($context, $destination, $theme['dir'], true);
    }

    // }}}
    
    protected function getSubDirectoriesInfos($rootDir, $infoFilename)
    {
        if ($rootDir[0] == '~')
        {
            if (DIRECTORY_SEPARATOR == '\\')
                $rootDir = $_SERVER['USERPROFILE'] . substr($rootDir, 1);
            else
                $rootDir = $_SERVER['HOME'] . substr($rootDir, 1);
        }

        $infos = array();
        $it = new \FilesystemIterator($rootDir);
        foreach ($it as $subDir)
        {
            if ($it->isDot() || !$it->isDir())
                continue;

            $defaultInfo = array(
                'name' => $subDir->getFilename(),
                'description' => '',
                'dir' => $subDir->getPathname(),
                'source' => $subDir->getPathname()
            );

            $infoPath = $subDir->getPathname() . DIRECTORY_SEPARATOR . $infoFilename;
            if (is_file($infoPath))
            {
                $infos[] = array_merge(
                    $defaultInfo,
                    Yaml::parse($infoPath)
                );
            }
            else
            {
                $infos[] = $defaultInfo;
            }
        }
        return $infos;
    }

    protected function copySubDirectory($context, $destination, $subDir, $cleanFirst = false)
    {
        $log = $context->getLog();

        if ($cleanFirst)
        {
            if (is_dir($destination))
            {
                $log->info("Deleting existing files...");
                PathHelper::deleteDirectoryContents($destination);
            }
        }

        $log->info("Copying files...");
        $log->debug("{$subDir} -> {$destination}");
        PathHelper::copyDirectory($subDir, $destination, ",\\.hg|\\.git|\\.svn,");
    }
}

