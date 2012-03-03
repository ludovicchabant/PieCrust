<?php

namespace PieCrust\Page;

use \Exception;
use \FilesystemIterator;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\PageConfigWrapper;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PathHelper;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Util\UriBuilder;


/**
 * A class that exposes the list of pages in a folder to another page.
 */
class Linker implements \ArrayAccess, \Iterator
{
    protected $page;
    protected $baseDir;
    protected $selfName;
    protected $linksCache;
    
    /**
     * Creates a new instance of Linker.
     */
    public function __construct(IPage $page, $dir = null)
    {
        $this->page = $page;
        if ($dir != null)
        {
            $this->baseDir = $dir;
            $this->selfName = null;
        }
        else
        {
            $this->baseDir = dirname($page->getPath()) . '/';
            $this->selfName = basename($page->getPath());
        }
    }

    // {{{ Template Data Members
    /**
     * Gets the name of the current directory.
     * @include
     */
    public function name()
    {
        return basename($this->baseDir);
    }
    
    /**
     * Gets whether this maps to a directory. Always returns true.
     * @include
     */
    public function is_dir()
    {
        return true;
    }

    /**
     * Gets whether this maps to the current page. Always returns false.
     * @include
     */
    public function is_self()
    {
        return false;
    }
    // }}}
    
    // {{{ ArrayAccess members
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
    // }}}
    
    // {{{ Iterator members
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
        return next($this->linksCache);
    }
  
    public function valid()
    {
        $this->ensureLinksCache();
        return key($this->linksCache) !== null;
    }
    // }}}
    
    protected function ensureLinksCache()
    {
        if ($this->linksCache === null)
        {
            try
            {
                $pageRepository = $this->page->getApp()->getEnvironment()->getPageRepository();

                $this->linksCache = array();
                $it = new FilesystemIterator($this->baseDir);
                foreach ($it as $item)
                {
                    $basename = $item->getBasename();
                    if (!$basename or $basename[0] == '.')
                    {
                        continue;
                    }
                    else if ($item->isDir())
                    {
                        $linker = new Linker($this->page, $item->getPathname());
                        $this->linksCache[$basename . '_'] = $linker;
                        // We add '_' at the end of the directory name to avoid
                        // collisions with a possibly existing page with the same
                        // name (since we strip out the '.html' extension).
                        // This means the user must access directories with
                        // 'link.dirname_' instead of 'link.dirname' but hey, if
                        // you have a better idea, send me an email!
                    }
                    else if (pathinfo($basename, PATHINFO_EXTENSION) == 'html')
                    {
                        $path = $item->getPathname();
                        $key = $item->getBasename('.html');
                        try
                        {
                            $relativePath = PathHelper::getRelativePagePath($this->page->getApp(), $path, $this->page->getPageType());
                            $uri = UriBuilder::buildUri($relativePath);
                            $page = $pageRepository->getOrCreatePage($uri, $path);
                            $this->linksCache[$key] = array(
                                'uri' => $uri,
                                'name' => $key,
                                'is_dir' => false,
                                'is_self' => ($basename == $this->selfName),
                                'page' => new PageConfigWrapper($page)
                            );
                        }
                        catch (Exception $e)
                        {
                            throw new PieCrustException("Error while loading page '" . $path .
                                                        "' for linking from '" . $this->page->getUri() .
                                                        "': " . $e->getMessage(), 0, $e);
                        }
                    }
                }
                
                if ($this->selfName != null)
                {
                    if (PageHelper::isRegular($this->page))
                    {
                        // Add a link to go up to the parent directory, but stay inside
                        // the app's pages directory.
                        $parentBaseDir = dirname($this->baseDir);
                        if (strlen($parentBaseDir) >= strlen($this->page->getApp()->getPagesDir()))
                        {
                            $linker = new Linker($this->page, dirname($this->baseDir));
                            $this->linksCache['_'] = $linker;
                        }
                    }
                    else if (PageHelper::isPost($this->page))
                    {
                        // Add a link to go up to the parent directory, but stay inside
                        // the app's posts directory.
                        $parentBaseDir = dirname($this->baseDir);
                        if (strlen($parentBaseDir) >= strlen($this->page->getApp()->getPostsDir()))
                        {
                            $linker = new Linker($this->page, dirname($this->baseDir));
                            $this->linksCache['_'] = $linker;
                        }
                    }

                    if ($this->page->getApp()->getPagesDir())
                    {
                        // Add a shortcut to the pages directory.
                        $linker = new Linker($this->page, $this->page->getApp()->getPagesDir());
                        $this->linksCache['_pages_'] = $linker;
                    }
                    
                    if ($this->page->getApp()->getPostsDir())
                    {
                        // Add a shortcut to the posts directory.
                        $linker = new Linker($this->page, $this->page->getApp()->getPostsDir());
                        $this->linksCache['_posts_'] = $linker;
                    }
                }
            }
            catch (Exception $e)
            {
                throw new PieCrustException("Error while building the links from page '" . $this->page->getUri() .
                                            "': " . $e->getMessage(), 0, $e);
            }
        }
    }
}
