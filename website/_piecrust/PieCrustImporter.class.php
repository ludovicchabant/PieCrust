<?php

require_once 'PieCrust.class.php';
require_once 'IImporter.class.php';

define('PIECRUST_IMPORT_DIR', '_import');

class PieCrustImporter
{
	public $postsFs;
	
	public function __construct()
	{
		$this->postsFs = 'hierarchy';
	}
	
	public function importContent()
	{
		$files = new FilesystemIterator(PIECRUST_ROOT_DIR . PIECRUST_IMPORT_DIR);
		foreach ($files as $f)
		{
			echo 'Importing: ' . $f->getFilename() . "\n";
			$fi = pathinfo($f->getFilename());
			$type = $fi['filename'] . 'Importer';
			require_once ('importers' . DIRECTORY_SEPARATOR . $type . '.class.php');
			$importer = new $type();
			$importer->open($f->getPathname());
			$importer->importPosts(PIECRUST_ROOT_DIR . PIECRUST_CONTENT_POSTS_DIR, $this->postsFs);
			$importer->close();
		}
	}
}
