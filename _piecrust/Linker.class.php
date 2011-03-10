<?php

define('PIECRUST_LINKER_DIR_SUFFIX', '_');

require_once 'PageWrapper.class.php';

/**
 *
 */
class Linker implements ArrayAccess, Iterator
{
    protected $pieCrust;
    protected $baseDir;
    protected $linksCache;
    
    public function __construct(PieCrust $pieCrust, $baseDir)
    {
        $this->pieCrust = $pieCrust;
        $this->baseDir = $baseDir;
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
		$this->ensureLinksCache();
		return isset($this->linksCache[$offset]);
	}
	
	public function offsetGet($offset) 
	{
		$this->ensureLinksCache();
		return $this->linksCache[$offset];
	}
	
	public function offsetSet($offset, $value)
	{
		throw new PieCrustException('Linker is read-only.');
	}
	
	public function offsetUnset($offset)
	{
		throw new PieCrustException('Linker is read-only.');
	}
    
    public function rewind()
    {
        $this->ensureLinksCache();
        return reset($this->linksCache);
    }
  
    public function current()
    {
        $this->ensureLinksCache();
        return current($this->linksCache);
    }
  
    public function key()
    {
        $this->ensureLinksCache();
        return key($this->linksCache);
    }
  
    public function next()
    {
        $this->ensureLinksCache();
        $res = next($this->linksCache);
        while ($res !== null and ($res instanceof Linker))
        {
            $res = next($this->linksCache);
        }
        return $res;
    }
  
    public function valid()
    {
        $this->ensureLinksCache();
        return key($this->linksCache) !== null;
    }
    
    protected function ensureLinksCache()
    {
        if ($this->linksCache === null)
        {
            $this->linksCache = array();
            $pagesDir = $this->pieCrust->getPagesDir();
            $it = new FilesystemIterator($this->baseDir);
            foreach ($it as $item)
            {
                if ($item->isDir())
                {
                    $key = $item->getBasename() . PIECRUST_LINKER_DIR_SUFFIX;
                    $this->linksCache[$key] = new Linker($this->pieCrust, $item->getPathname());
                }
                else
                {
                    $key = $item->getBasename('.html');
                    $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen($pagesDir)));
                    $uri = preg_replace('/\.[a-zA-Z0-9]+$/', '', $relativePath);
                    $uri = str_replace('_index', '', $uri);
                    $pageInfo = array(
                        'uri' => $uri,
                        'name' => $key,
                        'page' => new PageWrapper(Page::create($this->pieCrust, $uri, $item->getPathname()))
                    );
                    $this->linksCache[$key] = $pageInfo;
                }
            }
        }
    }
}
