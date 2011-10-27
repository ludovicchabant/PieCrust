<?php

require_once (dirname(__DIR__) . '/unittest_setup.php');

use PieCrust\PieCrust;
use PieCrust\Page\Page;
use PieCrust\Page\Paginator;


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
        $pc = new PieCrust(array('cache' => false, 'root' => PIECRUST_UNITTESTS_EMPTY_ROOT_DIR));
        $pc->setConfigValue('site', 'posts_per_page', 5);
        $pc->setConfigValue('site', 'date_format', 'F j, Y');
        $page = new Page($pc, 'test-page-uri', 'test-page-path', Page::TYPE_REGULAR, null, null, $pageNumber, null);
        $page->setConfigAndContents(array(), array('content' => 'Dummy page for paginator tests.'));
        $paginator = $page->getPaginator();
        $paginator->setPaginationDataSource($this->buildPaginationDataSource($pc, $postCount));
        $data = $paginator->getPaginationData();
        
        $this->assertNotNull($data);
        if ($postCount <= 5)
        {
            // All posts fit on the page.
            $this->assertNull($data['prev_page']);
            $this->assertEquals('test-page-uri', $data['this_page']);
            $this->assertNull($data['next_page']);
        }
        else if ($pageNumber <= 1)
        {
            // Lots of posts, but this is the first page.
            $this->assertNull($data['prev_page']);
            $this->assertEquals('test-page-uri', $data['this_page']);
            $this->assertEquals('test-page-uri/2', $data['next_page']);
        }
        else if ($pageNumber * 5 > $postCount)
        {
            // Lots of posts, and this is a page somewhere in the middle, or
            // the last page.
            if ($pageNumber > 2)
                $this->assertEquals('test-page-uri/' . ($pageNumber - 1), $data['prev_page']);
            else
                $this->assertEquals('test-page-uri', $data['prev_page']);
            $this->assertEquals('test-page-uri/' . $pageNumber, $data['this_page']);
            if ($pageNumber * 5 > $postCount)
                $this->assertNull($data['next_page']);
            else
                $this->assertEquals('test-page-uri/' . ($pageNumber + 1), $data['next_page']);
        }
        
        $expectedCount = $postCount;
        if ($postCount > 5)
        {
            if ($pageNumber * 5 <= $postCount)
                $expectedCount = 5;
            else
                $expectedCount = ($postCount % 5);
        }
        $this->assertEquals($expectedCount, count($data['posts']));
        for ($i = 0; $i < $expectedCount; ++$i)
        {
            $post = $data['posts'][$i];
            $fullIndex = $i + 5 * ($pageNumber - 1);
            
            $this->assertEquals('test-post-number-' . $fullIndex . '-name', $post['slug']);
            $time = mktime(0, 0, 0, ($fullIndex % 12), (($fullIndex * 3) % 28), 2006 + ($fullIndex / 6));
            $this->assertEquals(date('F j, Y', $time), $post['date']);
            $this->assertEquals('Test page ' . $fullIndex . ' contents.', $post['content']);
        }
    }
    
    protected function buildPaginationDataSource(PieCrust $pc, $postCount)
    {
        $posts = array();
        for ($i = 0; $i < $postCount; ++$i)
        {
            $year = 2006 + ($i / 6);
            $month = ($i % 12);
            $day = (($i * 3) % 28);
            $name = ('test-post-number-' . $i . '-name');
            $path = ('test-post-number-' . $i . '-path.html');
            $dummyPage = new Page($pc, $name, $path);
            $dummyPage->setConfigAndContents(array(), array('content' => ('Test page ' . $i . ' contents.')));
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
}
