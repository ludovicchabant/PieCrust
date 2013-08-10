<?php

namespace PieCrust\Page;

use \Exception;
use \FilesystemIterator;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Page\Iteration\BaseIterator;
use PieCrust\Util\PathHelper;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Util\UriBuilder;


/**
 * A class that exposes the list of pages in a folder to another page.
 *
 * @formatObject
 * @explicitInclude
 * @documentation The list of pages in the current sub-directory.
 */
class Linker extends BaseIterator implements \RecursiveIterator
{
    protected $page;
    protected $baseDir;

    protected $sortByName;
    protected $sortByReverse;

    /**
     * Creates a new instance of Linker.
     */
    public function __construct(IPage $page, $dir = null)
    {
        $this->page = $page;
        $this->baseDir = ($dir != null) ? $dir : dirname($page->getPath());
        $this->baseDir = rtrim($this->baseDir, '/\\') . '/';

        $this->sortByName = null;
        $this->sortByReverse = false;
    }

    // {{{ Internal members
    public function getPage()
    {
        return $this->page;
    }
    // }}}

    // {{{ Template data members
    /**
     * Gets the name of the current directory.
     */
    public function name()
    {
        if (strlen($this->baseDir) == strlen($this->page->getApp()->getPagesDir()))
            return '';
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
        return $this->sort($name, $reverse);
    }

    /**
     * @noCall
     */
    public function sort($name, $reverse = false)
    {
        $this->sortByName = $name;
        $this->sortByReverse = $reverse;
        return $this;
    }
    // }}}

    // {{{ RecursiveIterator members
    public function getChildren()
    {
        $linker = $this->current();
        if (!($linker instanceof Linker))
            return null;
        return $linker;
    }

    public function hasChildren()
    {
        $linker = $this->current();
        if (!($linker instanceof Linker))
            return false;
        return $linker->count() > 0;
    }
    // }}}

    // {{{ Loading members
    protected function load()
    {
        try
        {
            $pieCrust = $this->page->getApp();
            $pageRepository = $pieCrust->getEnvironment()->getPageRepository();

            $items = array();
            $skipNames = array('Thumbs.db');
            $it = new FilesystemIterator($this->baseDir);
            foreach ($it as $item)
            {
                $filename = $item->getFilename();

                // Skip dot files, Thumbs.db, etc.
                if (!$filename or $filename[0] == '.')
                    continue;
                if (in_array($filename, $skipNames))
                    continue;

                if ($item->isDir())
                {
                    // Skip "asset" directories.
                    if (preg_match('/\-assets$/', $filename))
                        continue;

                    $linker = new Linker($this->page, $item->getPathname());
                    $items[$filename . '_'] = $linker;
                    // We add '_' at the end of the directory name to avoid
                    // collisions with a possibly existing page with the same
                    // name (since we strip out the file extension).
                    // This means the user must access directories with
                    // 'link.dirname_' instead of 'link.dirname' but hey, if
                    // you have a better idea, send me an email!
                }
                else
                {
                    $path = $item->getPathname();
                    try
                    {
                        // To get the link's page, we need to be careful with the case
                        // where that page is the currently rendering one. This is
                        // because it could be rendering a sub-page -- but we would be
                        // requesting the default first page, which would effectively
                        // change the page number *while* we're rendering, which leads
                        // to all kinds of bad things!
                        // TODO: obviously, there needs to be some design changes to
                        // prevent this kind of chaotic behaviour.
                        if (str_replace('\\', '/', $path) == str_replace('\\', '/', $this->page->getPath()))
                        {
                            $page = $this->page;
                        }
                        else
                        {
                            $relativePath = PathHelper::getRelativePath($pieCrust->getPagesDir(), $path);
                            $uri = UriBuilder::buildUri($pieCrust, $relativePath);
                            $page = $pageRepository->getOrCreatePage($uri, $path);
                        }

                        $key = preg_replace('/\.[a-zA-Z0-9]+$/', '', $filename);
                        $key = str_replace('.', '_', $key);
                        $items[$key] = new LinkData(
                            $page,
                            array(
                                'name' => $key,
                                'is_dir' => false,
                                'is_self' => ($page == $this->page)
                            )
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
                if (false === usort($items, array($this, 'sortByCustom')))
                    throw new PieCrustException("Error while sorting pages with the specified setting: {$this->sortByName}");
            }

            return $items;
        }
        catch (Exception $e)
        {
            throw new PieCrustException(
                "Error while building the links from page '{$this->page->getUri()}': " .
                $e->getMessage(), 0, $e
            );
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

        $propertyPath = str_replace('.', '/', $this->sortByName);
        $page1 = $link1->getPage();
        $value1 = $page1->getConfig()->getValue($propertyPath);
        $page2 = $link2->getPage();
        $value2 = $page2->getConfig()->getValue($propertyPath);

        if ($value1 == $value2)
            return 0;
        if ($value1 == null && $value2 != null)
            return $this->sortByReverse ? 1 : -1;
        if ($value1 != null && $value2 == null)
            return $this->sortByReverse ? -1 : 1;
        if ($this->sortByReverse)
            return ($value1 < $value2) ? 1 : -1;
        else
            return ($value1 < $value2) ? -1 : 1;
    }
    // }}}
}
