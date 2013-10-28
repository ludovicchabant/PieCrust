<?php

namespace PieCrust\IO;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class CompositeFileSystem extends FileSystem
{
    protected $fileSystems;

    public function getInnerFileSystems()
    {
        return $this->fileSystems;
    }

    public function __construct($fileSystems)
    {
        $this->fileSystems = $fileSystems;
    }

    public function initialize(IPieCrust $pieCrust)
    {
        foreach ($this->fileSystems as $fs)
        {
            $fs->initialize($pieCrust);
        }
    }

    public function getName()
    {
        return 'composite';
    }

    public function getPageFiles()
    {
        $pageInfos = array();
        foreach ($this->fileSystems as $fs)
        {
            $pageInfos = array_merge(
                $pageInfos,
                $fs->getPageFiles()
            );
        }
        return $pageInfos;
    }

    public function getPostFiles($blogKey)
    {
        $postInfos = array();
        foreach ($this->fileSystems as $fs)
        {
            $postInfos = array_merge(
                $postInfos,
                $fs->getPostFiles($blogKey)
            );
        }
        return $postInfos;
    }

    public function getPostPathInfo($blogKey, $captureGroups, $mode)
    {
        foreach ($this->fileSystems as $fs)
        {
            try
            {
                $pathInfo = $fs->getPostPathInfo($blogKey, $captureGroups, $mode);
                return $pathInfo;
            }
            catch (PieCrustException $e)
            {
                // Silently ignore failure, hoping the next file-system
                // will manage to get us something.
            }
        }
        throw new PieCrustException("Can't find valid path info in any child file-system.");
    }

    public function getPostPathFormat($blogKey)
    {
        throw new PieCrustException("This shouldn't be called on a composite file-system.");
    }
}

