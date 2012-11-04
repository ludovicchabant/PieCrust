<?php

namespace PieCrust\Repositories;

use PieCrust\IPieCrust;


/**
 * An interface for an online repository of
 * PieCrust plugins.
 */
interface IRepository
{
    /**
     * Initializes the repository for the given website.
     */
    public function initialize(IPieCrust $pieCrust);

    /**
     * Returns whether this repository can read
     * from the given source.
     */
    public function supportsSource($source);

    /**
     * Gets the plugin metadata available at the
     * given source.
     */
    public function getPlugins($source);

    /**
     * Gets the theme metadata available at the
     * given source.
     */
    public function getThemes($source);

    /**
     * Installs the given plugin.
     */
    public function installPlugin($plugin, $context);

    /**
     * Installs the given theme.
     */
    public function installTheme($theme, $context);
}

