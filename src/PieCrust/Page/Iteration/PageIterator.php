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
        if ($this->page == null)
            throw new PieCrustException("The current pagination page must be set before the pagination limits can be specified.");

        $this->slice($skip, $limit);
        $this->paginationSlicer = $this->iterator;
        $this->paginationSlicer->setCurrentPage($this->page);
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
        $this->ensureUnlocked();
        $this->unload();

        if ($this->page == null)
            throw new PieCrustException("Can't use 'filter()' because no parent page was set for the pagination iterator.");
        
        $filterDefinition = $this->page->getConfig()->getValue($filterName);
        if ($filterDefinition == null)
            throw new PieCrustException("Couldn't find filter '{$filterName}' in the configuration header for page: {$this->page->getPath()}");

        $filter = new PaginationFilter();
        $filter->addClauses($filterDefinition);
        $this->iterator = new ConfigFilterIterator($this->iterator, $filter);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Only return posts in given category.
     */
    public function in_category($category)
    {
        $this->ensureUnlocked();
        $this->unload();

        $filter = new PaginationFilter();
        $filter->addClauses(array('is_category' => $category));
        $this->iterator = new ConfigFilterIterator($this->iterator, $filter);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Only return posts with given tag.
     */
    public function with_tag($tag)
    {
        $this->ensureUnlocked();
        $this->unload();

        $filter = new PaginationFilter();
        $filter->addClauses(array('has_tags' => $tag));
        $this->iterator = new ConfigFilterIterator($this->iterator, $filter);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Only return posts with given tags.
     */
    public function with_tags($tag1, $tag2 /*, $tag3, ... */)
    {
        $this->ensureUnlocked();
        $this->unload();

        $tagClauses = array();
        $argCount = func_num_args();
        for ($i = 0; $i < $argCount; ++$i)
        {
            $tag = func_get_arg($i);
            $tagClauses['has_tags'] = $tag;
        }

        $filter = new PaginationFilter();
        $filter->addClauses(array('and' => $tagClauses));
        $this->iterator = new ConfigFilterIterator($this->iterator, $filter);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Sort posts by a page setting.
     */
    public function sortBy($name, $reverse = false)
    {
        $this->ensureUnlocked();
        $this->unload();

        $this->iterator = new ConfigSortIterator($this->iterator, $name, $reverse);
        $this->gotSorter = true;
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

        // Get the previous and next posts, if the parent page is in there,
        // and store their template data.
        if ($this->page != null && $this->paginationSlicer != null)
        {
            $prevAndNextPost = array(
                $this->paginationSlicer->getPreviousPage(),
                $this->paginationSlicer->getNextPage()
            );
            $prevAndNextPostData = $this->getPostsData($prevAndNextPost);
            $this->previousPost = $prevAndNextPostData[0];
            $this->nextPost = $prevAndNextPostData[1];
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
