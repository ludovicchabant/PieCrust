<?php

require_once 'PieCrust.class.php';
require_once 'IImporter.class.php';

define('PIECRUST_IMPORT_DIR', '_import');

/**
 * A class that bootstraps the importer classes to import content into a PieCrust website.
 */
class PieCrustImporter
{
	protected $pieCrust;
	
	protected $importDir;
	/**
	 * Gets the import directory.
	 */
	public function getImportDir()
	{
		if ($this->importDir === null)
		{
            $this->setImportDir($this->pieCrust()->getRootDir() . PIECRUST_IMPORT_DIR);
		}
        return $this->importDir;
	}
	
	/**
	 * Sets the import directory.
	 */
	public function setImportDir($dir)
	{
		$this->importDir = $dir;
		if (is_dir($this->importDir) === false)
		{
            throw new PieCrustException('The import directory doesn\'t exist: ' . $this->importDir);
		}
	}
	
	/**
	 * Creates a new instance of PieCrustImporter.
	 */
	public function __construct(PieCrust $pieCrust)
	{
		$this->pieCrust = $pieCrust;
	}
	
	/**
	 * Imports content found in the import directory.
	 */
	public function import()
	{
		$files = new FilesystemIterator($this->getImportDir());
		foreach ($files as $f)
		{
			echo 'Importing: ' . $f->getFilename() . "\n";
			
			$fi = pathinfo($f->getFilename());
			$type = $fi['filename'] . 'Importer';
			
			require_once ('importers' . DIRECTORY_SEPARATOR . $type . '.class.php');
			$importer = new $type();
			$importer->open($f->getPathname());
			$importer->importPosts($this->pieCrust()->getPostsDir(), $this->pieCrust->getConfigValue('site', 'posts_fs'));
			$importer->close();
		}
	}
}
