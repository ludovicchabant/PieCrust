<?php

require_once (dirname(__DIR__) . '/unittest_setup.php');

use PieCrust\PieCrust;
use PieCrust\IO\FileSystem;


class FileSystemTest extends PHPUnit_Framework_TestCase
{
    public function fileSystemDataProvider()
    {
        return array(
            array('flat'),
            array('shallow'),
            array('hierarchy')
        );
    }
    
    /**
     * @dataProvider fileSystemDataProvider
     */
    public function testFileSystem($fsType)
    {
        $pc = new PieCrust(array('cache' => false, 'root' => PIECRUST_UNITTESTS_EMPTY_ROOT_DIR));
        $pc->setPostsDir(PIECRUST_UNITTESTS_TEST_DATA_DIR . 'posts/' . $fsType);
        $pc->setConfigValue('site', 'posts_fs', $fsType);
        
        $fs = FileSystem::create($pc);
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
}
