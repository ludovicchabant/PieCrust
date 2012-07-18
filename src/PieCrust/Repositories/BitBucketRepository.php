<?php

namespace PieCrust\Repositories;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\ArchiveHelper;
use PieCrust\Util\PathHelper;


/**
 * A repository hosted on BitBucket, where
 * each plugin is a Mercurial or Git 
 * repository itself.
 */
class BitBucketRepository implements IRepository
{
    // IRepository members {{{
    
    public function supportsSource($source)
    {
        return $this->getUserName($source) !== false;
    }

    public function getPlugins($source)
    {
        return $this->getRepositoryInfos($source, 'PieCrust-Plugin-');
    }

    public function installPlugin($plugin, $context)
    {
        $destination = $context->getLocalPluginDir($plugin['name'], false);
        if (is_dir($destination))
            throw new PieCrustException("'{$plugin['name']}' is already installed.");
        $this->extractRepository($context, $destination, $plugin['username'], $plugin['slug']);
    }

    // }}}

    protected function getUserName($source)
    {
        $matches = array();
        if (!preg_match(
            ',^https?://(www\.)?bitbucket\.org/(?P<username>[a-z0-9]+)/?,i', 
            $source, 
            $matches
        ))
        {
            return false;
        }
        return $matches['username'];
    }

    protected function getUserInfoFromSource($source)
    {
        $userName = $this->getUserName($source);
        if (!$userName)
            throw new PieCrustException("The given source is not a valid BitBucket source: {$source}");

        $url = 'https://api.bitbucket.org/1.0/users/' . $userName;
        $response = file_get_contents($url);
        $userInfo = json_decode($response);
        return $userInfo;
    }

    public function getRepositoryInfos($source, $prefix)
    {
        $infos = array();
        $prefixRegex = '/^' . preg_quote($prefix, '/') . '/';
        $userInfo = $this->getUserInfoFromSource($source);
        foreach ($userInfo->repositories as $repo)
        {
            if (preg_match($prefixRegex, $repo->name))
            {
                $infos[] = array(
                    'name' => substr($repo->name, strlen($prefix)),
                    'description' => $repo->description,
                    'username' => $userInfo->user->username,
                    'slug' => $repo->slug,
                    'source' => rtrim($source, '/') . '/' . $repo->slug,
                    'resource_uri' => $repo->resource_uri
                );
            }
        }
        return $infos;
    }

    protected function extractRepository($context, $destination, $userName, $repoSlug, $rev = 'default')
    {
        $app = $context->getApp();
        $log = $context->getLog();

        $cacheDir = $app->getCacheDir();
        if (!$cacheDir)
        {
            // If the cache doesn't exist or the application is running
            // with caching disabled, we still need to create a cache directory
            // to download the archive somewhere.
            $cacheDir = $app->getRootDir() . PieCrustDefaults::CACHE_DIR;
            PathHelper::ensureDirectory($cacheDir, true);
        }

        $url = "https://bitbucket.org/{$userName}/{$repoSlug}/get/${rev}.zip";
        $log->info("Downloading archive...");
        $log->debug("Fetching '{$url}'...");
        $contents = file_get_contents($url);
        $tempZip = $cacheDir . $repoSlug . '.zip';
        file_put_contents($tempZip, $contents);

        $log->info("Extracting...");
        $log->debug("Unzipping into: {$cacheDir}");
        ArchiveHelper::unzip($tempZip, $cacheDir, $log);

        $log->debug("Copying archive contents into: {$destination}");
        $globbed = glob($cacheDir . $userName . '-' . $repoSlug . '-*', GLOB_ONLYDIR | GLOB_MARK);
        if (count($globbed) != 1)
            throw new PieCrustException("Can't find extracted directory for downloaded archive!");
        $archiveDir = $globbed[0];
        PathHelper::ensureDirectory($destination, true);
        rename($archiveDir, $destination);

        $log->debug("Cleaning up...");
        unlink($tempZip);
    }
}

