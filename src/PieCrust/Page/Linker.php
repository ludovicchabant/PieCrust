<?php

namespace PieCrust\Page;

use \Exception;
use \FilesystemIterator;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Data\PaginationData;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Util\UriBuilder;


/**
 * A class that exposes the list of pages in a folder to another page.
 *
 * @formatObject
 * @explicitInclude
 * @documentation The list of pages in the same directory as the current one. See the documentation for more information.
 */
class Linker implements \ArrayAccess, \Iterator, \Countable
{
    protected $page;
    protected $baseDir;
    protected $selfName;

    protected $sortByName;
    protected $sortByReverse;

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

        $this->sortByName = null;
        $this->sortByReverse = false;
    }

    // {{{ Template Data Members
    /**
     * Gets the name of the current directory.
     */
    public function name()
    {
        return basename($this->baseDir);
    }
    
    /**
     * Gets whether this maps to a directory. Always returns true.
     */
    public function is_dir()
    {
        return true;
    }

    /**
     * Gets whether this maps to the current page. Always returns false.
     */
    public function is_self()
    {
        return false;
    }

    /**
     * @noCall
     */
    public function sortBy($name, $reverse = false)
    {
        $this->sortByName = $name;
        $this->sortByReverse = $reverse;
        return $this;
    }
    // }}}
    
    // {{{ Countable members
    public function count()
    {
        $this->ensureLinksCache();
        return count($this->linksCache);
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
        reset($this->linksCache);
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
        next($this->linksCache);
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
                $pieCrust = $this->page->getApp();
                $pageRepository = $pieCrust->getEnvironment()->getPageRepository();

                $this->linksCache = array();
                $skipNames = array('Thumbs.db');
                $it = new FilesystemIterator($this->baseDir);
                foreach ($it as $item)
                {
                    $basename = $item->getBasename();

                    // Skip dot files, Thumbs.db, etc.
                    if (!$basename or $basename[0] == '.')
                        continue;
                    if (in_array($item->getFilename(), $skipNames))
                        continue;
                    
                    if ($item->isDir())
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
                    else
                    {
                        $path = $item->getPathname();
                        try
                        {
                            $relativePath = PageHelper::getRelativePath($this->page);
                            $uri = UriBuilder::buildUri($relativePath);

                            // To get the link's page, we need to be careful with the case
                            // where that page is the currently rendering one. This is
                            // because it could be rendering a sub-page -- but we would be
                            // requesting the default first page, which would effectively
                            // change the page number *while* we're rendering, which leads
                            // to all kinds of bad things!
                            // TODO: obviously, there needs to be some design changes to
                            // prevent this kind of chaotic behaviour. 
                            if ($path == $this->page->getPath())
                                $page = $this->page;
                            else
                                $page = $pageRepository->getOrCreatePage($uri, $path);
                            
                            $key = str_replace('.', '_', $item->getBasename('.html'));
                            $this->linksCache[$key] = array(
                                'uri' => $uri,
                                'name' => $key,
                                'is_dir' => false,
                                'is_self' => ($basename == $this->selfName),
                                'page' => new PaginationData($page)
                            );
                        }
                        catch (Exception $e)
                        {
                            throw new PieCrustException(
                                "Error while loading page '{$path}' for linking from '{$this->page->getUri()}': " .
                                $e->getMessage(), 0, $e
                            );
                        }
                    }
                }

                if ($this->sortByName)
                {
                    if (false === usort($this->linksCache, array($this, 'sortByCustom')))
                        throw new PieCrustException("Error while sorting pages with the specified setting: {$this->sortByName}");
                }
                
                if ($this->selfName != null)
                {
                    // Add special stuff only for the original Linker
                    // (the one directly created by the current page).
                    if (PageHelper::isRegular($this->page))
                    {
                        // Add a link to go up to the parent directory, but stay inside
                        // the app's pages directory.
                        $parentBaseDir = dirname($this->baseDir);
                        if (strlen($parentBaseDir) >= strlen($pieCrust->getPagesDir()))
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
                        if (strlen($parentBaseDir) >= strlen($pieCrust->getPostsDir()))
                        {
                            $linker = new Linker($this->page, dirname($this->baseDir));
                            $this->linksCache['_'] = $linker;
                        }
                    }

                    if ($pieCrust->getPagesDir())
                    {
                        // Add a shortcut to the pages directory.
                        $linker = new Linker($this->page, $pieCrust->getPagesDir());
                        $this->linksCache['_pages_'] = $linker;
                    }
                    
                    if ($pieCrust->getPostsDir())
                    {
                        // Add a shortcut to the posts directory.
                        $linker = new Linker($this->page, $pieCrust->getPostsDir());
                        $this->linksCache['_posts_'] = $linker;
                    }
                }
            }
            catch (Exception $e)
            {
                throw new PieCrustException(
                    "Error while building the links from page '{$this->page->getUri()}': " .
                    $e->getMessage(), 0, $e
                );
            }
        }
    }

    protected function sortByCustom($link1, $link2)
    {
        $link1IsLinker = ($link1 instanceof Linker);
        $link2IsLinker = ($link2 instanceof Linker);

        if ($link1IsLinker && $link2IsLinker)
        {
            $c = strcmp($link1->name(), $link2->name());
            return $this->sortByReverse ? -$c : $c;
        }
        if ($link1IsLinker)
            return $this->sortByReverse ? 1 : -1;
        if ($link2IsLinker)
            return $this->sortByReverse ? -1 : 1;

        $page1 = $link1['page']->getPage();
        $value1 = $page1->getConfig()->getValue($this->sortByName);
        $page2 = $link2['page']->getPage();
        $value2 = $page2->getConfig()->getValue($this->sortByName);
        
        if ($value1 == null && $value2 == null)
            return 0;
        if ($value1 == null && $value2 != null)
            return $this->sortByReverse ? 1 : -1;
        if ($value1 != null && $value2 == null)
            return $this->sortByReverse ? -1 : 1;
        if ($value1 == $value2)
            return 0;
        if ($this->sortByReverse)
            return ($value1 < $value2) ? 1 : -1;
        else
            return ($value1 < $value2) ? -1 : 1;
    }

}
