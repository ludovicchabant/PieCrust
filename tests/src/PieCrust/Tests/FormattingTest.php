<?php

namespace PieCrust\Tests;

use PieCrust\IPieCrust;
use PieCrust\Formatters\IFormatter;
use PieCrust\Util\Configuration;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockFormatter;
use PieCrust\Mock\MockPieCrust;
use PieCrust\Mock\MockPlugin;


class FormattingTest extends PieCrustTestCase
{
    public function testMockFormatter()
    {
        $stub = $this->getApp('mock');
        $text = PieCrustHelper::formatText($stub, '### Test Title', 'mock');
        $this->assertEquals('Formatted: ### Test Title', $text);
    }

    /**
     * @expectedException PieCrust\PieCrustException
     */
    public function testMissingFormatter()
    {
        $stub = $this->getApp('mock');
        PieCrustHelper::formatText($stub, '### Test Title', 'missing');
    }

    public function testFormatterOrdering()
    {
        $first = new MockFormatter('ext', IFormatter::PRIORITY_HIGH, 'FIRST');
        $second = new MockFormatter('ext', IFormatter::PRIORITY_DEFAULT, 'SECOND');
        $third = new MockFormatter('ext', IFormatter::PRIORITY_LOW, 'THIRD');

        $app = new MockPieCrust();
        $mockPlugin = new MockPlugin();
        $mockPlugin->formatters = array($third, $first, $second);

        $stub = $this->getMockBuilder('PieCrust\Plugins\PluginLoader')
                     ->setMethods(array('getPlugins', 'ensureLoaded'))
                     ->setConstructorArgs(array($app))
                     ->getMock();
        $stub->expects($this->any())
             ->method('getPlugins')
             ->will($this->returnValue(array($mockPlugin)));

        $this->assertEquals(array($first, $second, $third), $stub->getFormatters());
    }

    public function testSmartyPantsFormatter()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'smartypants' => array('enabled' => true)
            ));
        $app = $fs->getApp();

        $text = PieCrustHelper::formatText($app, 'Something...');
        $this->assertEquals("<p>Something&#8230;</p>\n", $text);

        // At the beginning you enabled SmartyPants with 'enable', and not 'enabled'.
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'smartypants' => array('enable' => true)
            ));
        $app = $fs->getApp();

        $text = PieCrustHelper::formatText($app, 'Something...');
        $this->assertEquals("<p>Something&#8230;</p>\n", $text);
    }

    private function getApp($supportedFormats)
    {
        $app = new MockPieCrust();
        $app->getConfig()->set(array(
            'site' => array(
                'default_format' => 'doesnt_exist'
            )));
        $loader = $app->getPluginLoader();
        $loader->formatters[] = new MockFormatter($supportedFormats);
        return $app;
    }
}
 
