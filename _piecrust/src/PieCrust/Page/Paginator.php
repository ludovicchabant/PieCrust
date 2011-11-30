<?php

namespace PieCrust\Page;

use PieCrust\IPage;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;
use PieCrust\Page\Filtering\PaginationFilter;
use PieCrust\Util\UriBuilder;
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
        return ceil($totalPostCount / $postsPerPage);
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
    
    protected $paginationDataSource;
    /**
     * Specifies that the pagination data should be build from the given posts.
     *
     * @ignore
     */
    public function setPaginationDataSource(array $postInfos)
    {
        if ($this->postsIterator != null)
            throw new PieCrustException("The pagination data source can only be set before the pagination data is built.");
        $this->paginationDataSource = $postInfos;
    }
    
    protected function ensurePaginationData()
    {
        if ($this->postsIterator != null)
            return;
        
        $blogKey = $this->page->getConfig()->getValue('blog');
        
        // If not pagination data source was provided, load up a new FileSystem
        // and get the list of posts from the disk.
        $postInfos = $this->paginationDataSource;
        if ($postInfos === null)
        {
            $fs = FileSystem::create($this->page->getApp(), $blogKey);
            $postInfos = $fs->getPostFiles();
        }
        
        // Create the pagination iterator.
        $postsPerPage = PageHelper::getConfigValue($this->page, 'posts_per_page', $blogKey);
        $postsFilter = $this->getPaginationFilter();
        $offset = ($this->page->getPageNumber() - 1) * $postsPerPage;
        
        $this->postsIterator = new PaginationIterator($this->page, $postInfos);
        $this->postsIterator->setFilter($postsFilter);
        $this->postsIterator->limit($postsPerPage);
        $this->postsIterator->skip($offset);
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
