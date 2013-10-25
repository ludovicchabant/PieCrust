<?php

namespace PieCrust\Tests;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\Page\Page;
use PieCrust\Page\PageConfiguration;
use PieCrust\Page\Paginator;
use PieCrust\Page\Iteration\PageIterator;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPage;
use PieCrust\Mock\MockPieCrust;


class PaginatorTest extends PieCrustTestCase
{
    public function paginatorDataProvider()
    {
        return array(
            array('', 1, 0),
            array('', 1, 4),
            array('', 1, 5),
            array('', 1, 8),
            array('', 1, 14),
            array('', 2, 8),
            array('', 2, 14),
            array('', 3, 14),
            array('blog', 1, 0),
            array('blog', 1, 4),
            array('blog', 1, 5),
            array('blog', 1, 8),
            array('blog', 1, 14),
            array('blog', 2, 8),
            array('blog', 2, 14),
            array('blog', 3, 14)
        );
    }
    
    /**
     * @dataProvider paginatorDataProvider
     */
    public function testPaginator($uri, $pageNumber, $postCount)
    {
        $siteRoot = 'http://example.org/';

        $pc = new MockPieCrust();
        $pc->getConfig()->setValue('site/root', $siteRoot);
        $pc->getConfig()->setValue('site/pretty_urls', true);
        $pc->getConfig()->setValue('blog/posts_per_page', 5);
        $pc->getConfig()->setValue('blog/date_format', 'F j, Y');
        
        $page = new MockPage($pc);
        $page->uri = $uri;
        $page->pageNumber = $pageNumber;
        
        $paginator = new Paginator($page);
        $paginator->setPaginationDataSource($this->buildPaginationDataSource($pc, $postCount));
        $this->assertNotNull($paginator->getPaginationDataSource());
        
        $posts = $paginator->posts();
        $this->assertNotNull($posts);
        if ($postCount <= 5)
        {
            // All posts fit on the page.
            $this->assertNull($paginator->prev_page_number());
            $this->assertNull($paginator->prev_page());

            $this->assertEquals(1, $paginator->this_page_number());
            $this->assertEquals($siteRoot . $uri, $paginator->this_page());

            $this->assertNull($paginator->next_page_number());
            $this->assertNull($paginator->next_page());
        }
        else if ($pageNumber <= 1)
        {
            // Lots of posts, but this is the first page.
            $this->assertNull($paginator->prev_page_number());
            $this->assertNull($paginator->prev_page());

            $this->assertEquals(1, $paginator->this_page_number());
            $this->assertEquals($siteRoot . $uri, $paginator->this_page());

            $this->assertEquals(2, $paginator->next_page_number());
            $this->assertEquals(
                $siteRoot . ($uri == '' ? '2' : ($uri . '/2')),
                $paginator->next_page()
            );
        }
        else if ($pageNumber * 5 > $postCount)
        {
            // Lots of posts, and this is a page somewhere in the middle, or
            // the last page.
            if ($pageNumber >= 2)
            {
                $this->assertEquals($pageNumber - 1, $paginator->prev_page_number());

                $prevPage = $uri == '' ? 
                    (string)($pageNumber - 1) : 
                    ($uri . '/' . ($pageNumber - 1));
                if ($pageNumber - 1 <= 1)
                    $prevPage = $uri;
                $this->assertEquals($siteRoot . $prevPage, $paginator->prev_page());
            }
            else
            {
                $this->assertNull($paginator->prev_page_number());
                $this->assertNull($paginator->prev_page());
            }

            $this->assertEquals($pageNumber, $paginator->this_page_number());
            $this->assertEquals(
                $siteRoot . ($uri == '' ? (string)$pageNumber : ($uri . '/' . $pageNumber)), 
                $paginator->this_page()
            );

            if ($pageNumber * 5 > $postCount)
            {
                $this->assertNull($paginator->next_page_number());
                $this->assertNull($paginator->next_page());
            }
            else
            {
                $this->assertEquals($pageNumber + 1, $paginator->next_page_number());
                $this->assertEquals(
                    $siteRoot . ($uri == '' ? (string)($pageNumber + 1) : ($uri . '/' . ($pageNumber + 1))),
                    $paginator->next_page()
                );
            }
        }

        $this->assertEquals($postCount, $paginator->total_post_count());

        $pageCount = (int)ceil((float)$postCount / 5.0);
        $this->assertEquals($pageCount, $paginator->total_page_count());

        if ($pageCount == 0)
            $this->assertEquals(array(), $paginator->all_page_numbers());
        else
            $this->assertEquals(range(1, $pageCount), $paginator->all_page_numbers());

        foreach (range(1, 7) as $radius)
        {
            $numberCount = $radius * 2 + 1;

            if ($pageCount == 0)
            {
                $pageNumbers = array();
            }
            else
            {
                $pageNumbers = range($pageNumber - $radius, $pageNumber + $radius);
                $pageNumbers = array_filter(
                    $pageNumbers,
                    function ($i) use ($pageCount) { return $i >= 1 && $i <= $pageCount; }
                );
                $pageNumbers = array_values($pageNumbers);
                if (count($pageNumbers) < $numberCount)
                {
                    $toAdd = $numberCount - count($pageNumbers);
                    if ($pageNumbers[0] > 1)
                    {
                        $cur = $pageNumbers[0] - 1;
                        foreach (range(1, $toAdd) as $i)
                        {
                            array_unshift($pageNumbers, $cur);
                            if (--$cur <= 1)
                                break;
                        }
                    }
                    else if ($pageNumbers[count($pageNumbers) - 1] < $pageCount)
                    {
                        $cur = $pageNumbers[count($pageNumbers) - 1] + 1;
                        foreach (range(1, $toAdd) as $i)
                        {
                            $pageNumbers[] = $cur;
                            if (++$cur >= $pageCount)
                                break;
                        }
                    }
                }
            }

            $this->assertEquals(
                $pageNumbers, 
                $paginator->all_page_numbers($radius),
                "Wrong result for {$numberCount} page numbers around page {$pageNumber} out of {$pageCount} total pages.");
        }
        
        $expectedCount = $postCount;
        if ($postCount > 5)
        {
            if ($pageNumber * 5 <= $postCount)
                $expectedCount = 5;
            else
                $expectedCount = ($postCount % 5);
        }
        $expectedIndices = array();
        if ($postCount > 0)
        {
            $allIndices = array_reverse(range(0, $postCount - 1));
            $expectedIndices = array_slice(
                $allIndices,
                5 * ($pageNumber - 1),
                $expectedCount
            );
        }
        $this->assertExpectedPostsData($expectedIndices, $posts);
    }
    
