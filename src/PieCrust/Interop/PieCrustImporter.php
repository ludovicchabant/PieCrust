<?php

namespace PieCrust\Interop;

use \Exception;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Interop\Importers\IImporter;


/**
 * A class that bootstraps the importer classes to import content into a PieCrust website.
 */
class PieCrustImporter
{
    protected $pieCrust;
    protected $logger;

    /**
     * Creates a new instance of PieCrustImporter.
     */
    public function __construct(IPieCrust $pieCrust, $logger = null)
    {
        $this->pieCrust = $pieCrust;

        if ($logger == null)
        {
            $logger = \Log::singleton('null', '', '');
        }
        $this->logger = $logger;
    }

    /**
     * Gets the known importers.
     */
    public function getImporters()
    {
        return $this->pieCrust->getPluginLoader()->getImporters();
    }
    
    /**
     * Imports content at the given source, using the given importer format.
     */
    public function import($format, $source, $options)
    {
        // Find the importer that matches the given name and run the import.
        foreach ($this->getImporters() as $importer)
        {
            if ($importer->getName() == $format)
            {
                $this->logger->info("Importing '{$source}' using '{$importer->getName()}'");
                $importer->import($this->pieCrust, $source, $this->logger, $options);
                return;
            }
        }
        
        throw new PieCrustException("Importer format '{$format} ' is unknown.");
    }
}
