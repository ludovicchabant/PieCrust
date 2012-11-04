<?php

namespace PieCrust\Repositories;

use Symfony\Component\Yaml\Yaml;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\Cache;
use PieCrust\Util\ArchiveHelper;
use PieCrust\Util\PathHelper;


/**
 * A repository hosted on BitBucket, where
 * each plugin is a Mercurial or Git 
 * repository itself.
 */
class BitBucketRepository implements IRepository
{
    protected $cache;
    protected $cacheTime;

    // IRepository members {{{

    public function initialize(IPieCrust $pieCrust)
    {
        if ($pieCrust->isCachingEnabled())
        {
            $this->cache = new Cache($pieCrust->getCacheDir() . 'bitbucket_requests/');
            $this->cacheTime = 60 * 60; // Cache requests for one hour.
        }
        else
        {
            $this->cache = null;
            $this->cacheTime = false;
        }
    }
    
    public function supportsSource($source)
    {
        return $this->getUserName($source) !== false;
    }

    public function getPlugins($source)
    {
        return $this->getRepositoryInfos($source, 'PieCrust-Plugin-', 'plugin_info.yml');
    }

    public function getThemes($source)
    {
        return $this->getRepositoryInfos($source, 'PieCrust-Theme-', 'theme_info.yml');
    }

    public function installPlugin($plugin, $context)
    {
        $destination = $context->getLocalPluginDir($plugin['name'], false);
        if (is_dir($destination))
            throw new PieCrustException("'{$plugin['name']}' is already installed.");
        $this->extractRepository($context, $destination, $plugin['username'], $plugin['slug']);
    }

    public function installTheme($theme, $context)
    {
        $destination = $context->getLocalThemeDir(false);
        if (is_dir($destination))
            PathHelper::deleteDirectoryContents($destination);
        $this->extractRepository($context, $destination, $theme['username'], $theme['slug']);
    }

    // }}}

    protected function getRequest($url)
    {
        if ($this->cache == null)
            return file_get_contents($url);

        $cacheUrl = preg_replace('/[^A-Za-z0-9_\-]/', '_', $url);
        $cacheTime = $this->cache->getCacheTime($cacheUrl, 'json');
        if ($cacheTime !== false and $cacheTime + $this->cacheTime >= time())
        {
            return $this->cache->read($cacheUrl, 'json');
        }

        $data = file_get_contents($url);
        $this->cache->write($cacheUrl, 'json', $data);
        return $data;
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

    protected function getUserInfoFromSource($source)
    {
        $userName = $this->getUserName($source);
        if (!$userName)
            throw new PieCrustException("The given source is not a valid BitBucket source: {$source}");

        $url = 'https://api.bitbucket.org/1.0/users/' . $userName;
        $response = $this->getRequest($url);
        $userInfo = json_decode($response);
        return $userInfo;
    }

    public function getRepositoryInfos($source, $prefix, $infoFilename)
    {
        $infos = array();
        $prefixRegex = '/^' . preg_quote($prefix, '/') . '/';
        $userInfo = $this->getUserInfoFromSource($source);
        foreach ($userInfo->repositories as $repo)
        {
            if (preg_match($prefixRegex, $repo->name))
            {
                // Build some default metadata.
                $defaultInfo = array(
                    'name' => substr($repo->name, strlen($prefix)),
                    'authors' => array($userInfo->user->username),
                    'description' => $repo->description,
                    'username' => $userInfo->user->username,
                    'slug' => $repo->slug,
                    'source' => rtrim($source, '/') . '/' . $repo->slug,
                    'resource_uri' => $repo->resource_uri
                );

                // Look for `plugin_info.yml` or `theme_info.yml`.
                $url = "https://api.bitbucket.org/1.0/repositories/{$userInfo->user->username}/{$repo->name}/src/default/";
                $response = $this->getRequest(strtolower($url));
                $srcInfo = json_decode($response);
                if (count(array_filter(
                        $srcInfo->files, 
                        function ($f) use ($infoFilename) { return $f->path == $infoFilename; }
                    )) > 0)
                {
                    // Found! Read it.
                    $infoUrl = "https://api.bitbucket.org/1.0/repositories/{$userInfo->user->username}/{$repo->name}/raw/default/{$infoFilename}";
                    $infoRaw = $this->getRequest(strtolower($infoUrl));
                    $infos[] = array_merge(
                        Yaml::parse($infoRaw),
                        $defaultInfo
                    );
                }
                else
                {
                    // Not found... use the default metadata.
                    $infos[] = $defaultInfo;
                }
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

        $globbed = glob($cacheDir . $userName . '-' . $repoSlug . '-*', GLOB_ONLYDIR | GLOB_MARK);
        if (count($globbed) != 1)
            throw new PieCrustException("Can't find extracted directory for downloaded archive!");
        $archiveDir = $globbed[0];
        if (is_dir($destination))
        {
            $log->debug("Cleaning destination: {$destination}");
            PathHelper::deleteDirectoryContents($destination);
        }
        if (!is_dir(dirname($destination)))
        {
            mkdir(dirname($destination));
        }
        $log->debug("Moving extracted files into: {$destination}");
        rename($archiveDir, $destination);

        $log->debug("Cleaning up...");
        unlink($tempZip);
    }
}

