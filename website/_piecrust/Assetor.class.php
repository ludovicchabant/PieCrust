<?php

/**
 * The asset manager for PieCrust pages.
 *
 * The Assetor (worst class name ever) handles lazy loading of a page's
 * assets, stored in a subdirectory with the same name as the page file.
 *
 */
class Assetor implements ArrayAccess
{
	protected $assetsDir;
	protected $assetsUrlBase;
	
	public function __construct(PieCrust $pieCrust, Page $page)
	{		
		$pathParts = pathinfo($page->getPath());
		$this->assetsDir = $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'];
		$this->assetsUrlBase = $pieCrust->getHost() . $pieCrust->getUrlBase() . PIECRUST_CONTENT_PAGES_DIR . $page->getUri();
		
		if (!is_dir($this->assetsDir))
		{
			$this->assetsDir = false;
		}
	}
	
	public function __isset($name)
	{
		return $this->offsetExists($name);
	}
	
	public function __get($name)
	{
		return $this->offsetGet($name);
	}
	
	public function offsetExists($offset)
	{
		$this->ensureAssetsCache();
		return isset($this->assetsCache[$offset]);
	}
	
	public function offsetGet($offset) 
	{
		$this->ensureAssetsCache();
		return $this->assetsCache[$offset];
	}
	
	public function offsetSet($offset, $value)
	{
		throw new PieCrustException('Assetor is read-only.');
	}
	
	public function offsetUnset($offset)
	{
		throw new PieCrustException('Assetor is read-only.');
	}
	
	protected $assetsCache;
	
	protected function ensureAssetsCache()
	{
		if ($this->assetsCache === null)
		{
			$this->assetsCache = array();
			
			if ($this->assetsDir !== false)
			{
				$paths = new FilesystemIterator($this->assetsDir);
				foreach ($paths as $p)
				{
					$filename = $p->getFilename();
					$key = str_replace('.', '_', $filename);
					$this->assetsCache[$key] = $this->assetsUrlBase . '/' . $filename;
				}
			}
		}
	}
}
