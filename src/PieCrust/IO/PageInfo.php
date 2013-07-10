<?php

namespace PieCrust\IO;

use PieCrust\Util\PathHelper;


class PageInfo
{
    public $path;
    public $relativePath;

    public function __construct($rootDir, $path)
    {
        $this->path = $path;
        $this->relativePath = PathHelper::getRelativePath($rootDir, $path);
    }
}

