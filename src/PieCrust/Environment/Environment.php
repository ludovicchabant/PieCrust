<?php

namespace PieCrust\Environment;

use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


/**
 * A class that stores information about the environment
 * a PieCrust app is running in.
 */
abstract class Environment
{
    protected $pieCrust;

    /**
     * Gets the environment's page repository.
     */
    public abstract function getPageRepository();

    /**
     * Gets the environment's link collector.
     */
    public abstract function getLinkCollector();

    /**
     * Gets the page infos.
     */
    public abstract function getPageInfos();

    /**
     * Gets the post infos.
     */
    public abstract function getPostInfos($blogKey);

    /**
     * Gets the pages.
     */
    public abstract function getPages();

    /**
     * Gets the posts.
     */
    public abstract function getPosts($blogKey);

    protected $lastRunInfo;
    /**
     * Gets the info about the last executed request.
     */
    public function getLastRunInfo()
    {
        return $this->lastRunInfo;
    }

    /**
     * Sets the info about the last executed request.
     */
    public function setLastRunInfo($runInfo)
    {
        $this->lastRunInfo = $runInfo;
    }

    protected $uriFormat;
    /**
     * Gets the URI format for the current application.
     */
    public function getUriFormat()
    {
        if ($this->uriFormat == null)
        {
            $pieCrust = $this->pieCrust;
            $isBaking = ($pieCrust->getConfig()->getValue('baker/is_baking') === true);
            $isPretty = ($pieCrust->getConfig()->getValueUnchecked('site/pretty_urls') === true);

            $this->uriFormat = '%root%';
            if (!$isPretty and !$isBaking)
                $this->uriFormat .= '?/';
            $this->uriFormat .= '%slug%';

            // Add either a trailing slash or the default `.html` extension
            // to URIs that don't have an extension already, depending on whether
            // we are using pretty-URLs or not.
            if ($isBaking)
            {
                if ($isPretty)
                    $this->uriFormat .= '%slash_if_no_ext%';
                else
                    $this->uriFormat .= '%html_if_no_ext%';
            }

            // Preserve the debug flag if needed.
            if ($pieCrust->isDebuggingEnabled() && !$isBaking)
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
     * Creates a new instance of Environment.
     */
    protected function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
}

