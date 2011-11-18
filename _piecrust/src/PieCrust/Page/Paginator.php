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
     */
    public function posts()
    {
        $this->ensurePaginationData();
        return $this->postsIterator;
    }
    
    /**
     * Gets the previous page's URI.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function prev_page()
    {
        $previousPageIndex = ($this->page->getPageNumber() > 1) ? $this->page->getPageNumber() - 1 : null;
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
     * Gets thenext page's URI.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function next_page()
    {
        $this->ensurePaginationData();
        if ($this->postsIterator->hasMorePosts() and !($this->page->getConfig()->getValue('single_page')))
        {
            $nextPageIndex = $this->page->getPageNumber() + 1;
            return $this->page->getUri() . '/' . $nextPageIndex;
        }
        return null;
    }
    
    /**
     * Resets the pagination data, as if it had never been accessed.
     */
    public function resetPaginationData()
    {
        $this->postsIterator = null;
    }
    
    /**
     * Gets whether the pagination data was requested by the page.
     */
    public function wasPaginationDataAccessed()
    {
        return ($this->postsIterator != null);
    }
    
    /**
     * Gets whether the current page has more pages to show.
     */
    public function hasMorePages()
    {
        return ($this->next_page() != null);
    }
    
    protected $paginationDataSource;
    /**
     * Specifies that the pagination data should be build from the given posts.
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
            $subDir = $blogKey;
            if ($blogKey == PieCrustDefaults::DEFAULT_BLOG_KEY)
                $subDir = null;
            $fs = FileSystem::create($this->page->getApp(), $subDir);
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
