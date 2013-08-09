<?php

namespace PieCrust\IO;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class FileSystemFactory
{
    /**
     * Creates the appropriate implementation of `FileSystem` based
     * on the configuration of the website.
     */
    public static function create(IPieCrust $pieCrust)
    {
        $postsFs = $pieCrust->getConfig()->getValueUnchecked('site/posts_fs');
        $postsFs = array_map(
            function ($i) { return trim($i); },
            explode(',', $postsFs)
        );

        $fss = array();
        $fileSystems = $pieCrust->getPluginLoader()->getFileSystems();
        foreach ($fileSystems as $fs)
        {
            $i = array_search($fs->getName(), $postsFs);
            if ($i !== false)
            {
                $fss[] = $fs;
                unset($postsFs[$i]);
            }
        }
        if ($postsFs)
        {
            throw new PieCrustException("Unknown file-system(s): " . implode(', ', $postsFs));
        }

        $fssCount = count($fss);
        if ($fssCount == 1)
        {
            $pieCrust->getEnvironment()->getLog()->debug("Creating unique file-system.");
            $finalFs = $fss[0];
        }
        elseif ($fssCount > 1)
        {
            $pieCrust->getEnvironment()->getLog()->debug("Creating composite file-system.");
            $finalFs = new CompositeFileSystem($fss);
        }
        else
        {
            throw new PieCrustException("No file-systems have been created: " . implode(', ', $postsFs));
        }

        $finalFs->initialize($pieCrust);
        return $finalFs;
    }
}

