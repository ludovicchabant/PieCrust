<?php

namespace PieCrust\Tests;

use PieCrust\IPage;
use PieCrust\PieCrust;
use PieCrust\Baker\BakerAssistant;
use PieCrust\Baker\BakeResult;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;


class TestBakerAssistant extends BakerAssistant
{
    public $pagesBaked;
    public $pagesSkipped;

    public function __construct()
    {
        parent::__construct('test baker assistant');
        $this->pagesBaked = 0;
        $this->pagesSkipped = 0;
    }

    public function onPageBakeStart(IPage $page)
    {
    }

    public function onPageBakeEnd(IPage $page, BakeResult $result)
    {
        if ($result->didBake)
            $this->pagesBaked++;
        else
            $this->pagesSkipped++;
    }
}

class BakerAssistantTest extends PieCrustTestCase
{
    public function testBakerAssistant()
    {
        $fs = MockFileSystem::create();
        $fs->withPage('foo', array('layout' => 'none'), 'Something');
        $fs->withPage('bar', array('layout' => 'none'), 'Whatever');
        $fs->withPostsDir();
        $app = $fs->getMockApp();
        $app->getConfig()->setValue('site/default_template_engine', 'none');
        $app->getConfig()->setValue('site/default_format', 'none');
        $testAssistant = new TestBakerAssistant('test1');
        $app->pluginLoader->bakerAssistants = array($testAssistant);

        $baker = new PieCrustBaker($app);
        sleep(1);
        $baker->bake();

        $this->assertTrue(is_file($fs->url('kitchen/_counter/foo.html')));
        $this->assertTrue(is_file($fs->url('kitchen/_counter/bar.html')));
        $this->assertEquals(2, $testAssistant->pagesBaked);
        $this->assertEquals(0, $testAssistant->pagesSkipped);

        $testAssistant->pagesBaked = 0;
        $testAssistant->pagesSkipped = 0;
        clearstatcache();
        sleep(1);
        $baker->bake();

        $this->assertEquals(0, $testAssistant->pagesBaked);
        $this->assertEquals(2, $testAssistant->pagesSkipped);
    }
}

