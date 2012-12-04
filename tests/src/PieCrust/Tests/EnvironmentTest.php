<?php

use PieCrust\PieCrustDefaults;
use PieCrust\Mock\MockFileSystem;


class EnvironmentTest extends PHPUnit_Framework_TestCase
{
    public function testGetEmptySitePageInfos()
    {
        $fs = MockFileSystem::create();
        $app = $fs->getApp();
        $pageInfos = $app->getEnvironment()->getPageInfos();

        $this->assertEquals(1, count($pageInfos));
        $this->assertEquals('_index.html', $pageInfos[0]['relative_path']);
        $this->assertEquals(PieCrustDefaults::RES_DIR() . 'pages/_index.html', $pageInfos[0]['path']);
    }

    public function testGetSimpleSitePageInfos()
    {
        $fs = MockFileSystem::create()
            ->withPage('_index', array(), 'Blah.');
        $app = $fs->getApp();
        $pageInfos = $app->getEnvironment()->getPageInfos();

        $this->assertEquals(1, count($pageInfos));
        $this->assertEquals('_index.html', $pageInfos[0]['relative_path']);
        $this->assertEquals($fs->url('kitchen/_content/pages/_index.html'), $pageInfos[0]['path']);
    }
}