    public function fluentFilteringDataProvider()
    {
        return array(
            array(1, 17, null, array_reverse(range(0, 16))),
            array(1, 17, function ($it) { $it->skip(1); }, array_reverse(range(0, 15))),
            array(1, 17, function ($it) { $it->skip(4); }, array_reverse(range(0, 12))),
            array(1, 17, function ($it) { $it->limit(3); }, array_reverse(range(14, 16))),
            array(1, 17, function ($it) { $it->skip(2)->limit(3); }, array_reverse(range(12, 14)))
        );
    }
    
    /**
     * @dataProvider fluentFilteringDataProvider
     */
    public function testFluentFiltering($pageNumber, $postCount, $filterFunc, $expectedIndices)
    {
        $pc = new MockPieCrust();
        $pc->getConfig()->setValue('site/posts_per_page', 5);
        
        $page = new MockPage($pc);
        
        $dataSource = $this->buildPaginationDataSource($pc, $postCount);
        $it = new PageIterator($pc, 'blog', $dataSource);
        $it->setCurrentPage($page);
        
        if ($filterFunc)
            $filterFunc($it);
        $this->assertExpectedPostsData($expectedIndices, $it);
    }

    public function previousAndNextPostsDataProvider()
    {
        return array(
            array(1, 0),
            array(2, 0),
            array(2, 1),
            array(3, 1),
            array(12, 0),
            array(12, 11),
            array(12, 3),
            array(12, 4),
            array(12, 5),
            array(12, 6)
        );
    }

    /**
     * @dataProvider previousAndNextPostsDataProvider
     */
    public function testPreviousAndNextPosts($postCount, $currentPostIndex)
    {
        $pc = new MockPieCrust();
        $pc->getConfig()->setValue('blog/posts_per_page', 5);
        $pc->getConfig()->setValue('blog/date_format', 'F j, Y');

        $posts = $this->buildPaginationDataSource($pc, $postCount);
        // The pagination data source is ordered in reverse
        // chronological order. Let's reverse it to be able 
        // to index next/current/previous posts easily.
        // (the Paginator will reorder them internally)
        $posts = array_reverse($posts);
        $page = $posts[$currentPostIndex];
        
        $paginator = new Paginator($page);
        $paginator->setPaginationDataSource($posts);

        $nextPost = $paginator->next_post();
        $prevPost = $paginator->prev_post();
        if ($currentPostIndex > 0)
        {
            $this->assertEquals($posts[$currentPostIndex - 1]->getUri(), $prevPost['slug']);
        }
        else
        {
            $this->assertNull($prevPost);
        }
        if ($currentPostIndex < ($postCount - 1))
        {
            $this->assertEquals($posts[$currentPostIndex + 1]->getUri(), $nextPost['slug']);
        }
        else
        {
            $this->assertNull($nextPost);
        }
    }

