<?php

namespace PieCrust\Data;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Util\PageConfigWrapper;
use PieCrust\Util\PageHelper;
use PieCrust\Util\PieCrustHelper;


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

        $this->values['url'] = PieCrustHelper::formatUri($pieCrust, $post->getUri());
        $this->values['slug'] = $post->getUri();

        $timestamp = $post->getDate();
        if ($post->getConfig()->getValue('time'))
        {
            $timestamp = strtotime($post->getConfig()->getValue('time'), $timestamp);
        }
        $this->values['timestamp'] = $timestamp;
        $this->values['date'] = date($postsDateFormat, $timestamp);

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

