<?php

namespace PieCrust\Page\Iteration;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Data\PaginationData;
use PieCrust\Page\Filtering\PaginationFilter;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PieCrustHelper;


/**
 * A class that can return several pages with filtering and all.
 *
 * The data-source must be an array of IPages.
 *
 * @formatObject
 * @explicitInclude
 * @documentation The list of posts.
 */
class PageIterator extends BaseIterator
{
    protected $pieCrust;
    protected $blogKey;
    
    protected $iterator;
    protected $gotSorter;
    protected $isLocked;

    protected $page;
    protected $previousPost;
    protected $nextPost;
    protected $paginationSlicer;
    
    public function __construct(IPieCrust $pieCrust, $blogKey, array $dataSource)
    {
        parent::__construct();

        $this->pieCrust = $pieCrust;
        $this->blogKey = $blogKey;
        
        $this->iterator = new WrapperIterator($dataSource);
        $this->gotSorter = false;
        $this->isLocked = false;

        $this->page = null;
        $this->previousPost = null;
        $this->nextPost = null;
        $this->paginationSlicer = null;
    }

    // {{{ Internal members
    public function getCurrentPage()
    {
        return $this->page;
    }

    public function setCurrentPage(IPage $page)
    {
        $this->unload();
        $this->page = $page;
    }
    
    public function setFilter(PaginationFilter $filter)
    {
        $this->unload();
        $this->iterator = new ConfigFilterIterator($this->iterator, $filter);
    }
    
    public function setPagination($skip, $limit)
    {
        $this->slice($skip, $limit);
        $this->paginationSlicer = $this->iterator;
    }

    public function getPaginationTotalCount()
    {
        $this->ensureLoaded();
        if ($this->paginationSlicer != null)
            return $this->paginationSlicer->getInnerCount();
        return $this->count();
    }

    public function hasMorePaginationPosts()
    {
        $this->ensureLoaded();
        if ($this->paginationSlicer != null)
            return $this->paginationSlicer->hadMoreItems();
        return false;
    }

    public function getNextPost()
    {
        $this->ensureLoaded();
        return $this->nextPost;
    }

    public function getPreviousPost()
    {
        $this->ensureLoaded();
        return $this->previousPost;
    }

    public function setLocked($locked = true)
    {
        $this->isLocked = $locked;
    }
    // }}}

    // {{{ Fluent-interface template members
    /**
     * @include
     * @noCall
     * @documentation Skip `n` posts.
     */
    public function skip($count)
    {
        $this->ensureUnlocked();
        $this->unload();
        $this->ensureSorter();
        $this->iterator = new SliceIterator($this->iterator, $count);
        return $this;
    }
    
    /**
     * @include
     * @noCall
     * @documentation Only return `n` posts.
     */
    public function limit($count)
    {
        $this->ensureUnlocked();
        $this->unload();
        $this->ensureSorter();
        $this->iterator = new SliceIterator($this->iterator, 0, $count);
        return $this;
    }
    
