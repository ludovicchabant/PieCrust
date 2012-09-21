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
        $this->values['timestamp'] = $post->getDate(); //TODO: do we need to move this to the lazy-loaded values?
        $this->values['date'] = date($postsDateFormat, $post->getDate());

        // Add some lazy-loading functions for stuff
        // that would load the page's contents.
        $this->lazyValues[self::WILDCARD] = 'loadContent';
    }

    protected function loadContent()
    {
        $post = $this->page;
        foreach ($this->page->getContentSegments() as $key => $segment)
        {
            $this->values[$key] = $segment;
        }

        $postHasMore = false;
        if ($post->hasContentSegment('content.abstract'))
        {
            $this->values['content'] = $post->getContentSegment('content.abstract');
            $postHasMore = true;
        }
        $this->values['has_more'] = $postHasMore;
    }
}

