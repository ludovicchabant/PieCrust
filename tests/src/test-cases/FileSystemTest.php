<?php

require_once 'unittest_setup.php';

use PieCrust\IO\FileSystem;


class FileSystemTest extends PHPUnit_Framework_TestCase
{
    public function getPostFilesDataProvider()
    {
        return array(
            array('flat'),
            array('shallow'),
            array('hierarchy')
        );
    }
    
    /**
     * @dataProvider getPostFilesDataProvider
     */
    public function testGetPostFiles($fsType)
    {
        $pc = new MockPieCrust();
        $pc->setPostsDir(PIECRUST_UNITTESTS_TEST_DATA_DIR . 'posts/' . $fsType . '/');
        $pc->getConfig()->setValue('site/posts_fs', $fsType);
        
        $fs = FileSystem::create($pc);
        $this->assertNotNull($fs);
        $postFiles = $fs->getPostFiles();
        $this->assertNotNull($postFiles);
        $this->assertEquals(4, count($postFiles));
        
        // FileSystem implementations return posts in reverse-chronological order.
        $this->assertEquals('2009', $postFiles[3]['year']);
        $this->assertEquals('05', $postFiles[3]['month']);
        $this->assertEquals('16', $postFiles[3]['day']);
        $this->assertEquals('first-post', $postFiles[3]['name']);
        $this->assertTrue(is_file($postFiles[3]['path']));
        
        $this->assertEquals('2010', $postFiles[2]['year']);
        $this->assertEquals('01', $postFiles[2]['month']);
        $this->assertEquals('08', $postFiles[2]['day']);
        $this->assertEquals('second-post', $postFiles[2]['name']);
        $this->assertTrue(is_file($postFiles[2]['path']));
        
        $this->assertEquals('2010', $postFiles[1]['year']);
        $this->assertEquals('11', $postFiles[1]['month']);
        $this->assertEquals('02', $postFiles[1]['day']);
        $this->assertEquals('third-post', $postFiles[1]['name']);
        $this->assertTrue(is_file($postFiles[1]['path']));
        
        $this->assertEquals('2011', $postFiles[0]['year']);
        $this->assertEquals('09', $postFiles[0]['month']);
        $this->assertEquals('23', $postFiles[0]['day']);
        $this->assertEquals('fourth-post', $postFiles[0]['name']);
        $this->assertTrue(is_file($postFiles[0]['path']));
    }
    
    public function getPathInfoDataProvider()
    {
        return array(
            array('flat', null),
            array('flat', 'year'),
            array('flat', 'month'),
            array('flat', 'day'),
            array('shallow', null),
            array('shallow', 'year'),
            array('shallow', 'month'),
            array('shallow', 'day'),
            array('hierarchy', null),
            array('hierarchy', 'year'),
            array('hierarchy', 'month'),
            array('hierarchy', 'day'),
        );
    }
    
    /**
     * @dataProvider getPathInfoDataProvider
     */
    public function testGetPathInfo($fsType, $wildcardComponent)
    {
        $pc = new MockPieCrust();
        $pc->setPostsDir(PIECRUST_UNITTESTS_TEST_DATA_DIR . 'posts/' . $fsType . '/');
        $pc->getConfig()->setValue('site/posts_fs', $fsType);
        
        $fs = FileSystem::create($pc);
        $postFiles = $fs->getPostFiles();
        $this->assertNotNull($postFiles);
        $this->assertEquals(4, count($postFiles));
        
        $years = array('2009', '2010', '2010', '2011');
        $months = array('05', '01', '11', '09');
        $days = array('16', '08', '02', '23');
        $slugs = array('first-post', 'second-post', 'third-post', 'fourth-post');
        for ($i = 0; $i < 4; ++$i)
        {
            $groups = array('year' => $years[$i], 'month' => $months[$i], 'day' => $days[$i], 'slug' => $slugs[$i]);
            if ($wildcardComponent != null)
                unset($groups[$wildcardComponent]);
            $pathInfo = $fs->getPathInfo($groups);
            $this->assertEquals($years[$i], $pathInfo['year']);
            $this->assertEquals($months[$i], $pathInfo['month']);
            $this->assertEquals($days[$i], $pathInfo['day']);
            $this->assertEquals($slugs[$i], $pathInfo['slug']);
            $this->assertEquals(str_replace('\\', '/', $postFiles[3 - $i]['path']), str_replace('\\', '/', $pathInfo['path']));
        }
    }
}
