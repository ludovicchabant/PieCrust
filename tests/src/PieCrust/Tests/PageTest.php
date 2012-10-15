<?php

use PieCrust\Page\Page;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;


class PageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreatePageFromNullUri()
    {
        $app = new MockPieCrust();
        Page::createFromUri($app, null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreatePageFromNullPath()
    {
        $app = new MockPieCrust();
        Page::createFromPath($app, null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreatePageFromInvalidPath()
    {
        $app = new MockPieCrust();
        Page::createFromPath($app, 'something/missing.html');
    }

    /**
     * @expectedException \PieCrust\PieCrustException
     */
    public function testCreateInvalidPage()
    {
        $app = MockFileSystem::create()->getApp();
        Page::createFromUri($app, 'something/missing.html');
    }

    /**
     * @expectedException \PieCrust\PieCrustException
     */
    public function testCreateInvalidTagPage()
    {
        $app = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'tag_url' => 'tag/%tag%'
                )
            ))
            ->getApp();
        Page::createFromUri($app, '/tag/foo');
    }

    /**
     * @expectedException \PieCrust\PieCrustException
     */
    public function testCreateInvalidCategoryPage()
    {
        $app = MockFileSystem::create()
            ->withConfig(array(
                'site' => array(
                    'category_url' => 'cat/%category%',
                )
            ))
            ->getApp();
        Page::createFromUri($app, '/cat/foo');
    }
}

