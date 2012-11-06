<?php

use PieCrust\IPieCrust;
use PieCrust\Page\PageConfiguration;
use PieCrust\Page\Paginator;
use PieCrust\Page\PaginationIterator;
use PieCrust\Mock\MockPage;
use PieCrust\Mock\MockPieCrust;


class PaginatorTest extends PHPUnit_Framework_TestCase
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
        $it = new PaginationIterator($pc, 'blog', $dataSource);
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
        $pc->getConfig()->setValue('blog/posts_per_page', 20);
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

        $it = new PaginationIterator($pc, 'blog', $posts);
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
