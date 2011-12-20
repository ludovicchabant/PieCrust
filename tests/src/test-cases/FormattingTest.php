<?php

require_once 'unittest_setup.php';

use PieCrust\IPieCrust;
use PieCrust\Util\Configuration;


class FormattingTest extends PHPUnit_Framework_TestCase
{
    public function testMockFormatter()
    {
        $stub = $this->getApp('mock');
        $text = $stub->formatText('### Test Title', 'mock');
        $this->assertEquals('Formatted: ### Test Title', $text);
    }

    /**
     * @expectedException PieCrust\PieCrustException
     */
    public function testMissingFormatter()
    {
        $stub = $this->getApp('mock');
        $stub->formatText('### Test Title', 'missing');
    }

    private function getApp($supportedFormats)
    {
        $stub = $this->getMockBuilder('PieCrust\PieCrust')
                     ->disableOriginalConstructor()
                     ->setMethods(array('ensureConfig', 'getConfig', 'getFormattersLoader'))
                     ->getMock();
        
        $config = new Configuration(array(
            'site' => array(
                'default_format' => 'doesnt_exist'
            )
        ));
        $stub->expects($this->any())
             ->method('getConfig')
             ->will($this->returnValue($config));
        
        $loader = new MockPluginLoader(array(new MockFormatter($supportedFormats)));
        $stub->expects($this->any())
             ->method('getFormattersLoader')
             ->will($this->returnValue($loader));

        return $stub;
    }
}
 
