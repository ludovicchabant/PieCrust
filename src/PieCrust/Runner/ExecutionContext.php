<?php

namespace PieCrust\Runner;


/**
 * A class that describes the current request.
 */
class ExecutionContext
{
    protected $pageStack;

    public $isCacheValid;
    public $wasCacheCleaned;

    public $startTime;

    public function pushPage($page)
    {
        array_push($this->pageStack, $page);
    }

    public function popPage()
    {
        return array_pop($this->pageStack);
    }

    public function getPage()
    {
        $count = count($this->pageStack);
        if ($count == 0)
            return null;
        return $this->pageStack[$count - 1];
    }

    public function isMainPage()
    {
        return count($this->pageStack) == 1;
    }

    public function __construct($startTime = false)
    {
        $this->isCacheValid = false;
        $this->wasCacheCleaned = false;

        $this->startTime = $startTime;
        if ($this->startTime === false)
            $this->startTime = microtime(true);

        $this->pageStack = array();
    }
}