    public function sortedPaginationDataProvider()
    {
        return array(
            array(
                null,
                null,
                array_reverse(range(0, 9))
            ),
            array(
                'foo',
                function ($i) { return $i; },
                range(0, 9)
            ),
            array(
                'nested/foo',
                function ($i) { return ($i + 5) % 10; },
                array(5, 6, 7, 8, 9, 0, 1, 2, 3, 4)
            ),
            array(
                'foo',
                function ($i) { return $i; },
                array_reverse(range(0, 9)),
                true
            ),
            array(
                'nested/foo',
                function ($i) { return ($i + 5) % 10; },
                array_reverse(array(5, 6, 7, 8, 9, 0, 1, 2, 3, 4)),
                true
            )
        );
    }

    /**
     * @dataProvider sortedPaginationDataProvider
     */
    public function testSortedPagination($sortByName, $configSetter, $expectedIndices, $sortByReverse = false)
    {
        $pc = new MockPieCrust();

        $posts = array();
        for ($i = 0; $i < 10; ++$i)
        {
            $year = 2012;
            $month = 1;
            $day = $i;
            $name = "test-post-number-$i-name";
            $path = "test-post-number-$i-path.html";

            $dummyPage = new MockPage($pc);
            $dummyPage->uri = $name;
            $dummyPage->path = $path;
            $dummyPage->date = mktime(0, 0, 0, $month, $day, $year);
            if ($sortByName && $configSetter)
                $dummyPage->config->setValue($sortByName, $configSetter($i));
            $dummyPage->contents = array('content' => ("Test page $i contents."));
            $posts[] = $dummyPage;
        }

        $it = new PageIterator($pc, 'blog', $posts);
        if ($sortByName)
            $it->sortBy($sortByName, $sortByReverse);

        $this->assertEquals(count($expectedIndices), count($it));
        foreach ($expectedIndices as $k => $i)
        {
            $post = $it[$k];
            $this->assertEquals("test-post-number-$i-name", $post['slug']);
            $this->assertEquals("Test page $i contents.", $post['content']);
        }
    }

    /**
     * @expectedException PieCrust\PieCrustException
     */
    public function testLockedPageIterator()
    {
        $pc = new MockPieCrust();
        $page = new MockPage($pc);
        $paginator = new Paginator($page);
        $paginator->setPaginationDataSource($this->buildPaginationDataSource($pc, 10));
        $paginator->posts()->limit(4);
    }

    public function tagPageDataProvider()
    {
        return array(
            array(
                array(),
                array(),
                array()
            ),
            array(
                array(),
                array(
                    1 => array('foo'),
                    2 => array('bar'), 
                    4 => array('bar'), 
                    7 => array('bar', 'foo'),
                    8 => array('foo', 'bar'),
                    9 => array('foo')
                ),
                array(9, 8, 7, 1)
            ),
            array(
                array(
                    'posts_filters' => array(
                        'not' => array('has_tags' => 'draft')
                    )
                ),
                array(
                    1 => array('foo'),
                    3 => array('draft'),
                    4 => array('foo', 'draft'),
                    6 => array('foo'),
                    8 => array('foo', 'draft')
                ),
                array(6, 1)
            )
        );
    }

    /**
     * @dataProvider tagPageDataProvider
     */
    public function testTagPage($pageConfig, $tagging, $expectedIndices)
    {
        $pc = new MockPieCrust();
        $page = new MockPage($pc);
        $page->pageType = IPage::TYPE_TAG;
        $page->pageKey = 'foo';
        foreach ($pageConfig as $key => $value)
        {
            $page->getConfig()->setValue($key, $value);
        }

        $posts = $this->buildPaginationDataSource($pc, 10);
        // The pagination data source is ordered in reverse
        // chronological order. Let's reverse it to be able 
        // to index posts easily.
        // (the Paginator will reorder them internally)
        $posts = array_reverse($posts);
        foreach ($tagging as $i => $tags)
        {
            $posts[$i]->getConfig()->setValue('tags', $tags);
        }

        $paginator = new Paginator($page);
        $paginator->setPaginationDataSource($posts);
        $this->assertExpectedPostsData($expectedIndices, $paginator->posts());
    }

    public function categoryPageDataProvider()
    {
        return array(
            array(
                array(),
                array(),
                array(),
                array()
            ),
            array(
                array(),
                array(
                    2 => 'foo',
                    3 => 'bar',
                    6 => 'foo',
                    8 => 'foo',
                    9 => 'bar'
                ),
                array(),
                array(8, 6, 2)
            ),
            array(
                array(
                    'posts_filters' => array(
                        'not' => array('has_tags' => 'draft')
                    )
                ),
                array(
                    2 => 'foo',
                    5 => 'foo',
                    6 => 'bar',
                    9 => 'foo'
                ),
                array(
                    5 => array('draft')
                ),
                array(9, 2)
            )
        );
    }

