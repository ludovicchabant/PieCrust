<?php

namespace PieCrust\IO;

use FilesystemIterator;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;


/**
 * A file-system that loads posts from an external directory.
 *
 * It's not strictly speaking only for Dropbox, but that's how
 * it's most likely to be used.
 */
class DropboxFileSystem extends FileSystem
{
    protected $config;

    public function getName()
    {
        return 'dropbox';
    }

    public function initialize(\PieCrust\IPieCrust $pieCrust)
    {
        parent::initialize($pieCrust);

        if (DIRECTORY_SEPARATOR == '\\')
            $homePath = getenv('USERPROFILE');
        else
            $homePath = getenv('HOME');
        $defaultConfig = array(
            'dir' => (
                $homePath . DIRECTORY_SEPARATOR . 
                'Dropbox' . DIRECTORY_SEPARATOR .
                'Documents'
            ),
            'posts' => '%slug%.%ext%'
        );

        $config = $pieCrust->getConfig()->getValue('dropbox');
        if (!$config)
            $config = array();
        $this->config = array_merge($defaultConfig, $config);

        if (substr($this->config['dir'], 0, 1) == '~')
        {
            $this->config['dir'] = $homePath . substr($this->config['dir'], 1);
        }
        $this->config['dir'] = rtrim($this->config['dir'], "/\\") . '/';
    }

    public function getPageFiles()
    {
        return array();
    }

    public function getPostFiles($blogKey)
    {
        $postsDir = $this->config['dir'];
        if (!$postsDir or !is_dir($postsDir))
            return array();

        $postsPattern = $this->getPostsPattern($blogKey);
        if (!$postsPattern)
            return array();

        $year = date('Y');
        $month = date('m');
        $day = date('d');

        $result = array();
        $pathsIterator = new FilesystemIterator($postsDir);
        foreach ($pathsIterator as $path)
        {
            if ($path->isDir())
                continue;

            $extension = pathinfo($path->getFilename(), PATHINFO_EXTENSION);
            if (!in_array($extension, $this->htmlExtensions))
                continue;
        
            $pattern = str_replace(
                array('%slug%', '%ext%'),
                array('(.*)', preg_quote($extension, '/')),
                $postsPattern
            );
            $pattern = '/^' . $pattern . '$/';
            $matches = array();
            $res = preg_match($pattern, $path->getFilename(), $matches);
            if (!$res)
                continue;
            
            $result[] = PostInfo::fromStrings(
                $year,
                $month,
                $day,
                $matches[1],
                $extension,
                $path->getPathname()
            );
        }
        return $result;
    }

    public function getPostPathFormat($blogKey)
    {
        $postsDir = $this->config['dir'];
        if (!$postsDir or !is_dir($postsDir))
            throw new PieCrustException("No directory specified for the Dropbox articles.");
        $postsPattern = $this->getPostsPattern($blogKey);
        if (!$postsPattern)
            throw new PieCrustException("No posts pattern have been defined for blog: {$blogKey}");
        $format = $postsDir . $postsPattern;
        return $format;
    }

    private function getPostsPattern($blogKey)
    {
        if (isset($this->config[$blogKey . '_posts']))
            return $this->config[$blogKey . '_posts'];
        if ($blogKey == PieCrustDefaults::DEFAULT_BLOG_KEY)
            return $this->config['posts'];
        return null;
    }
}

