<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Page\PaginationIterator;
use PieCrust\Util\PageHelper;


/**
 * The template data for a blog, listing all posts in
 * the blog along with posts by categories and tags.
 */
class BlogData
{
    protected $page;
    protected $blogKey;

    protected $posts;
    protected $categories;
    protected $tags;

    protected $years;
    protected $months;

    public function __construct(IPage $page, $blogKey)
    {
        $this->page = $page;
        $this->blogKey = $blogKey;
    }

    // {{{ Template functions
    /**
     * @noCall
     * @documentation The list of posts for this blog. This has the same properties as `pagination.posts`.
     */
    public function posts()
    {
        $this->ensurePosts();
        return $this->posts;
    }

    /**
     * @noCall
     * @documentation The list of categories for this blog. Each category has a `name` and a list of `posts` with the same properties as `pagination.posts`.
     */
    public function categories()
    {
        $this->ensureCategories();
        return $this->categories;
    }

    /**
     * @noCall
     * @documentation The list of tags for this blog. Each tag has a `name` and a list of `posts` with the same properties as `pagination.posts`.
     */
    public function tags()
    {
        $this->ensureTags();
        return $this->tags;
    }

    /**
     * The list of years with published posts in the blog.
     */
    public function years()
    {
        $this->ensureYears();
        return $this->years;
    }

    /**
     * The list of months with published posts in the blog.
     */
    public function months()
    {
        $this->ensureMonths();
        return $this->months;
    }
    // }}}
    
    protected function ensurePosts()
    {
        if ($this->posts)
            return;

        $blogPosts = PageHelper::getPosts($this->page->getApp(), $this->blogKey);
        $this->posts = new PaginationIterator($this->page->getApp(), $this->blogKey, $blogPosts);
        $this->posts->setCurrentPage($this->page);
    }

    protected function ensureCategories()
    {
        if ($this->categories)
            return;

        $this->categories = new PagePropertyArrayData($this->page, $this->blogKey, 'category');
    }

    protected function ensureTags()
    {
        if ($this->tags)
            return;

        $this->tags = new PagePropertyArrayData($this->page, $this->blogKey, 'tags');
    }

    protected function ensureYears()
    {
        if ($this->years)
            return;

        // Get all the blog posts.
        $posts = PageHelper::getPosts($this->page->getApp(), $this->blogKey);

        // Sort them by year.
        $yearsInfos = array();
        foreach ($posts as $post)
        {
            $timestamp = $post->getDate();
            $pageYear = date('Y', $timestamp);
            if (!isset($yearsInfos[$pageYear]))
            {
                $yearsInfos[$pageYear] = array(
                    'name' => $pageYear,
                    'timestamp' => mktime(0, 0, 0, 1, 1, intval($pageYear)),
                    'data_source' => array()
                );
            }
            $yearsInfos[$pageYear]['data_source'][] = $post;
        }

        // For each year, create the data class.
        $this->years = array();
        foreach ($yearsInfos as $year => $yearInfo)
        {
            $this->years[$year] = new PageTimeData(
                $this->page,
                $this->blogKey,
                $yearInfo['name'],
                $yearInfo['timestamp'],
                $yearInfo['data_source']
            );
        }

        // Sort the years in inverse chronological order.
        krsort($this->years);
    }

    protected function ensureMonths()
    {
        if ($this->months)
            return;

        $blogPosts = PageHelper::getPosts($this->page->getApp(), $this->blogKey);
        $this->months = array();
        $currentMonthAndYear = null;
        foreach ($blogPosts as $post)
        {
            $timestamp = $post->getDate();
            $pageMonthAndYear = date('Y m', $timestamp);
            if ($currentMonthAndYear == null or $pageMonthAndYear != $currentMonthAndYear)
            {
                $pageYear = intval(date('Y', $timestamp));
                $pageMonth = intval(date('m', $timestamp));
                $this->months[$pageMonthAndYear] = array(
                    'name' => date('F Y', $timestamp),
                    'timestamp' => mktime(0, 0, 0, $pageMonth, 1, $pageYear),
                    'posts' => array()
                );
                $currentMonthAndYear = $pageMonthAndYear;
            }
            $this->months[$currentMonthAndYear]['posts'][] = new PaginationData($post);
        }
        ksort($this->months);
    }
}

