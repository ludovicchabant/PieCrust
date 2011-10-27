<?php

namespace PieCrust\Page;

use PieCrust\PieCrust;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;
use PieCrust\Page\Filtering\PaginationFilter;
use PieCrust\Util\UriBuilder;


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
    protected $pieCrust;
    protected $page;
    
    /**
     * Creates a new Paginator instance.
     */
    public function __construct(PieCrust $pieCrust, Page $page)
    {
        $this->pieCrust = $pieCrust;
        $this->page = $page;
    }
    
    /**
     * Gets the posts for this page.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function posts()
    {
        $pagination = $this->getPaginationData();
        return $pagination['posts'];
    }
    
    /**
     * Gets the previous page's URI.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function prev_page()
    {
        $pagination = $this->getPaginationData();
        return $pagination['prev_page'];
    }
    
    /**
     * Gets this page's URI.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function this_page()
    {
        $pagination = $this->getPaginationData();
        return $pagination['this_page'];
    }
    
    /**
     * Gets thenext page's URI.
     *
     * This method is meant to be called from the layouts via the template engine.
     */
    public function next_page()
    {
        $pagination = $this->getPaginationData();
        return $pagination['next_page'];
    }
    
    /**
     * Gets whether the pagination data was requested by the page.
     */
    public function wasPaginationDataAccessed()
    {
        return ($this->paginationData != null);
    }
    
    /**
     * Gets whether the current page has more pages to show.
     */
    public function hasMorePages()
    {
        return ($this->next_page() != null);
    }
    
    protected $paginationData;
    /**
     * Gets the pagination data for rendering.
     */
    public function getPaginationData()
    {
        if ($this->paginationData === null)
        {
            $this->buildPaginationData();
        }
        return $this->paginationData;
    }
    
    protected $paginationDataSource;
    /**
     * Specifies that the pagination data should be build from the given posts.
     */
    public function setPaginationDataSource(array $postInfos)
    {
        if ($this->paginationData !== null) throw new PieCrustException("The pagination data source can only be set before the pagination data is build.");
        $this->paginationDataSource = $postInfos;
    }
    
    protected function buildPaginationData()
    {
        $blogKey = $this->page->getConfigValue('blog');
        
        // If not pagination data source was provided, load up a new FileSystem
        // and get the list of posts from the disk.
        $filterPostInfos = false;
        $postInfos = $this->paginationDataSource;
        if ($postInfos === null)
        {
            $subDir = $blogKey;
            if ($blogKey == PieCrust::DEFAULT_BLOG_KEY)
                $subDir = null;
            $fs = FileSystem::create($this->pieCrust, $subDir);
            $postInfos = $fs->getPostFiles();
            $filterPostInfos = true;
        }
        
        // Now build the pagination data for each post.
        $postsData = array();
        $nextPageIndex = null;
        $previousPageIndex = ($this->page->getPageNumber() > 1) ? $this->page->getPageNumber() - 1 : null;
        if (count($postInfos) > 0)
        {
            // Load all the posts for the requested page number (page numbers start at '1').
            $postsPerPage = $this->page->getConfigValue('posts_per_page', $blogKey);
            $postsDateFormat = $this->page->getConfigValue('date_format', $blogKey);
            $postsFilter = $this->getPaginationFilter();
            
            $hasMorePages = false;
            $postInfosWithPages = $this->getRelevantPostInfosWithPages($postInfos, $postsFilter, $postsPerPage, $hasMorePages);
            foreach ($postInfosWithPages as $postInfo)
            {
                // Create the post with all the stuff we already know.
                $post = $postInfo['page'];
                $post->setAssetUrlBaseRemap($this->page->getAssetUrlBaseRemap());
                $post->setDate($postInfo);

                // Build the pagination data entry for this post.
                $postData = $post->getConfig();
                $postData['url'] = $this->pieCrust->formatUri($post->getUri());
                $postData['slug'] = $post->getUri();
                
                $timestamp = $post->getDate();
                if ($post->getConfigValue('time')) $timestamp = strtotime($post->getConfigValue('time'), $timestamp);
                $postData['timestamp'] = $timestamp;
                $postData['date'] = date($postsDateFormat, $post->getDate());
                
                $postHasMore = true;
                $postContents = $post->getContentSegment('content.abstract');
                if ($postContents == null)
                {
                    $postHasMore = false;
                    $postContents = $post->getContentSegment('content');
                }
                $postData['content'] = $postContents;
                $postData['has_more'] = $postHasMore;
                
                $postsData[] = $postData;
            }
            
            if ($hasMorePages and !($this->page->getConfigValue('single_page')))
            {
                // There's another page following this one.
                $nextPageIndex = $this->page->getPageNumber() + 1;
            }
        }
        
        // Figure out clean URIs for previous/current/next pages.
        $previousPageUri = null;
        if ($previousPageIndex != null)
        {
            if ($previousPageIndex == 1)
                $previousPageUri = $this->page->getUri();
            else
                $previousPageUri = $this->page->getUri() . '/' . $previousPageIndex;
        }
        
        $thisPageUri = $this->page->getUri();
        if ($this->page->getPageNumber() > 1)
        {
            $thisPageUri .= '/' . $this->page->getPageNumber();
        }
        
        $nextPageUri = null;
        if ($nextPageIndex != null)
        {
            $nextPageUri = $this->page->getUri() . '/' . $nextPageIndex;
        }
        
        // That's it!
        $this->paginationData = array(
                                'posts' => $postsData,
                                'prev_page' => $previousPageUri,
                                'this_page' => $thisPageUri,
                                'next_page' => $nextPageUri
                                );
    }
    
    protected function getRelevantPostInfosWithPages(array $postInfos, PaginationFilter $postsFilter, $postsPerPage, &$hasMorePages)
    {
        $hasMorePages = false;
        $offset = ($this->page->getPageNumber() - 1) * $postsPerPage;
        $upperLimit = min($offset + $postsPerPage, count($postInfos));
        $blogKey = $this->page->getConfigValue('blog');
        $postsUrlFormat = $this->pieCrust->getConfigValueUnchecked($blogKey, 'post_url');
        
        if ($postsFilter->hasClauses())
        {
            // We have some filtering clause: that's tricky because we
            // need to filter posts using those clauses from the start to
            // know what offset to start from. This is not very efficient and
            // at this point the user might as well bake his website but hey,
            // this can still be useful for debugging.
            $filteredPostInfos = array();
            foreach ($postInfos as $postInfo)
            {
                if (!isset($postInfo['page']))
                {
                    $postInfo['page'] = PageRepository::getOrCreatePage(
                        $this->pieCrust,
                        UriBuilder::buildPostUri($postsUrlFormat, $postInfo), 
                        $postInfo['path'],
                        Page::TYPE_POST,
                        $blogKey);
                }
                
                if ($postsFilter->postMatches($postInfo['page']))
                {
                    $filteredPostInfos[] = $postInfo;
                    
                    // Exit if we more than enough posts.
                    // (the extra post is to make sure there is a next page)
                    if (count($filteredPostInfos) >= ($offset + $postsPerPage + 1))
                    {
                        $hasMorePages = true;
                        break;
                    }
                }
            }
            
            // Now get the slice of the filtered post infos that is relevant
            // for the current page number.
            $relevantPostInfos = array_slice($filteredPostInfos, $offset, $upperLimit - $offset);
            return $relevantPostInfos;
        }
        else
        {
            // This is a normal page, or a situation where we don't do any filtering.
            // That's easy, we just return the portion of the posts-infos array that
            // is relevant to the current page. We just need to add the built page objects.
            $relevantSlice = array_slice($postInfos, $offset, $upperLimit - $offset);
            
            $relevantPostInfos = array();
            foreach ($relevantSlice as $postInfo)
            {
                if (!isset($postInfo['page']))
                {
                    $postInfo['page'] = PageRepository::getOrCreatePage(
                        $this->pieCrust,
                        UriBuilder::buildPostUri($postsUrlFormat, $postInfo), 
                        $postInfo['path'],
                        Page::TYPE_POST,
                        $blogKey);
                }
                
                $relevantPostInfos[] = $postInfo;
            }
            $hasMorePages = (count($postInfos) > ($offset + $postsPerPage));
            return $relevantPostInfos;
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
        $filterInfo = $this->page->getConfigValue('posts_filters');
        if ($filterInfo != null)
            $filter->addClauses($filterInfo);
        
        return $filter;
    }
}
