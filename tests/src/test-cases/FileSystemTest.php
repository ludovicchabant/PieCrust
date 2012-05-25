<?php

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
        $this->assertEquals(6, count($postFiles));
        
        // FileSystem implementations return posts in reverse-chronological order.
        $postFile = $postFiles[5];
        $this->assertEquals('2009', $postFile['year']);
        $this->assertEquals('05', $postFile['month']);
        $this->assertEquals('16', $postFile['day']);
        $this->assertEquals('first-post', $postFile['name']);
        $this->assertTrue(is_file($postFile['path']));
        
        $postFile = $postFiles[4];
        $this->assertEquals('2010', $postFile['year']);
        $this->assertEquals('01', $postFile['month']);
        $this->assertEquals('08', $postFile['day']);
        $this->assertEquals('second-post', $postFile['name']);
        $this->assertTrue(is_file($postFile['path']));
        
        $postFile = $postFiles[3];
        $this->assertEquals('2010', $postFile['year']);
        $this->assertEquals('11', $postFile['month']);
        $this->assertEquals('02', $postFile['day']);
        $this->assertEquals('third-post', $postFile['name']);
        $this->assertTrue(is_file($postFile['path']));
        
        $postFile = $postFiles[2];
        $this->assertEquals('2011', $postFile['year']);
        $this->assertEquals('09', $postFile['month']);
        $this->assertEquals('23', $postFile['day']);
        $this->assertEquals('fourth-post', $postFile['name']);
        $this->assertTrue(is_file($postFile['path']));
        
        // FileSystems don't load the post so they don't know about
        // the time... here, they will return the 6th post before the 5th.
        $postFile = $postFiles[1];
        $this->assertEquals('2011', $postFile['year']);
        $this->assertEquals('09', $postFile['month']);
        $this->assertEquals('24', $postFile['day']);
        $this->assertEquals('a-sixth-post', $postFile['name']);
        $this->assertTrue(is_file($postFile['path']));
        
        $postFile = $postFiles[0];
        $this->assertEquals('2011', $postFile['year']);
        $this->assertEquals('09', $postFile['month']);
        $this->assertEquals('24', $postFile['day']);
        $this->assertEquals('b-fifth-post', $postFile['name']);
        $this->assertTrue(is_file($postFile['path']));
    }
    
    public function getPostPathInfoDataProvider()
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
     * @dataProvider getPostPathInfoDataProvider
     */
    public function testGetPostPathInfo($fsType, $wildcardComponent)
    {
        $pc = new MockPieCrust();
        $pc->setPostsDir(PIECRUST_UNITTESTS_TEST_DATA_DIR . 'posts/' . $fsType . '/');
        $pc->getConfig()->setValue('site/posts_fs', $fsType);
        
        $fs = FileSystem::create($pc);
        $postFiles = $fs->getPostFiles();
        $this->assertNotNull($postFiles);
        $this->assertEquals(6, count($postFiles));
        
        $years = array('2009', '2010', '2010', '2011', '2011', '2011');
        $months = array('05', '01', '11', '09', '09', '09');
        $days = array('16', '08', '02', '23', '24', '24');
        $slugs = array('first-post', 'second-post', 'third-post', 'fourth-post', 'a-sixth-post', 'b-fifth-post');
        for ($i = 0; $i < 6; ++$i)
        {
            $groups = array('year' => $years[$i], 'month' => $months[$i], 'day' => $days[$i], 'slug' => $slugs[$i]);
            if ($wildcardComponent != null)
                unset($groups[$wildcardComponent]);
            $pathInfo = $fs->getPostPathInfo($groups);
            $this->assertEquals($years[$i], $pathInfo['year']);
            $this->assertEquals($months[$i], $pathInfo['month']);
            $this->assertEquals($days[$i], $pathInfo['day']);
            $this->assertEquals($slugs[$i], $pathInfo['slug']);
            $this->assertEquals(str_replace('\\', '/', $postFiles[5 - $i]['path']), str_replace('\\', '/', $pathInfo['path']));
        }
    }
}
