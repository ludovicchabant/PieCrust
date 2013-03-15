<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\PieCrustException;
use PieCrust\Page\Linker;
use PieCrust\Page\Iteration\RecursiveLinkerIterator;


/**
 * The object passed to the template engine for the site
 * data.
 *
 * @explicitInclude
 */
class SiteData
{
    protected $page;
    protected $userData;

    public function __construct(IPage $page)
    {
        $this->page = $page;
    }

    // {{{ Template functions
    /**
     * @noCall
     * @include
     * @documentation The list of all pages in the website.
     */
    public function pages()
    {
        $linker = new Linker($this->page, $this->page->getApp()->getPagesDir());
        return new RecursiveLinkerIterator($linker);
    }
    // }}}

    // {{{ User data functions
    public function mergeUserData(array $userData)
    {
        $this->userData = $userData;
    }

    public function __isset($name)
    {
        return $this->userData != null and isset($this->userData[$name]);
    }

    public function __get($name)
    {
        if ($this->userData == null)
            return null;
        return $this->userData[$name];
    }
    // }}}
}

