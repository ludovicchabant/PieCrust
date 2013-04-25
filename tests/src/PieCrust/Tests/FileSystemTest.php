<?php

namespace PieCrust\Tests;

use PieCrust\IO\FileSystem;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;


class FileSystemTest extends PieCrustTestCase
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
        $pc->setPostsDir(PIECRUST_UNITTESTS_DATA_DIR . 'posts/' . $fsType . '/');
        $pc->getConfig()->setValue('site/posts_fs', $fsType);
        
        $fs = FileSystem::create($pc);
        $this->assertNotNull($fs);
        $postFiles = $fs->getPostFiles();
        $postFiles = MockFileSystem::sortPostInfos($postFiles);
        $this->assertNotNull($postFiles);
        $this->assertEquals(6, count($postFiles));

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
        $pc->setPostsDir(PIECRUST_UNITTESTS_DATA_DIR . 'posts/' . $fsType . '/');
        $pc->getConfig()->setValue('site/posts_fs', $fsType);
        
        $fs = FileSystem::create($pc);
        $postFiles = $fs->getPostFiles();
        $postFiles = MockFileSystem::sortPostInfos($postFiles);
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

    public function testGetPageFiles()
    {
        $fs = MockFileSystem::create()
            ->withPage('test1')
            ->withPage('testxml.xml')
            ->withPage('foo/test2')
            ->withPage('foo/testtxt.txt')
            ->withPage('foo/bar/test3')
            ->withPage('foo/test-stuff')
            ->withPage('bar/blah')
            ->withPage('_tag')
            ->withPage('_category')
            ->withPage('otherblog/_tag')
            ->withPage('otherblog/_category')
            ->withPageAsset('bar/blah', 'something.txt')
            ->withAsset('_content/pages/.whatever', 'fake')
            ->withAsset('_content/pages/.DS_Store', 'fake')
            ->withAsset('_content/pages/.svn/blah', 'fake')
            ->withAsset('_content/pages/Thumbs.db', 'fake')
            ->withAsset('_content/pages/foo/.DS_Store', 'fake')
            ->withAsset('_content/pages/foo/Thumbs.db', 'fake')
            ->withAsset('_content/pages/foo/.svn/blah', 'fake');

        $pc = new MockPieCrust();
        $pc->setPagesDir($fs->url('kitchen/_content/pages'));
        $pc->getConfig()->setValue('site/posts_fs', 'flat');
        
        $pcFs = FileSystem::create($pc);
        $pageFiles = $pcFs->getPageFiles();
        foreach ($pageFiles as &$pf)
        {
            // Fix slash/backslash problems on Windows that make
            // the test fail (PHP won't care about it so it's
            // functionally the same AFAIK).
            $pf['path'] = str_replace('\\', '/', $pf['path']);
            $pf['relative_path'] = str_replace('\\', '/', $pf['relative_path']);
        }

        $expected = array(
            array(
                'path' => $fs->url('kitchen/_content/pages/test1.html'),
                'relative_path' => 'test1.html'
            ),
            array(
                'path' => $fs->url('kitchen/_content/pages/testxml.xml'),
                'relative_path' => 'testxml.xml'
            ),
            array(
                'path' => $fs->url('kitchen/_content/pages/foo/test2.html'),
                'relative_path' => 'foo/test2.html'
            ),
            array(
                'path' => $fs->url('kitchen/_content/pages/foo/testtxt.txt'),
                'relative_path' => 'foo/testtxt.txt'
            ),
            array(
                'path' => $fs->url('kitchen/_content/pages/foo/bar/test3.html'),
                'relative_path' => 'foo/bar/test3.html'
            ),
            array(
                'path' => $fs->url('kitchen/_content/pages/foo/test-stuff.html'),
                'relative_path' => 'foo/test-stuff.html'
            ),
            array(
                'path' => $fs->url('kitchen/_content/pages/bar/blah.html'),
                'relative_path' => 'bar/blah.html'
            )
        );
        $this->assertEquals($expected, $pageFiles);
    }
}
