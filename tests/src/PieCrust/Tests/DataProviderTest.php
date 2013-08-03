<?php

namespace PieCrust\Tests;

use PieCrust\IPage;
use PieCrust\PieCrust;
use PieCrust\Data\DataProvider;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;


class TestDataProvider extends DataProvider
{
    public $value;

    public function __construct($name, $value)
    {
        parent::__construct($name);
        $this->value = $value;
    }

    public function getPageData(IPage $page)
    {
        return $this->value;
    }
}

class DataProviderTest extends PieCrustTestCase
{
    public function dataProviderDataProvider()
    {
        return array(
            array('blah', 'whatever', 'blah', 'whatever'),
            array('blah', array('foo' => 'whatever'), 'blah.foo', 'whatever')
        );
    }

    /**
     * @dataProvider dataProviderDataProvider
     * (OMG! Inception!)
     */
    public function testDataProvider($endPoint, $value, $accessor, $accessedValue)
    {
        $fs = MockFileSystem::create();
        $fs->withPage(
            'foo', 
            array('layout' => 'none', 'format' => 'none'), 
            'Something: {{'.$accessor.'}}'
        );
        $fs->withPostsDir();
        $fs->withTemplatesDir();
        $app = $fs->getMockApp();
        $app->addTemplateEngine('twig', 'TwigTemplateEngine');
        $testProvider = new TestDataProvider($endPoint, $value);
        $app->pluginLoader->dataProviders = array($testProvider);

        $baker = new PieCrustBaker($app);
        $baker->bake();

        $path = $fs->url('kitchen/_counter/foo.html');
        $actual = file_get_contents($path);
        $expected = 'Something: '.$accessedValue;
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider dataProviderDataProvider
     */
    public function testDataProviderInTemplate($endPoint, $value, $accessor, $accessedValue)
    {
        $fs = MockFileSystem::create();
        $fs->withPage(
            'foo', 
            array('layout' => 'special', 'format' => 'none'), 
            'stuff'
        );
        $fs->withTemplate(
            'special',
            "Something: {{".$accessor."}} {{content|raw}}"
        );
        $fs->withPostsDir();
        $app = $fs->getMockApp();
        $app->addTemplateEngine('twig', 'TwigTemplateEngine');
        $testProvider = new TestDataProvider($endPoint, $value);
        $app->pluginLoader->dataProviders = array($testProvider);

        $baker = new PieCrustBaker($app);
        $baker->bake();

        $path = $fs->url('kitchen/_counter/foo.html');
        $actual = file_get_contents($path);
        $expected = 'Something: '.$accessedValue.' stuff';
        $this->assertEquals($expected, $actual);
    }
}

