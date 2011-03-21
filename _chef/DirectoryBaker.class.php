<?php


/**
 * A class responsible for baking non-PieCrust content.
 */
class DirectoryBaker
{
    /**
     * The default files to skip (Windows & MacOS system files, Git & Mercurial special files,
     * and anything starting with an underscore).
     */
    const DEFAULT_SKIP_PATTERN = '/(^_)|(\.DS_Store)|(Thumbs.db)|(\.git)|(\.hg)/';
    
    protected $bakeDir;
    protected $skipPattern;
    
    /**
     * Creates a new instance of DirectoryBaker.
     */
    public function __construct($bakeDir, $skipPattern = null)
    {
        $this->bakeDir = rtrim(realpath($bakeDir), '/\\') . DIRECTORY_SEPARATOR;
        $this->skipPattern = $skipPattern;
        if ($this->skipPattern == null)
        {
            $this->skipPattern = self::DEFAULT_SKIP_PATTERN;
        }
        
        if (!is_dir($this->bakeDir) or !is_writable($this->bakeDir))
        {
            throw new PieCrustException('The bake directory is not writable, or does not exist: ' . $this->bakeDir);
        }
    }
    
    /**
     * Bakes the given directory and all its files and sub-directories.
     */
    public function bake($rootDir)
    {
        $rootDir = rtrim(realpath($rootDir), '/\\') . DIRECTORY_SEPARATOR;
        $rootDirLength = strlen($rootDir);
        
        $this->bakeDirectory($rootDir, $rootDirLength, $rootDir, 0);
    }
    
    protected function bakeDirectory($rootDir, $rootDirLength, $currentDir, $level)
    {
        $it = new DirectoryIterator($currentDir);
        foreach ($it as $i)
        {
            if ($i->isDot())
            {
                continue;
            }
            if (preg_match($this->skipPattern, $i->getFilename()))
            {
                continue;
            }
            
            $relative = substr($i->getPathname(), $rootDirLength);
            $destination = $this->bakeDir . $relative;
            if ($i->isDir())
            {
                if (!is_dir($destination))
                {
                    @mkdir($destination, 0777, true);
                }
                $this->bakeDirectory($rootDir, $rootDirLength, $i->getPathname(), $level + 1);
            }
            else if ($i->isFile())
            {
                if (!is_file($destination) or $i->getMTime() >= filemtime($destination))
                {
                    $start = microtime(true);
                    @copy($i->getPathname(), $destination);
                    echo PieCrustBaker::formatTimed($start, $relative) . PHP_EOL;
                }
            }
        }
    }
}
