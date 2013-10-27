<?php

namespace PieCrust\Baker;

use PieCrust\IPage;
use PieCrust\IPieCrust;


/**
 * A simple implementation of the `IBakerAssistant` interface.
 */
class BakerAssistant implements IBakerAssistant
{
    protected $name;
    protected $pieCrust;
    protected $logger;

    /**
     * Creates a new instance of `BakerAssistant`.
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    // IBakerAssistant Members {{{
    /**
     * Gets the name of the assistant.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Initializes the assistant.
     */
    public function initialize(IPieCrust $pieCrust, $logger = null)
    {
        $this->pieCrust = $pieCrust;

        if ($logger == null)
        {
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;
    }

    /**
     * Gets called before baking the site.
     */
    public function onBakeStart(IBaker $baker)
    {
    }

    /**
     * Gets called before a page is baked.
     */
    public function onPageBakeStart(IPage $page)
    {
    }

    /**
     * Gets called after a page is baked.
     */
    public function onPageBakeEnd(IPage $page, BakeResult $result)
    {
    }

    /**
     * Gets called after baking the site.
     */
    public function onBakeEnd(IBaker $baker)
    {
    }
    // }}}
}

