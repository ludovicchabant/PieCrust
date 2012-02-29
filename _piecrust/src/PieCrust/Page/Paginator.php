<?php

namespace PieCrust\Page;

use PieCrust\IPage;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Page\Filtering\PaginationFilter;
use PieCrust\Util\PageHelper;


/**
 * The pagination manager for a page split into sub-pages.
 *
 * Pages that display a large number of posts may be split into
 * several sub-pages. The Paginator class handles figuring out what
 * posts to include for the current page number.
 *
 */
class Paginator
{
    protected $page;
    protected $postsIterator;
    
    /**
     * Creates a new Paginator instance.
     *
     * @ignore
     */
    public function __construct(IPage $page)
    {
        $this->page = $page;
        $this->postsIterator = null;
    }
    
    /**
     * Gets the posts for this page.
     *
     * This method is meant to be called from the layouts via the template engine.
     *
     */
    public function posts()
    {
        $this->ensurePaginationData();
        return $this->postsIterator;
    }
 
    /**
     * Gets the maximum number of posts to be displayed on the page.
     */
    public function posts_per_page()
    {
        $blogKey = $this->page->getConfig()->getValue('blog');
        return PageHelper::getConfigValue($this->page, 'posts_per_page', $blogKey);
    }
 
    /**
     * Gets the actual number of posts on the page.
     */
    public function posts_this_page()
    {
        $this->ensurePaginationData();
        return $this->postsIterator->count();
    }

    /**
     * Gets the previous page's page number.
     */
    public function prev_page_number()
    {
        return ($this->page->getPageNumber() > 1) ? $this->page->getPageNumber() - 1 : null;
    }
    
    /**
     * Gets this page's page number.
     */
    public function this_page_number()
    {
        return $this->page->getPageNumber();
    }
 
    /**
     * Gets the next page's page number.
     */
    public function next_page_number()
    {
        $this->ensurePaginationData();
        if ($this->postsIterator->hasMorePosts() and !($this->page->getConfig()->getValue('single_page')))
        {
            return $this->page->getPageNumber() + 1;
        }
        return null;
    }

    /**
     * Gets the previous page's URI.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function prev_page()
    {
        $previousPageIndex = $this->prev_page_number();
        $previousPageUri = null;
        if ($previousPageIndex != null)
        {
            if ($previousPageIndex == 1)
                $previousPageUri = $this->page->getUri();
            else
                $previousPageUri = $this->page->getUri() . '/' . $previousPageIndex;
        }
        return $previousPageUri;
    }
    
    /**
     * Gets this page's URI.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function this_page()
    {
        $thisPageUri = $this->page->getUri();
        if ($this->page->getPageNumber() > 1)
        {
            $thisPageUri .= '/' . $this->page->getPageNumber();
        }
        return $thisPageUri;
    }
   
    /**
     * Gets the next page's URI.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function next_page()
    {
        $nextPageIndex = $this->next_page_number();
        if ($nextPageIndex != null)
        {
            return $this->page->getUri() . '/' . $nextPageIndex;
        }
        return null;
    }

    /**
     * Gets the total number of posts.
     */
    public function total_post_count()
    {
        $this->ensurePaginationData();
        return $this->postsIterator->getTotalPostCount();
    }

    /**
     * Gets the total number of pages.
     */
    public function total_page_count()
    {
        if ($this->page->getConfig()->getValue('single_page'))
            return 1;

        $totalPostCount = $this->total_post_count(); 
        $postsPerPage = $this->posts_per_page();
        if (is_int($postsPerPage) && $postsPerPage > 0)
            return ceil($totalPostCount / $postsPerPage);
        return $totalPostCount;
    }

    protected $dataSource;
    /**
     * Gets the list of posts to use for the pagination.
     * If `null`, it will use all of the posts in the website.
     *
     * @ignore
     */
    public function getPaginationDataSource()
    {
        return $this->dataSource;
    }

    /**
     * Sets the list of posts to use for the pagination.
     *
     * @ignore
     */
    public function setPaginationDataSource(array $posts)
    {
        if ($this->postsIterator != null)
            throw new PieCrustException("Can't set the pagination data source after the pagination data has been loaded.");
        $this->dataSource = $posts;
    }
    
    /**
     * Resets the pagination data, as if it had never been accessed.
     *
     * @ignore
     */
    public function resetPaginationData()
    {
        $this->postsIterator = null;
    }
    
    /**
     * Gets whether the pagination data was requested by the page.
     *
     * @ignore
     */
    public function wasPaginationDataAccessed()
    {
        return ($this->postsIterator != null);
    }
    
    /**
     * Gets whether the current page has more pages to show.
     *
     * @ignore
     */
    public function hasMorePages()
    {
        return ($this->next_page() != null);
    }
    
    protected function ensurePaginationData()
    {
        if ($this->postsIterator != null)
            return;

        // Get the post infos.
        $posts = $this->dataSource;
        if ($posts === null)
        {
            $blogKey = $this->page->getConfig()->getValue('blog');
            $posts = PageHelper::getPosts($this->page->getApp(), $blogKey);
        }
        
        // Create the pagination iterator.
        $this->postsIterator = new PaginationIterator($this->page, $posts);
        // Add the filters for the current page.
        $postsFilter = $this->getPaginationFilter();
        $this->postsIterator->setFilter($postsFilter);
        // If the `posts_per_page` setting is valid, paginate accordingly.
        $postsPerPage = $this->posts_per_page();
        if (is_int($postsPerPage) && $postsPerPage > 0)
        {
            $this->postsIterator->limit($postsPerPage);
            $offset = ($this->page->getPageNumber() - 1) * $postsPerPage;
            $this->postsIterator->skip($offset);
        }
    }
    
    protected function getPaginationFilter()
    {
        $filter = new PaginationFilter();
        
        // If the current page is a tag/category page, add filtering
        // for that.
        $filter->addPageClauses($this->page);
        
        // Add custom filtering clauses specified by the user in the
        // page configuration header.
        $filterInfo = $this->page->getConfig()->getValue('posts_filters');
        if ($filterInfo != null)
            $filter->addClauses($filterInfo);
        
        return $filter;
    }
}
