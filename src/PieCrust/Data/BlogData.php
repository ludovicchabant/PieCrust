<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Page\Iteration\PageIterator;
use PieCrust\Util\PageHelper;


/**
 * The template data for a blog, listing all posts in
 * the blog along with posts by categories and tags.
 *
 * @explicitInclude
 */
class BlogData
{
    protected $page;
    protected $blogKey;

    protected $years;
    protected $months;

    protected $userData;

    public function __construct(IPage $page, $blogKey)
    {
        $this->page = $page;
        $this->blogKey = $blogKey;
    }

    // {{{ Template functions
    /**
     * @noCall
     * @include
     * @documentation The list of all posts for this blog.
     */
    public function posts()
    {
        $blogPosts = PageHelper::getPosts($this->page->getApp(), $this->blogKey);
        $posts = new PageIterator($this->page->getApp(), $this->blogKey, $blogPosts);
        $posts->setCurrentPage($this->page);
        return $posts;
    }

    /**
     * @noCall
     * @include
     * @documentation The list of categories for this blog.
     */
    public function categories()
    {
        return new PagePropertyArrayData($this->page, $this->blogKey, 'category');
    }

    /**
     * @noCall
     * @include
     * @documentation The list of tags for this blog.
     */
    public function tags()
    {
        return new PagePropertyArrayData($this->page, $this->blogKey, 'tags');
    }

    /**
     * @noCall
     * @include
     * @documentation The list of years with published posts in the blog.
     */
    public function years()
    {
        $this->ensureYears();
        return $this->years;
    }

    /**
     * @noCall
     * @include
     * @documentation The list of months with published posts in the blog.
     */
    public function months()
    {
        $this->ensureMonths();
        return $this->months;
    }
    // }}}

    // {{{ User data functions
    public function mergeUserData(array $userData)
    {
        $this->userData = $userData;
    }

    public function __isset($name)
    {
        return $this->userData != null and isset($this->userData[$name]);
    }

    public function __get($name)
    {
        if ($this->userData == null)
            return null;
        return $this->userData[$name];
    }
    // }}}

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

        // Get all the blog posts.
        $posts = PageHelper::getPosts($this->page->getApp(), $this->blogKey);

        // Sort them by month.
        $monthsInfos = array();
        $currentMonthAndYear = null;
        foreach ($posts as $post)
        {
            $timestamp = $post->getDate();
            $pageMonthAndYear = date('F Y', $timestamp);
            if (!isset($monthsInfos[$pageMonthAndYear]))
            {
                $pageYear = intval(date('Y', $timestamp));
                $pageMonth = intval(date('m', $timestamp));
                $monthsInfos[$pageMonthAndYear] = array(
                    'name' => $pageMonthAndYear,
                    'timestamp' => mktime(0, 0, 0, $pageMonth, 1, $pageYear),
                    'data_source' => array()
                );
            }
            $monthsInfos[$pageMonthAndYear]['data_source'][] = $post;
        }

        // For each month, create the data class.
        $this->months = array();
        foreach ($monthsInfos as $month => $monthInfo)
        {
            $this->months[$month] = new PageTimeData(
                $this->page,
                $this->blogKey,
                $monthInfo['name'],
                $monthInfo['timestamp'],
                $monthInfo['data_source']
            );
        }

        // Sort the months in inverse chronological order.
        uasort($this->months, array('PieCrust\Data\BlogData', 'sortByReverseTimestamp'));
    }

    public static function sortByReverseTimestamp($left, $right)
    {
        if ($left->timestamp() == $right->timestamp())
            return 0;

        return $left->timestamp() > $right->timestamp() ? -1 : 1;
    }
}

