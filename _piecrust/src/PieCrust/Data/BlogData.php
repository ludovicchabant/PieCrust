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
    protected $posts;
    protected $categories;
    protected $tags;

    public function __construct(IPieCrust $pieCrust, $blogKey)
    {
        // Lists of posts in the given blog. The PaginationIterator
        // lazy-loads all that stuff so we don't need to be clever here.
        $blogPosts = PageHelper::getPosts($pieCrust, $blogKey);
        $this->posts = new PaginationIterator($pieCrust, $blogKey, $blogPosts);

        // Categories and tags. Same thing as above: PagePropertyArrayData wraps
        // a PaginationIterator so we can create this right away.
        $this->categories = new PagePropertyArrayData($pieCrust, $blogKey, 'category');
        $this->tags = new PagePropertyArrayData($pieCrust, $blogKey, 'tags');
    }

    // {{{ Template functions
    /**
     * @noCall
     * @documentation The list of posts for this blog.
     */
    public function posts()
    {
        return $this->posts;
    }

    /**
     * @documentation The list of categories for this blog.
     */
    public function categories()
    {
        return $this->categories;
    }

    /**
     * @documentation The list of tags for this blog.
     */
    public function tags()
    {
        return $this->tags;
    }
    // }}}
}

