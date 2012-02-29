<?php

require_once 'unittest_setup.php';

use PieCrust\IPieCrust;
use PieCrust\Formatters\IFormatter;
use PieCrust\Util\Configuration;
use PieCrust\Util\PieCrustHelper;


class FormattingTest extends PHPUnit_Framework_TestCase
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

    private function getApp($supportedFormats)
    {
        $stub = $this->getMockBuilder('PieCrust\PieCrust')
                     ->disableOriginalConstructor()
                     ->setMethods(array('ensureConfig', 'getConfig', 'getPluginLoader'))
                     ->getMock();
        
        $config = new Configuration(array(
            'site' => array(
                'default_format' => 'doesnt_exist'
            )
        ));
        $stub->expects($this->any())
             ->method('getConfig')
             ->will($this->returnValue($config));
        
        $loader = new MockPluginLoader();
        $loader->formatters[] = new MockFormatter($supportedFormats);
        $stub->expects($this->any())
             ->method('getPluginLoader')
             ->will($this->returnValue($loader));

        return $stub;
    }
}
 
