<?php

namespace PieCrust\Interop\Importers;

use PieCrust\IPieCrust;


/**
 * Interface to classes that import content from other CMS/engines.
 *
 */
interface IImporter
{
    /**
     * Gets the name of this importer.
     */
    public function getName();

    /**
     * Gets the description of this importer.
     */
    public function getDescription();

    /**
     * Gets the help topic for this importer.
     */
    public function getHelpTopic();
    
    /**
     * Imports a website.
     */
    public function import(IPieCrust $pieCrust, $connection, $logger);
}
