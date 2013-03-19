<?php

namespace PieCrust\Tests;

use PieCrust\PieCrustDefaults;
use PieCrust\Mock\MockFileSystem;


class EnvironmentTest extends PieCrustTestCase
{
    public function testGetEmptySitePageInfos()
    {
        $fs = MockFileSystem::create();
        $app = $fs->getApp();
        $pageInfos = $app->getEnvironment()->getPageInfos();

        $this->assertEquals(1, count($pageInfos));
        $this->assertEquals('_index.html', $pageInfos[0]['relative_path']);
        $this->assertEquals(
            str_replace('\\', '/', PieCrustDefaults::RES_DIR() . 'pages/_index.html'),
            str_replace('\\', '/', $pageInfos[0]['path'])
        );
    }

    public function testGetSimpleSitePageInfos()
    {
        $fs = MockFileSystem::create()
            ->withPage('_index', array(), 'Blah.');
        $app = $fs->getApp();
        $pageInfos = $app->getEnvironment()->getPageInfos();

        $this->assertEquals(1, count($pageInfos));
        $this->assertEquals('_index.html', $pageInfos[0]['relative_path']);
        $this->assertEquals(
            str_replace('\\', '/', $fs->url('kitchen/_content/pages/_index.html')),
            str_replace('\\', '/', $pageInfos[0]['path'])
        );
    }
}

