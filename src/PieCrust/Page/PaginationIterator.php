<?php

namespace PieCrust\Page;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Data\PaginationData;
use PieCrust\Page\Filtering\PaginationFilter;
use PieCrust\Page\Iteration\BaseIterator;
use PieCrust\Page\Iteration\DateSortIteratorModifier;
use PieCrust\Page\Iteration\FilterIteratorModifier;
use PieCrust\Page\Iteration\LimitIteratorModifier;
use PieCrust\Page\Iteration\SkipIteratorModifier;
use PieCrust\Page\Iteration\SortIteratorModifier;
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
class PaginationIterator extends BaseIterator
{
    protected $pieCrust;
    protected $dataSource;
    protected $blogKey;
    
    protected $modifierChain;

    protected $page;
    protected $previousPost;
    protected $nextPost;
    protected $hasMorePosts;
    protected $totalPostCount;
    
    public function __construct(IPieCrust $pieCrust, $blogKey, array $dataSource)
    {
        parent::__construct();

        $this->pieCrust = $pieCrust;
        $this->dataSource = $dataSource;
        $this->blogKey = $blogKey;
        
        $this->modifierChain = array();

        $this->page = null;
        $this->previousPost = null;
        $this->nextPost = null;
        $this->hasMorePosts = false;
        $this->totalPostCount = 0;
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
        array_unshift($this->modifierChain, new FilterIteratorModifier($filter));
    }

    public function getTotalPostCount()
    {
        $this->ensureLoaded();
        return $this->totalPostCount;
    }
    
    public function hasMorePosts()
    {
        $this->ensureLoaded();
        return $this->hasMorePosts;
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
    // }}}

    // {{{ Fluent-interface template members
    /**
     * @include
     * @noCall
     * @documentation Skip `n` posts.
     */
    public function skip($count)
    {
        $this->unload();
        $this->modifierChain[] = new SkipIteratorModifier($count);
        return $this;
    }
    
    /**
     * @include
     * @noCall
     * @documentation Only return `n` posts.
     */
    public function limit($count)
    {
        $this->unload();
        $this->modifierChain[] = new LimitIteratorModifier($count);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Apply a named filter from the page's config (similar to `posts_filter`).
     */
    public function filter($filterName)
    {
        $this->unload();
        if ($this->page == null)
            throw new PieCrustException("Can't use 'filter()' because no parent page was set for the pagination iterator.");
        if (!$this->page->getConfig()->hasValue($filterName))
            throw new PieCrustException("Couldn't find filter '{$filterName}' in the configuration header for page: {$this->page->getPath()}");
        
        $filterDefinition = $this->page->getConfig()->getValue($filterName);
        $filter = new PaginationFilter();
        $filter->addClauses($filterDefinition);
        $this->modifierChain[] = new FilterIteratorModifier($filter);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Only return posts in given category.
     */
    public function in_category($category)
    {
        $this->unload();
        $filter = new PaginationFilter();
        $filter->addClauses(array('is_category' => $category));
        $this->modifierChain[] = new FilterIteratorModifier($filter);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Only return posts with given tag.
     */
    public function with_tag($tag)
    {
        $this->unload();
        $filter = new PaginationFilter();
        $filter->addClauses(array('has_tags' => $tag));
        $this->modifierChain[] = new FilterIteratorModifier($filter);
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Only return posts with given tags.
     */
    public function with_tags($tag1, $tag2 /*, $tag3, ... */)
    {
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
        $this->modifierChain[] = new FilterIteratorModifier($filter);
        return $this;
    }
    
    /**
     * @include
     * @noCall
     * @documentation Return all posts.
     */
    public function all()
    {
        $this->unload();
        $this->modifierChain = array();
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Sort posts by a page setting.
     */
    public function sortBy($name, $reverse = false)
    {
        $this->modifierChain[] = new SortIteratorModifier($name, $reverse);
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
        if (count($this->posts) == 0)
            return null;
        return $this->posts[0];
    }
    // }}}
    
    // {{{ Protected members
    protected function load()
    {
        $pieCrust = $this->pieCrust;
        $blogKey = $this->blogKey;
        $postsUrlFormat = $pieCrust->getConfig()->getValueUnchecked($blogKey . '/post_url');

        // Work with copies of our arrays so we can unload and reload.
        $dataSource = $this->dataSource;
        $modifierChain = $this->modifierChain;

        // The given data source is usually from the post FileSystem, which
        // orders posts by reverse-chronological date. The problem is that
        // it doesn't know about the time at which a post was posted because
        // at this point none of the posts have been loaded from disk.
        // We need to sort by the actual date and time of the post.
        // (this will load the configurations of all posts)
        //
        // However, we need to check for other sorting modifiers at the
        // beginning of the chain, so as not to sort posts by date for nothing
        // because they're going to be sorted some other way just after that.
        // 
        // And to be really optimal, we'll insert the sorter just before the
        // first modifier that depends on order. If there's no modifiers in the
        // chain, we'll add the sorter anyway.
        $insertSorterAt = 0;
        foreach ($modifierChain as $i => $it)
        {
            if ($it->dependsOnOrder())
            {
                $insertSorterAt = $i;
                break;
            }
            else if ($it->affectsOrder())
            {
                $insertSorterAt = -1;
                break;
            }
        }
        if ($insertSorterAt >= 0)
        {
            $sorter = new DateSortIteratorModifier();
            array_splice($modifierChain, $insertSorterAt, 0, array($sorter));
        }

        // Now run the chain on the data source.
        foreach ($modifierChain as $it)
        {
            $dataSource = $it->modify($dataSource);
        }

        // Get the final post count.
        $this->totalPostCount = count($dataSource);

        // Find the previous and next posts, if the parent page is in there.
        if ($this->page != null)
        {
            $pageIndex = -1;
            foreach ($dataSource as $i => $post)
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
                    $prevAndNextPost[0] = $dataSource[$pageIndex - 1];
                if ($pageIndex < $this->totalPostCount - 1)
                    $prevAndNextPost[1] = $dataSource[$pageIndex + 1];

                // Get their template data.
                $prevAndNextPostData = $this->getPostsData($prevAndNextPost);

                // Posts are sorted by reverse time, so watch out for what's
                // "previous" and what's "next"!
                $this->previousPost = $prevAndNextPostData[1];
                $this->nextPost = $prevAndNextPostData[0];
            }
        }
        
        // Get the posts data and see whether there's more than what we got.
        $this->posts = $this->getPostsData($dataSource);
        $this->hasMorePosts = false;
        foreach ($modifierChain as $it)
        {
            if ($it->didStripItems())
            {
                $this->hasMorePosts = true;
                break;
            }
        }
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
