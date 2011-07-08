<?php

require_once 'FileSystem.class.php';


/**
 * Describes a flat PieCrust blog file-system.
 */
class FlatFileSystem extends FileSystem
{
    public function __construct(PieCrust $pieCrust)
	{
		FileSystem::__construct($pieCrust);
	}
    
    public function getPostFiles()
    {
		$pathPattern = $this->pieCrust->getPostsDir() . '*.html';
		$paths = glob($pathPattern, GLOB_ERR);
		if ($paths === false)
		{
			throw new PieCrustException('An error occured while reading the posts directory.');
		}
		rsort($paths);
		
		$result = array();
		foreach ($paths as $path)
		{
			$matches = array();
			
			$filename = pathinfo($path, PATHINFO_BASENAME);
			if (preg_match('/^(\d{4})-(\d{2})-(\d{2})_(.*)\.html$/', $filename, $matches) == false)
				continue;
			
			$result[] = array(
				'year' => $matches[1],
				'month' => $matches[2],
				'day' => $matches[3],
				'name' => $matches[4],
				'path' => $path
			);
		}
		return $result;
	}
}
