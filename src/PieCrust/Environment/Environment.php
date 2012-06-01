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

    protected $uriPrefix;
    protected $uriSuffix;
    /**
     * Gets the URI decorators for the current application.
     */
    public function getUriDecorators($reevaluate = false)
    {
        if ($this->uriPrefix == null or $this->uriSuffix == null or $reevaluate)
        {
            $pieCrust = $this->pieCrust;
            $isBaking = ($pieCrust->getConfig()->getValue('baker/is_baking') === true);
            $isPretty = ($pieCrust->getConfig()->getValueUnchecked('site/pretty_urls') === true);
            $uriPrefix = $pieCrust->getConfig()->getValueUnchecked('site/root') . (($isPretty or $isBaking) ? '' : '?/');
            $uriSuffix = '%extension%';

            // Preserve the debug flag if needed.
            if ($pieCrust->isDebuggingEnabled() && !$isBaking)
            {
                if ($isPretty)
                    $uriSuffix .= '?!debug';
                else if (strpos($uriPrefix, '?') === false)
                    $uriSuffix .= '?!debug';
                else
                    $uriSuffix .= '&!debug';
            }

            $this->uriPrefix = $uriPrefix;
            $this->uriSuffix = $uriSuffix;
        }
        return array($this->uriPrefix, $this->uriSuffix);
    }

    /**
     * Creates a new instance of Environment.
     */
    protected function __construct(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
    }
}

