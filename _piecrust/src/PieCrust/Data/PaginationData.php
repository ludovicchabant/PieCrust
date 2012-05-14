<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\PageConfigWrapper;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PieCrustHelper;


/**
 * A class that exposes template data for a blog post
 * or page to the template engine.
 */
class PaginationData extends PageConfigWrapper
{
    public function __construct(IPage $post)
    {
        parent::__construct($post);
    }

    protected function addCustomValues()
    {
        $post = $this->page;
        $pieCrust = $this->page->getApp();
        $blogKey = $this->page->getConfig()->getValueUnchecked('blog');
        $postsDateFormat = PageHelper::getConfigValueUnchecked($this->page, 'date_format', $blogKey);

        // Add the easy values to the values array.
        $this->values['url'] = PieCrustHelper::formatUri($pieCrust, $post->getUri());
        $this->values['slug'] = $post->getUri();

        $timestamp = $post->getDate();
        if ($post->getConfig()->getValue('time'))
        {
            $timestamp = strtotime($post->getConfig()->getValue('time'), $timestamp);
        }
        $this->values['timestamp'] = $timestamp;
        $this->values['date'] = date($postsDateFormat, $timestamp);

        // Add some lazy-loading functions for stuff
        // that would load the page's contents.
        $this->lazyValues['content'] = 'loadContentAndHasMoreProperty';
        $this->lazyValues['has_more'] = 'loadContentAndHasMoreProperty';
    }

    protected function loadContentAndHasMoreProperty()
    {
        if (isset($this->values['content']) or
            isset($this->values['has_more']))
            return;

        $post = $this->page;
        $postHasMore = false;
        $postContents = $post->getContentSegment('content');
        if ($post->hasContentSegment('content.abstract'))
        {
            $postContents = $post->getContentSegment('content.abstract');
            $postHasMore = true;
        }
        $this->values['content'] = $postContents;
        $this->values['has_more'] = $postHasMore;
    }
}

