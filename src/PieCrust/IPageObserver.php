<?php

namespace PieCrust;


/**
 * Interface for an object that wants to be notified
 * when a page is loaded or unloaded.
 */
interface IPageObserver
{
    /**
     * Called when the page is loaded.
     */
    public function onPageLoaded($page);

    /**
     * Called when the page is formatted.
     */
    public function onPageFormatted($page);

    /**
     * Called when the page is unloaded.
     */
    public function onPageUnloaded($page);
}

