<?php

namespace PieCrust\Tests;

use PieCrust\PieCrustDefaults;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Util\PageHelper;


class EnvironmentTest extends PieCrustTestCase
{
    public function testGetEmptySitePages()
    {
        $fs = MockFileSystem::create();
        $app = $fs->getApp();
        $pages = $app->getEnvironment()->getPages();

        $this->assertEquals(1, count($pages));
        $this->assertEquals('_index.html', PageHelper::getRelativePath($pages[0]));
        $this->assertEquals(
            str_replace('\\', '/', PieCrustDefaults::RES_DIR() . 'theme/_content/pages/_index.html'),
            str_replace('\\', '/', $pages[0]->getPath())
        );
    }

    public function testGetSimpleSitePages()
    {
        $fs = MockFileSystem::create()
            ->withPage('_index', array(), 'Blah.');
        $app = $fs->getApp();
        $pages = $app->getEnvironment()->getPages();

        $this->assertEquals(1, count($pages));
        $this->assertEquals('_index.html', PageHelper::getRelativePath($pages[0]));
        $this->assertEquals(
            str_replace('\\', '/', $fs->url('kitchen/_content/pages/_index.html')),
            str_replace('\\', '/', $pages[0]->getPath())
        );
    }
}

