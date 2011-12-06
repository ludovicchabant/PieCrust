<?php

namespace PieCrust\Interop;

use \Exception;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Interop\Importers\IImporter;
use PieCrust\IO\FileSystem;
use PieCrust\Util\PluginLoader;


/**
 * A class that bootstraps the importer classes to import content into a PieCrust website.
 */
class PieCrustImporter
{
    protected $importersLoader;

    /**
     * Creates a new instance of PieCrustImporter.
     */
    public function __construct()
    {
        $this->importersLoader = new PluginLoader(
            'PieCrust\\Interop\\Importers\\IImporter',
            PieCrustDefaults::APP_DIR . '/Interop/Importers');
    }

    /**
     * Gets the known importers.
     */
    public function getImporters()
    {
        return $this->importersLoader->getPlugins();
    }
    
    /**
     * Imports content at the given source, using the given importer format.
     */
    public function import(IPieCrust $pieCrust, $format, $source)
    {
        // Find the importer that matches the given name and run the import.
        foreach ($this->importersLoader->getPlugins() as $importer)
        {
            if ($importer->getName() == $format)
            {
                $this->doImport($pieCrust, $importer, $source);
                return;
            }
        }
        
        throw new PieCrustException('Importer format "' . $format . '" is unknown.');
    }
    
    protected function doImport(IPieCrust $pieCrust, IImporter $importer, $source)
    {
        echo 'Importing "' . $source . '" using "' . $importer->getName() . '".' . PHP_EOL;
        $importer->open($source);
        $importer->importPages($pieCrust->getPagesDir());
        $importer->importPosts($pieCrust->getPostsDir(), $pieCrust->getConfig()->getValue('site/posts_fs'));
        $importer->close();
    }
}
