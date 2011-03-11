<?php

define('PIECRUST_ASSET_DIR_SUFFIX', '-assets');
if (!defined('PIECRUST_ASSET_URL_SUFFIX'))
{
	define ('PIECRUST_ASSET_URL_SUFFIX', PIECRUST_ASSET_DIR_SUFFIX);
}

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
	protected $assetsCache;
	
	protected $urlBase;
	/**
	 * Gets the base URL for the assets.
	 */
	public function getUrlBase()
	{
		return $this->urlBase;
	}
	
	/**
	 * Sets the base URL for the assets.
	 */
	public function setUrlBase($urlBase)
	{
		if ($this->assetsCache != null) throw new PieCrustException("The base URL can only be set before the assets are loaded.");
		$this->urlBase = rtrim($urlBase, '/');
	}
	
	/**
	 * Gets all the asset path-names.
	 */
	public function getAssetPathnames()
	{
		if ($this->assetsDir === false) return null;
		return new FilesystemIterator($this->assetsDir, FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS);
	}
	
	/**
	 * Creates a new instance of Assetor.
	 */
	public function __construct(PieCrust $pieCrust, Page $page)
	{
		$pathParts = pathinfo($page->getPath());
		$this->assetsDir = $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'] . PIECRUST_ASSET_DIR_SUFFIX;
		if (is_dir($this->assetsDir))
		{
			if ($page->getAssetUrlBaseRemap() != null)
			{
				$this->urlBase = Assetor::buildUrlBase($pieCrust, $page);
			}
			else
			{
				$relativePath = str_replace('\\', '/', $page->getRelativePath(true));
				$this->urlBase = $pieCrust->getUrlBase() . $relativePath . PIECRUST_ASSET_DIR_SUFFIX;
			}
		}
		else
		{
			$this->assetsDir = false;
			$this->urlBase = false;
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
					$this->assetsCache[$key] = $this->urlBase . '/' . $filename;
				}
			}
		}
	}
	
	protected static function buildUrlBase(PieCrust $pieCrust, Page $page)
    {
        $replacements = array(
			'%url_base%' => $pieCrust->getUrlBase(),
			'%path' => $page->getRelativePath(true),
			'%uri%' => $page->getUri()
		);
        return str_replace(array_keys($replacements), array_values($replacements), $page->getAssetUrlBaseRemap());
    }
}
