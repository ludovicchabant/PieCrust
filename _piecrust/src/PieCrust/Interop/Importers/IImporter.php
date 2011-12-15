<?php

namespace PieCrust\Interop\Importers;


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
     * Opens the file/database/whatever.
     */
    public function open($connection);
    
    /**
     * Imports the pages into the given directory.
     */
    public function importPages($pagesDir);
    
    /**
     * Imports posts into the given directory using the given format.
     */
    public function importPosts($postsDir, $mode);
    
    /**
     * Closes any open resources.
     */
    public function close();
}
