<?php

namespace PieCrust\Plugins\Repositories;


/**
 * An interface for an online repository of
 * PieCrust plugins.
 */
interface IPluginRepository
{
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
     * Installs the given plugin.
     */
    public function installPlugin($plugin, $context);
}

