<?php

namespace PieCrust\Tests;

use org\bovigo\vfs\vfsStream;
use PieCrust\Util\PathHelper;


class PathHelperTest extends PieCrustTestCase
{
    public function testGetValidAppRootDir()
    {
        $structure = array(
            'blah' => array(
                'web' => array(
                    '_content' => array(
                        'config.yml' => 'dummy'
                    ),
                    'media' => array(
                        'submedia' => array()
                    )
                )
            )
        );
        $root = vfsStream::setup('root', null, $structure);

        $cwd = vfsStream::url('root/blah/web');
        $webRoot = PathHelper::getAppRootDir($cwd);
        $this->assertEquals(vfsStream::url('root/blah/web'), $webRoot);

        $cwd = vfsStream::url('root/blah/web/media/submedia');
        $webRoot = PathHelper::getAppRootDir($cwd);
        $this->assertEquals(vfsStream::url('root/blah/web'), $webRoot);
    }

    public function testOutsideAppRootDir()
    {
        $structure = array(
            'blah' => array(
                'web' => array(
                    '_content' => array(
                        'config.yml' => 'dummy'
                    )
                ),
                'other' => array()
            )
        );
        $root = vfsStream::setup('root', null, $structure);

        $cwd = vfsStream::url('root');
        $webRoot = PathHelper::getAppRootDir($cwd);
        $this->assertNull($webRoot);

        $cwd = vfsStream::url('root/other');
        $webRoot = PathHelper::getAppRootDir($cwd);
        $this->assertNull($webRoot);
    }

    public function globToRegexDataProvider()
    {
        return array(
            array('blah', '/blah/'),
            array('/blah/', '/blah/'),
            array('/^blah.*\\.css/', '/^blah.*\\.css/'),
            array('blah.*', '/blah\\.[^\\/\\\\]*/'),
            array('blah?.css', '/blah[^\\/\\\\]\\.css/')
        );
    }

    /**
     * @dataProvider globToRegexDataProvider
     */
    public function testGlobToRegex($in, $expectedOut)
    {
        $out = PathHelper::globToRegex($in);
        $this->assertEquals($expectedOut, $out);
    }

    public function testGlobToRegexExample()
    {
        $pattern = PathHelper::globToRegex('blah*.css');
        $this->assertTrue(preg_match($pattern, 'dir/blah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah2.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blahblah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah.blah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah.blah.css/something') == 1);
        $this->assertFalse(preg_match($pattern, 'blah/something.css') == 1);

        $pattern = PathHelper::globToRegex('blah?.css');
        $this->assertFalse(preg_match($pattern, 'dir/blah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah1.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blahh.css') == 1);
        $this->assertFalse(preg_match($pattern, 'dir/blah/yo.css') == 1);
    }

}

