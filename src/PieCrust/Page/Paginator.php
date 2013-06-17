<?php

namespace PieCrust\Page;

use PieCrust\IPage;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\Page\Filtering\PaginationFilter;
use PieCrust\Page\Iteration\PageIterator;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PieCrustHelper;


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
    
    // {{{ Template members
    /**
     * Gets the posts for this page.
     *
     * @noCall
     * @documentation The list of posts for this page.
     */
    public function posts()
    {
        $this->ensurePaginationData();
        return $this->postsIterator;
    }

    /**
     * Gets whether there are any posts for this page.
     *
     * @noCall
     * @documentation Whether there are any posts for this page.
     */
    public function has_posts()
    {
        return $this->posts_this_page() > 0;
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
        if (PageHelper::isPost($this->page) or
            $this->page->getConfig()->getValue('single_page'))
            return null;

        $this->ensurePaginationData();
        if ($this->postsIterator->hasMorePaginationPosts())
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
        if ($previousPageIndex != null)
        {
            return $this->getSubPageUri($previousPageIndex);
        }
        return null;
    }
    
    /**
     * Gets this page's URI.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function this_page()
    {
        return $this->getSubPageUri($this->page->getPageNumber());
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
            return $this->getSubPageUri($nextPageIndex);
        }
        return null;
    }

    /**
     * Gets the total number of posts.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function total_post_count()
    {
        $this->ensurePaginationData();
        return $this->postsIterator->getPaginationTotalCount();
    }

    /**
     * Gets the total number of pages.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function total_page_count()
    {
        if (PageHelper::isPost($this->page) or
            $this->page->getConfig()->getValue('single_page'))
            return 1;

        $totalPostCount = $this->total_post_count(); 
        $postsPerPage = $this->posts_per_page();
        if (is_int($postsPerPage) && $postsPerPage > 0)
            return ceil($totalPostCount / $postsPerPage);
        return $totalPostCount;
    }

    /**
     * Gets all the page numbers.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function all_page_numbers($radius = false)
    {
        $totalPageCount = $this->total_page_count();

        if ($totalPageCount == 0)
            return array();

        if (!$radius or $totalPageCount <= (2 * (int)$radius + 1) or $this->page == null)
            return range(1, $totalPageCount);

        $radius = (int)$radius;
        $firstNumber = $this->page->getPageNumber() - $radius;
        $lastNumber = $this->page->getPageNumber() + $radius;
        if ($firstNumber <= 0)
        {
            $lastNumber += (1 - $firstNumber);
            $firstNumber = 1;
        }
        if ($lastNumber > $totalPageCount)
        {
            $firstNumber -= ($lastNumber - $totalPageCount);
            $lastNumber = $totalPageCount;
        }
        $firstNumber = max(1, $firstNumber);
        $lastNumber = min ($totalPageCount, $lastNumber);
        return range($firstNumber, $lastNumber);
    }

    /**
     * Get the link to a given page.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function page($index)
    {
        return $this->getSubPageUri($index);
    }

    /**
     * Gets the post coming after the current page, 
     * if it is a post, and it's not the last one.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function next_post()
    {
        $this->ensurePaginationData();
        return $this->postsIterator->getNextPost();
    }

    /**
     * Gets the post coming before the current page,
     * if it is a post, and it's not the first one.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function prev_post()
    {
        $this->ensurePaginationData();
        return $this->postsIterator->getPreviousPost();
    }
    // }}}

    // {{{ Pagination manipulation
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
    // }}}
    
    protected function ensurePaginationData()
    {
        if ($this->postsIterator != null)
            return;

        // Get the post infos.
        $posts = $this->dataSource;
        $blogKey = $this->page->getConfig()->getValue('blog');
        if ($posts === null)
        {
            $posts = PageHelper::getPosts($this->page->getApp(), $blogKey);
        }
        
        // Create the pagination iterator.
        $this->postsIterator = new PageIterator($this->page->getApp(), $blogKey, $posts);
        // Set our current page.
        $this->postsIterator->setCurrentPage($this->page);
        // Add the filters for the current page.
        $postsFilter = $this->getPaginationFilter();
        if ($postsFilter->hasClauses())
            $this->postsIterator->setFilter($postsFilter);
        // If the `posts_per_page` setting is valid, paginate accordingly.
        $postsPerPage = $this->posts_per_page();
        if (is_int($postsPerPage) && $postsPerPage > 0)
        {
            // Limit to posts that should be on this page.
            $offset = ($this->page->getPageNumber() - 1) * $postsPerPage;
            $this->postsIterator->setPagination($offset, $postsPerPage);
        }
        $this->postsIterator->setLocked();
    }
    
    protected function getPaginationFilter()
    {
        $filter = new PaginationFilter();
        $filterInfo = $this->page->getConfig()->getValue('posts_filters');
        if ($filterInfo == 'none' or $filterInfo == 'nil' or $filterInfo == '')
            $filterInfo = null;
        
        if (PageHelper::isTag($this->page) or PageHelper::isCategory($this->page))
        {
            // If the current page is a tag/category page, add filtering
            // for that.
            $filter->addPageClauses($this->page, $filterInfo);
        }
        else if ($filterInfo != null)
        {
            // Add custom filtering clauses specified by the user in the
            // page configuration header.
            $filter->addClauses($filterInfo);
        }
        return $filter;
    }

    public function getSubPageUri($index)
    {
        $uri = $this->page->getUri();
        if ($index > 1)
        {
            if ($uri != '')
                $uri .= '/';
            $uri .= $index;
        }
        return PieCrustHelper::formatUri($this->page->getApp(), $uri);
    }
}
