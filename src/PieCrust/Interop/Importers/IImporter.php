<?php

namespace PieCrust\Interop\Importers;

use \Console_CommandLine;
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
     * Sets up any custom options for the importer.
     */
    public function setupParser(Console_CommandLine $parser);
    
    /**
     * Imports a website.
     */
    public function import(IPieCrust $pieCrust, $connection, $logger, $options = null);
}
