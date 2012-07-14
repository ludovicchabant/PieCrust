<?php

namespace PieCrust\Page;

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
class PaginationIterator implements \Iterator, \ArrayAccess, \Countable
{
    protected $pieCrust;
    protected $dataSource;
    protected $blogKey;
    
    protected $filter;
    protected $skip;
    protected $limit;
    protected $sortByName;
    protected $sortByReverse;
    
    protected $posts;
    protected $hasMorePosts;
    protected $totalPostCount;

    protected $page;
    protected $previousPost;
    protected $nextPost;
    
    public function __construct(IPieCrust $pieCrust, $blogKey, array $dataSource)
    {
        $this->pieCrust = $pieCrust;
        $this->dataSource = $dataSource;
        $this->blogKey = $blogKey;
        
        $this->filter = null;
        $this->skip = 0;
        $this->limit = -1;
        $this->sortByName = null;
        $this->sortByReverse = false;
        
        $this->posts = null;
        $this->hasMorePosts = false;
        $this->totalPostCount = 0;

        $this->page = null;
        $this->previousPost = null;
        $this->nextPost = null;
    }

    public function setCurrentPage(IPage $page)
    {
        $this->unload();
        $this->page = $page;
    }
    
    public function setFilter(PaginationFilter $filter)
    {
        $this->unload();
        $this->filter = $filter;
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
    
    // {{{ Fluent-interface template members
    /**
     * @include
     * @noCall
     * @documentation Skip `n` posts.
     */
    public function skip($count)
    {
        $this->unload();
        $this->skip = $count;
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
        $this->limit = $count;
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
            throw new PieCrustException("Couldn't find filter '{$filterName}' in the page's configuration header.");
        
        $filterDefinition = $this->page->getConfig()->getValue($filterName);
        $this->filter = new PaginationFilter();
        $this->filter->addClauses($filterDefinition);
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
        $this->filter = new PaginationFilter();
        $this->filter->addClauses(array('is_category' => $category));
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
        $this->filter = new PaginationFilter();
        $this->filter->addClauses(array('has_tags' => $tag));
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

        $this->filter = new PaginationFilter();
        $this->filter->addClauses(array('and' => $tagClauses));
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
        $this->filter = null;
        $this->skip = 0;
        $this->limit = -1;
        return $this;
    }

    /**
     * @include
     * @noCall
     * @documentation Sort posts by a page setting.
     */
    public function sortBy($name, $reverse = false)
    {
        $this->sortByName = $name;
        $this->sortByReverse = $reverse;
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
    
    // {{{ Countable members
    /**
     * @include
     * @noCall
     * @documentation Return the number of matching posts.
     */
    public function count()
    {
        $this->ensureLoaded();
        return count($this->posts);
    }
    // }}}
    
    // {{{ Iterator members
    public function current()
    {
        $this->ensureLoaded();
        return current($this->posts);
    }
    
    public function key()
    {
        $this->ensureLoaded();
        return key($this->posts);
    }
    
    public function next()
    {
        $this->ensureLoaded();
        next($this->posts);
    }
    
    public function rewind()
    {
        $this->unload();
        $this->ensureLoaded();
        reset($this->posts);
    }
    
    public function valid()
    {
        if (!$this->isLoaded())
            return false;
        $this->ensureLoaded();
        return (key($this->posts) !== null);
    }
    // }}}
    
    // {{{ ArrayAccess members
    public function offsetExists($offset)
    {
        if (!is_int($offset))
            return false;
        
        $this->ensureLoaded();
        return isset($this->posts[$offset]);
    }
    
    public function offsetGet($offset)
    {
        if (!is_int($offset))
           throw new OutOfRangeException();
            
        $this->ensureLoaded();
        return $this->posts[$offset];
    }
    
    public function offsetSet($offset, $value)
    {
        throw new PieCrustException("The pagination is read-only.");
    }
    
    public function offsetUnset($offset)
    {
        throw new PieCrustException("The pagination is read-only.");
    }
    // }}}
    
    // {{{ Protected members
    protected function isLoaded()
    {
        return ($this->posts != null);
    }

    protected function unload()
    {
        $this->posts = null;
    }

    protected function ensureLoaded()
    {
        if ($this->posts != null)
            return;
        
        $pieCrust = $this->pieCrust;
        $blogKey = $this->blogKey;
        $postsUrlFormat = $pieCrust->getConfig()->getValueUnchecked($blogKey.'/post_url');

        // If we have any filter, apply it to the pagination data source.
        if ($this->filter != null and $this->filter->hasClauses())
        {
            $actualDataSource = array();
            foreach ($this->dataSource as $post)
            {
                if ($this->filter->postMatches($post))
                {
                    $actualDataSource[] = $post;
                }
            }
        }
        else
        {
            $actualDataSource = $this->dataSource;
        }
        $this->totalPostCount = count($actualDataSource);

        if (!$this->sortByName)
        {
            // The given data source is usually from the post FileSystem, which
            // orders posts by reverse-chronological date. The problem is that
            // it doesn't know about the time at which a post was posted because
            // at this point none of the posts have been loaded from disk.
            // We need to sort by the actual date and time of the post.
            if (false === usort($actualDataSource, array("\PieCrust\Page\PaginationIterator", "sortByReverseTimestamp")))
                throw new PieCrustException("Error while sorting posts by timestamp.");
        }
        else
        {
            // Sort by some arbitrary setting.
            if (false === usort($actualDataSource, array($this, "sortByCustom")))
                throw new PieCrustException("Error while sorting posts with the specified setting: {$this->sortByName}");
        }

        // Find the previous and next posts, if the parent page is in there.
        if ($this->page != null)
        {
            $pageIndex = -1;
            foreach ($actualDataSource as $i => $post)
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
                    $prevAndNextPost[0] = $actualDataSource[$pageIndex - 1];
                if ($pageIndex < $this->totalPostCount - 1)
                    $prevAndNextPost[1] = $actualDataSource[$pageIndex + 1];

                // Get their template data.
                $prevAndNextPostData = $this->getPostsData($prevAndNextPost);

                // Posts are sorted by reverse time, so watch out for what's
                // "previous" and what's "next"!
                $this->previousPost = $prevAndNextPostData[1];
                $this->nextPost = $prevAndNextPostData[0];
            }
        }

        // Now honour the skip and limit clauses.
        $upperLimit = count($this->dataSource);
        if ($this->limit > 0)
            $upperLimit = min($this->skip + $this->limit, $upperLimit);
        $actualDataSource = array_slice($actualDataSource, $this->skip, $upperLimit - $this->skip);
        
        // Get the posts data and see whether there's more than this slice.
        $this->posts = $this->getPostsData($actualDataSource);
        if ($this->limit > 0)
        {
            $this->hasMorePosts = ($this->totalPostCount > ($this->skip + $this->limit));
        }
        else
        {
            $this->hasMorePosts = false;
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

    protected function sortByCustom($post1, $post2)
    {
        $value1 = $post1->getConfig()->getValue($this->sortByName);
        $value2 = $post2->getConfig()->getValue($this->sortByName);
        
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

    protected static function sortByReverseTimestamp($post1, $post2)
    {
        $timestamp1 = $post1->getDate();
        if ($post1->getConfig()->getValue('time'))
            $timestamp1 = strtotime($post1->getConfig()->getValue('time'), $timestamp1);

        $timestamp2 = $post2->getDate();
        if ($post2->getConfig()->getValue('time'))
            $timestamp2 = strtotime($post2->getConfig()->getValue('time'), $timestamp2);

        if ($timestamp1 == $timestamp2)
            return 0;
        if ($timestamp1 < $timestamp2)
            return 1;
        return -1;
    }
    // }}}
}
