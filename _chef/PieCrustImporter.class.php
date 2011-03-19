<?php

require_once 'IImporter.class.php';


/**
 * A class that bootstraps the importer classes to import content into a PieCrust website.
 */
class PieCrustImporter
{
	protected $pieCrust;
	
	/**
	 * Creates a new instance of PieCrustImporter.
	 */
	public function __construct(PieCrust $pieCrust)
	{
		$this->pieCrust = $pieCrust;
	}
	
	/**
	 * Imports content at the given source, using the given importer format.
	 */
	public function import($format, $source)
	{
		$format = ucfirst(strtolower($format));
		$type = $format . 'Importer';
		try
		{
			include_once ('importers/' . $type . '.class.php');
		}
		catch (Exception $e)
		{
			throw new Exception('Importer format "' . $format . '" is unknown: ' . $e->getMessage());
		}
		
		echo 'Importing "' . $source . '" using format "' . $format . '".' . PHP_EOL;
		$importer = new $type();
		$importer->open($source);
		$importer->importPages($this->pieCrust()->getPagesDir());
		$importer->importPosts($this->pieCrust()->getPostsDir(), $this->pieCrust->getConfigValue('site', 'posts_fs'));
		$importer->close();
	}
}