    /**
     * @dataProvider categoryPageDataProvider
     */
    public function testCategoryPage($pageConfig, $categorize, $tagging, $expectedIndices)
    {
        $pc = new MockPieCrust();
        $page = new MockPage($pc);
        $page->pageType = IPage::TYPE_CATEGORY;
        $page->pageKey = 'foo';
        foreach ($pageConfig as $key => $value)
        {
            $page->getConfig()->setValue($key, $value);
        }

        $posts = $this->buildPaginationDataSource($pc, 10);
        // The pagination data source is ordered in reverse
        // chronological order. Let's reverse it to be able 
        // to index posts easily.
        // (the Paginator will reorder them internally)
        $posts = array_reverse($posts);
        foreach ($categorize as $i => $category)
        {
            $posts[$i]->getConfig()->setValue('category', $category);
        }
        foreach ($tagging as $i => $tags)
        {
            $posts[$i]->getConfig()->setValue('tags', $tags);
        }

        $paginator = new Paginator($page);
        $paginator->setPaginationDataSource($posts);
        $this->assertExpectedPostsData($expectedIndices, $paginator->posts());
    }

    public function testAssetsInPagination()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none')))
            ->withPost('foo1', 1, 10, 2013, array('title' => 'Foo 1'))
            ->withAsset('_content/posts/2013-10-01_foo1-assets/thumbnail.jpg', 'Thumb 1')
            ->withPost('foo2', 2, 10, 2013, array('title' => 'Foo 2'))
            ->withAsset('_content/posts/2013-10-02_foo2-assets/thumbnail.jpg', 'Thumb 2')
            ->withPost('foo3', 3, 10, 2013, array('title' => 'Foo 3'))
            ->withAsset('_content/posts/2013-10-03_foo3-assets/thumbnail.jpg', 'Thumb 3')
            ->withPost('foo4', 4, 10, 2013, array('title' => 'Foo 4'))
            ->withAsset('_content/posts/2013-10-04_foo4-assets/thumbnail.jpg', 'Thumb 4')
            ->withPage('whatever', array(), <<<EOD
{% for p in pagination.posts %}
{{ p.title }}: {{ p.assets.thumbnail }}
{% endfor %}
EOD
            );
        $app = $fs->getApp();
        $page = Page::createFromUri($app, '/whatever', false);
        $actual = $page->getContentSegment();
        $expected = <<<EOD
Foo 4: /_content/posts/2013-10-04_foo4-assets/thumbnail.jpg
Foo 3: /_content/posts/2013-10-03_foo3-assets/thumbnail.jpg
Foo 2: /_content/posts/2013-10-02_foo2-assets/thumbnail.jpg
Foo 1: /_content/posts/2013-10-01_foo1-assets/thumbnail.jpg

EOD;
        $this->assertEquals($expected, $actual);
    }
    
    protected function buildPaginationDataSource(IPieCrust $pc, $postCount)
    {
        $posts = array();
        for ($i = 0; $i < $postCount; ++$i)
        {
            $year = 2006 + ($i / 6); // bump the year up every 6 posts.
            $month = ($i % 12);      // each post a different month.
            $day = (($i * 3) % 28);  // each post on some day between 0 and 27.
            $name = ("test-post-number-$i-name");
            $path = ("test-post-number-$i-path.html");
            
            $dummyPage = new MockPage($pc);
            $dummyPage->uri = $name;
            $dummyPage->path = $path;
            $dummyPage->date = mktime(0, 0, 0, $month, $day, $year);
            $dummyPage->contents = array('content' => ("Test page $i contents."));
            $posts[] = $dummyPage;
        }

        // Reverse the array because we want to return it in reverse
        // chronological order, like a FileSystem implementation would do.
        $posts = array_reverse($posts);
        
        return $posts;
    }
    
    protected function assertExpectedPostsData($expectedIndices, $actualPosts)
    {
        $this->assertEquals(count($expectedIndices), count($actualPosts));
        foreach ($expectedIndices as $k => $i)
        {
            $post = $actualPosts[$k];
            $this->assertEquals("test-post-number-$i-name", $post['slug']);
            $time = mktime(0, 0, 0, ($i % 12), (($i * 3) % 28), 2006 + ($i / 6));
            $this->assertEquals(date('F j, Y', $time), $post['date']);
            $this->assertEquals("Test page $i contents.", $post['content']);
        }
    }
}
