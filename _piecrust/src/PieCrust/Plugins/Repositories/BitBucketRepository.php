<?php

namespace PieCrust\Plugins\Repositories;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\ArchiveHelper;

/**
 * A plugin repository hosted on BitBucket, where
 * each plugin is a Mercurial or Git repository itself.
 */
class BitBucketRepository implements IPluginRepository
{
    public function supportsSource($source)
    {
        return $this->getUserName($source) !== false;
    }

    public function getPlugins($source)
    {
        $userName = $this->getUserName($source);
        if (!$userName)
            throw new PieCrustException("The given source is not a valid BitBucket source: {$source}");

        $url = 'https://api.bitbucket.org/1.0/users/' . $userName;
        $response = file_get_contents($url);
        $userInfo = json_decode($response);

        $plugins = array();
        foreach ($userInfo->repositories as $repo)
        {
            if (substr($repo->name, 0, 9) == 'PieCrust-' and
                $repo->name != 'PieCrust-App')
            {
                $plugins[] = array(
                    'name' => substr($repo->name, 9),
                    'description' => $repo->description,
                    'username' => $userName,
                    'slug' => $repo->slug,
                    'source' => rtrim($source, '/') . '/' . $repo->slug,
                    'resource_uri' => $repo->resource_uri,
                    'repository_class' => __CLASS__
                );
            }
        }
        return $plugins;
    }

    public function installPlugin($plugin, $context)
    {
        $app = $context->getApp();
        $log = $context->getLog();

        $cacheDir = $app->getCacheDir();
        if (!$cacheDir)
            throw new PieCrustException("Can't download archive without a cache directory.");

        $url = "https://bitbucket.org/{$plugin['username']}/{$plugin['slug']}/get/default.zip";
        $log->info("Downloading archive...");
        $log->debug("Fetching '{$url}'...");
        $contents = file_get_contents($url);
        $tempZip = $cacheDir . $plugin['name'] . '.zip';
        file_put_contents($tempZip, $contents);

        $log->info("Extracting...");
        $log->debug("Unzipping into: {$cacheDir}");
        ArchiveHelper::unzip($tempZip, $cacheDir, $log);

        $destination = $context->getLocalPluginDir($plugin['name'], false);
        $log->debug("Copying archive contents into: {$destination}");
        $globbed = glob($cacheDir . $plugin['username'] . '*', GLOB_ONLYDIR | GLOB_MARK);
        $archiveDir = $globbed[0];
        rename($archiveDir, $destination);

        $log->debug("Cleaning up...");
        unlink($tempZip);

        $log->info("Plugin {$plugin['name']} is installed.");
    }

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
}

