<?php

require_once 'unittest_setup.php';

require_once 'vfsStream/vfsStream.php';

use PieCrust\Util\PathHelper;


class PathHelperTest extends PHPUnit_Framework_TestCase
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
        $root = vfsStream::create($structure);

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
        $root = vfsStream::create($structure);

        $cwd = vfsStream::url('root');
        $webRoot = PathHelper::getAppRootDir($cwd);
        $this->assertNull($webRoot);

        $cwd = vfsStream::url('root/other');
        $webRoot = PathHelper::getAppRootDir($cwd);
        $this->assertNull($webRoot);
    }
}

