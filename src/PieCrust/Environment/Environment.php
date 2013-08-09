<?php

namespace PieCrust\Environment;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystemFactory;
use PieCrust\Runner\ExecutionContext;


/**
 * A class that stores information about the environment
 * a PieCrust app is running in.
 */
abstract class Environment
{
    protected $pieCrust;

    protected $logger;
    /**
     * Gets the logger, if any.
     */
    public function getLog()
    {
        return $this->logger;
    }

    /**
     * Gets the environment's page repository.
     */
    public abstract function getPageRepository();

    /**
     * Gets the environment's link collector.
     */
    public abstract function getLinkCollector();

    /**
     * Gets the pages.
     */
    public abstract function getPages();

    /**
     * Gets the posts.
     */
    public abstract function getPosts($blogKey);

    protected $fileSystem;
    /**
     * Gets the file system for the current app.
     */
    public function getFileSystem()
    {
        if ($this->fileSystem == null)
        {
            $this->fileSystem = FileSystemFactory::create($this->pieCrust);
        }
        return $this->fileSystem;
    }

    protected $executionContext;
    /**
     * Gets the info about the current executed request, if any.
     * This info will stay available until a new request is executed.
     */
    public function getExecutionContext($autoCreate = false)
    {
        if ($autoCreate && $this->executionContext == null)
            $this->executionContext = new ExecutionContext();
        return $this->executionContext;
    }

    protected $uriFormat;
    /**
     * Gets the URI format for the current application.
     */
    public function getUriFormat()
    {
        if ($this->uriFormat == null)
        {
            $isBaking = ($this->pieCrust->getConfig()->getValue('baker/is_baking') === true);
            $isPreviewing = ($this->pieCrust->getConfig()->getValue('server/is_hosting') === true);
            $isPretty = ($this->pieCrust->getConfig()->getValueUnchecked('site/pretty_urls') === true);

            $this->uriFormat = '%root%';
            if (!$isPretty and !$isBaking)
                $this->uriFormat .= '?/';
            $this->uriFormat .= '%slug%';

            // Add either a trailing slash or the default `.html` extension
            // to URIs that don't have an extension already, depending on whether
            // we are using pretty-URLs or not.
            if ($isBaking || $isPreviewing)
            {
                if ($isPretty)
                {
                    if ($this->pieCrust->getConfig()->getValue('site/trailing_slash') ||
                        $this->pieCrust->getConfig()->getValue('baker/trailing_slash'))
                        $this->uriFormat .= '%slash_if_no_ext%';
                }
                else
                {
                    $this->uriFormat .= '%html_if_no_ext%';
                }
            }

            // Preserve the debug flag if needed.
            if ($this->pieCrust->isDebuggingEnabled() && !$isBaking)
            {
                if ($isPretty)
                    $this->uriFormat .= '?!debug';
                else if (strpos($this->uriFormat, '?') === false)
                    $this->uriFormat .= '?!debug';
                else
                    $this->uriFormat .= '&!debug';
            }
        }
        return $this->uriFormat;
    }

    /**
     * Creates a new instance of `Environment`.
     */
    protected function __construct($logger = null)
    {
        if ($logger == null)
            $logger = \Log::singleton('null', '', '');
        $this->logger = $logger;

        $this->fileSystem = null;
    }

    /**
     * Initializes this environment for the given application.
     */
    public function initialize(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
}

