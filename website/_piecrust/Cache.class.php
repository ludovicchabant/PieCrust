<?php

class Cache
{
	protected $baseDir;
	protected $commentTags;
	
	public function __construct($baseDir)
	{
		$this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$this->commentTags = array(
				'html' => array('<!-- ', ' -->'),
				'yml' => array('# ', '')
			);
	}
	
	public function isValid($uri, $extension, timestamp $time)
	{
		$cacheTime = $this->getCacheTime($uri, $extension);
		if ($cacheTime == null)
			return false;
		return $cacheTime > $time;
	}
	
	public function getCacheTime($uri, $extension)
	{
		$cachePath = $this->getCachePath($uri, $extension);
		if (!file_exists($cachePath))
			return null;
		return filemtime($cachePath);
	}
	
	public function read($uri, $extension)
	{
		$cachePath = $this->getCachePath($uri, $extension);
		return file_get_contents($cachePath);
	}
	
	public function write($uri, $extension, $contents)
	{
		$cachePath = $this->getCachePath($uri, $extension);
		$commentTags = $this->commentTags[$extension];
		$header = $commentTags[0] . 'PieCrust ' . PieCrust::VERSION . ' - cached ' . date('Y-m-d H:i:s:u') . $commentTags[1] . "\n";
		file_put_contents($cachePath, ($header . $contents));
	}
	
	protected function getCachePath($uri, $extension)
	{
		return $this->baseDir . ltrim($uri, '/\\') . ($extension == null ? '' : ('.' . $extension));
	}
}

