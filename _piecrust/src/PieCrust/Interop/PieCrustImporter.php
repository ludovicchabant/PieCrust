<?php

namespace PieCrust\Interop;

use \Exception;
use PieCrust\PieCrust;
use PieCrust\Interop\Importers\IImporter;
use PieCrust\Util\PluginLoader;


/**
 * A class that bootstraps the importer classes to import content into a PieCrust website.
 */
class PieCrustImporter
{
    protected $pieCrust;
    protected $importersLoader;
    
    /**
     * Creates a new instance of PieCrustImporter.
     */
    public function __construct(PieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->importersLoader = new PluginLoader(
            'PieCrust\\Interop\\Importers\\IImporter',
            __DIR__ . '/Importers');
    }
    
    /**
     * Imports content at the given source, using the given importer format.
     */
    public function import($format, $source)
    {
        $format = ucfirst(strtolower($format));
        $type = $format . 'Importer';
        
        foreach ($this->importersLoader->getPlugins() as $importer)
        {
            if ($importer->getName() == $format)
            {
                $this->doImport($importer, $source);
                return;
            }
        }
        
        throw new Exception('Importer format "' . $format . '" is unknown.');
    }
    
    protected function doImport(IImporter $importer, $source)
    {
        try
        {
            echo 'Importing "' . $source . '" using "' . $importer->getName() . '".' . PHP_EOL;
            $importer->open($source);
            $importer->importPages($this->pieCrust()->getPagesDir());
            $importer->importPosts($this->pieCrust()->getPostsDir(), $this->pieCrust->getConfig()->getValue('site/posts_fs'));
            $importer->close();
        }
        catch (Exception $e)
        {
            echo 'Error importing from "' . $source . '": ' . $e->getMessage();
        }
    }
}