    /**
     * @include
     * @noCall
     * @documentation Like calling `skip` and `limit` (in that order).
     */
    public function slice($skip, $limit)
    {
        $this->ensureUnlocked();
        $this->unload();
        $this->ensureSorter();
        $this->iterator = new SliceIterator($this->iterator, $skip, $limit);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Apply a named filter from the page's config (similar to `posts_filter`).
     */
    public function filter($filterName)
    {
        if ($this->page == null)
            throw new PieCrustException("Can't use 'filter()' because no parent page was set for the pagination iterator.");
        
        $filterDefinition = $this->page->getConfig()->getValue($filterName);
        if ($filterDefinition == null)
            throw new PieCrustException("Couldn't find filter '{$filterName}' in the configuration header for page: {$this->page->getPath()}");

        return $this->filterClauses($filterDefinition);
    }

    /**
     * @include
     * @noCall
     * @documentation Sort posts by a page setting.
     */
    public function sort($name, $reverse = false)
    {
        $this->ensureUnlocked();
        $this->unload();

        $this->iterator = new ConfigSortIterator($this->iterator, $name, $reverse);
        $this->gotSorter = true;
        return $this;
    }

    /**
     * Magic function to trap any `in_xxx`, `has_xxx`, etc.
     */
    public function __call($name, $arguments)
    {
        if (strncmp('is_', $name, 3) === 0 or strncmp('in_', $name, 3) === 0)
        {
            if (count($arguments) > 1)
                throw new PieCrustException("Page iterator method '{$name}' only accepts one argument (".count($arguments)." provided)");
            return $this->filterPropertyValue(substr($name, 3), $arguments[0]);
        }

        if (strncmp('has_', $name, 4) === 0)
        {
            return $this->filterPropertyArray(substr($name, 4), $arguments);
        }
       
        if (strncmp('with_', $name, 5) === 0)
        {
            return $this->filterPropertyArray(substr($name, 5), $arguments);
        }

        throw new PieCrustException("Page iterator has no such method: {$name}");
    }

    protected function filterPropertyValue($name, $value)
    {
        $name = 'is_' . $name;
        $clauses = array($name => $value);
        return $this->filterClauses($clauses);
    }

    protected function filterPropertyArray($name, $values)
    {
        $name = 'has_' . $name;
        if (count($values) == 1)
        {
            $clauses = array($name => $values[0]);
        }
        else
        {
            $compound = array();
            foreach ($values as $val)
            {
                $compound[] = array($name => $val);
            }
            $clauses = array('and' => $compound);
        }
        return $this->filterClauses($clauses);
    }

    protected function filterClauses($clauses)
    {
        $this->ensureUnlocked();
        $this->unload();

        $filter = new PaginationFilter();
        $filter->addClauses($clauses);
        $this->iterator = new ConfigFilterIterator($this->iterator, $filter);

        return $this;
    }
    // }}}

    // {{{ Miscellaneous template members
    /**
     * @include
     * @noCall
     * @documentation Return the first matching post.
     */
    public function first()
    {
        $this->ensureLoaded();
        if ($this->items->count() == 0)
            return null;
        return $this->items[0];
    }
    // }}}
    
    // {{{ Shortcut template members
    public function sortBy($name, $reverse = false)
    {
        $this->pieCrust->getEnvironment()->getLog()->warning(
            "The `sortBy` template method has been renamed `sort`."
        );
        return $this->sort($name, $reverse);
    }

    public function with_tag($tag)
    {
        return $this->with_tags($tag);
    }
    // }}}
    
    // {{{ Protected members
    protected function ensureUnlocked()
    {
        if ($this->isLocked)
            throw new PieCrustException("The `paginator` object always returns the posts required for correct pagination. You can add more filtering with the `posts_filters` configuration setting, or get a custom posts list with `blog.posts`.");
    }

    protected function ensureSorter()
    {
        if ($this->gotSorter)
            return;

        $this->iterator = new DateSortIterator($this->iterator);
        $this->gotSorter = true;
    }

    protected function load()
    {
        // Run the iterator chain!
        $this->ensureSorter();
        $posts = iterator_to_array($this->iterator);

        // Find the previous and next posts, if the parent page is in there.
        if ($this->page != null)
        {
            $pageIndex = -1;
            foreach ($posts as $i => $post)
            {
                if ($post === $this->page)
                {
                    $pageIndex = $i;
                    break;
                }
            }
            if ($pageIndex >= 0)
            {
                // Get the previous and next posts.
                $prevAndNextPost = array(null, null);
                if ($pageIndex > 0)
                    $prevAndNextPost[0] = $posts[$pageIndex - 1];
                if ($pageIndex < count($posts) - 1)
                    $prevAndNextPost[1] = $posts[$pageIndex + 1];

                // Get their template data.
                $prevAndNextPostData = $this->getPostsData($prevAndNextPost);

                // Posts are sorted by reverse time, so watch out for what's
                // "previous" and what's "next"!
                $this->previousPost = $prevAndNextPostData[1];
                $this->nextPost = $prevAndNextPostData[0];
            }
        }
        
        // Get the posts data, and use that as the items we'll return.
        $items = $this->getPostsData($posts);

        return $items;
    }
    
    protected function getPostsData(array $posts)
    {
        $postsData = array();
        foreach ($posts as $post)
        {
            // This can be null, e.g. when getting the template data for
            // the next/previous posts.
            if ($post == null)
            {
                $postsData[] = null;
                continue;
            }

            $postsData[] = new PaginationData($post);
        }
        return $postsData;
    }
    // }}}
}
