<?php

require_once (dirname(__DIR__) . '/unittest_setup.php');

use PieCrust\PieCrust;
use PieCrust\IPieCrust;
use PieCrust\Page\PageConfiguration;
use PieCrust\Page\Paginator;
use PieCrust\Page\PaginationIterator;


class PaginatorTest extends PHPUnit_Framework_TestCase
{
    public function paginatorDataProvider()
    {
        return array(
            array(1, 0),
            array(1, 4),
            array(1, 5),
            array(1, 8),
            array(1, 14),
            array(2, 8),
            array(2, 14),
            array(3, 14)
        );
    }
    
    /**
     * @dataProvider paginatorDataProvider
     */
    public function testPaginator($pageNumber, $postCount)
    {
        $pc = new MockPieCrust();
        $pc->getConfig()->setValue('blog/posts_per_page', 5);
        $pc->getConfig()->setValue('blog/date_format', 'F j, Y');
        
        $page = new MockPage($pc);
        $page->uri = 'test-page-uri';
        $page->pageNumber = $pageNumber;
        
        $paginator = new Paginator($page);
        $paginator->setPaginationDataSource($this->buildPaginationDataSource($pc, $postCount));
        
        $posts = $paginator->posts();
        $this->assertNotNull($posts);
        if ($postCount <= 5)
        {
            // All posts fit on the page.
            $this->assertNull($paginator->prev_page());
            $this->assertEquals('test-page-uri', $paginator->this_page());
            $this->assertNull($paginator->next_page());
        }
        else if ($pageNumber <= 1)
        {
            // Lots of posts, but this is the first page.
            $this->assertNull($paginator->prev_page());
            $this->assertEquals('test-page-uri', $paginator->this_page());
            $this->assertEquals('test-page-uri/2', $paginator->next_page());
        }
        else if ($pageNumber * 5 > $postCount)
        {
            // Lots of posts, and this is a page somewhere in the middle, or
            // the last page.
            if ($pageNumber > 2)
                $this->assertEquals('test-page-uri/' . ($pageNumber - 1), $paginator->prev_page());
            else
                $this->assertEquals('test-page-uri', $paginator->prev_page());
            $this->assertEquals('test-page-uri/' . $pageNumber, $paginator->this_page());
            if ($pageNumber * 5 > $postCount)
                $this->assertNull($paginator->next_page());
            else
                $this->assertEquals('test-page-uri/' . ($pageNumber + 1), $paginator->next_page());
        }
        
        $expectedCount = $postCount;
        if ($postCount > 5)
        {
            if ($pageNumber * 5 <= $postCount)
                $expectedCount = 5;
            else
                $expectedCount = ($postCount % 5);
        }
        $expectedIndexes = array();
        if ($postCount > 0)
            $expectedIndexes = range(5 * ($pageNumber - 1), 5 * ($pageNumber - 1) + $expectedCount - 1);
        $this->assertExpectedPostsData($expectedIndexes, $posts);
    }
    
    public function fluentFilteringDataProvider()
    {
        return array(
            array(1, 17, null, range(0, 16)),
            array(1, 17, function ($it) { $it->skip(4); }, range(4, 16)),
            array(1, 17, function ($it) { $it->limit(3); }, range(0, 2)),
            array(1, 17, function ($it) { $it->skip(2)->limit(3); }, range(2, 4))
        );
    }
    
    /**
     * @dataProvider fluentFilteringDataProvider
     */
    public function testFluentFiltering($pageNumber, $postCount, $filterFunc, $expectedIndexes)
    {
        $pc = new MockPieCrust();
        $pc->getConfig()->setValue('site/posts_per_page', 5);
        
        $page = new MockPage($pc);
        
        $dataSource = $this->buildPaginationDataSource($pc, $postCount);
        $it = new PaginationIterator($page, $dataSource);
        
        if ($filterFunc)
            $filterFunc($it);
        $this->assertExpectedPostsData($expectedIndexes, $it);
    }
    
    protected function buildPaginationDataSource(IPieCrust $pc, $postCount)
    {
        $posts = array();
        for ($i = 0; $i < $postCount; ++$i)
        {
            $year = 2006 + ($i / 6);
            $month = ($i % 12);
            $day = (($i * 3) % 28);
            $name = ('test-post-number-' . $i . '-name');
            $path = ('test-post-number-' . $i . '-path.html');
            
            $dummyPage = new MockPage($pc);
            $dummyPage->uri = $name;
            $dummyPage->path = $path;
            $dummyPage->contents = array('content' => ('Test page ' . $i . ' contents.'));
            $posts[] = array(
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'name' => $name,
                'path' => $path,
                'page' => $dummyPage
            );
        }
        return $posts;
    }
    
    protected function assertExpectedPostsData($expectedIndexes, $actualPosts)
    {
        $this->assertEquals(count($expectedIndexes), count($actualPosts));
        foreach ($expectedIndexes as $k => $i)
        {
            $post = $actualPosts[$k];
            $this->assertEquals('test-post-number-' . $i . '-name', $post['slug']);
            $time = mktime(0, 0, 0, ($i % 12), (($i * 3) % 28), 2006 + ($i / 6));
            $this->assertEquals(date('F j, Y', $time), $post['date']);
            $this->assertEquals('Test page ' . $i . ' contents.', $post['content']);
        }
    }
}
