<?php

namespace PieCrust\Tests;

use PieCrust\Util\Configuration;


class ConfigurationTest extends PieCrustTestCase
{
    public function configurationDataProvider()
    {
        return array(
            array(
                array(
                    'foo' => 'bar'
                ),
                array(
                    'foo' => 'bar',
                    'something' => null
                )
            ),
            array(
                array(
                    'foo' => array('bar' => 42, 'baz' => 'something'),
                ),
                array(
                    'foo/bar' => 42,
                    'foo/baz' => 'something',
                    'foo/wut' => null
                )
            ),
            array(
                array(
                    'foo' => array('bar' => array('one', 'two'))
                ),
                array(
                    'foo/bar' => array('one', 'two')
                )
            )
        );
    }
    
    /**
     * @dataProvider configurationDataProvider
     */
    public function testConfiguration($config, $expectedConfig)
    {
        $pc = new Configuration($config);
        foreach ($expectedConfig as $key => $value)
        {
            $this->assertEquals($value, $pc->getValue($key));
        }
    }
    
    public function testGetValue1()
    {
        $c = new Configuration(array(
            'foo' => 'bar'
        ));
        $this->assertEquals('bar', $c->getValue('foo'));
        $this->assertEquals('bar', $c->getValueUnchecked('foo'));
        $this->assertNull($c->getValue('doesntexist'));
        $value = $c->getValue('foo');
        $value = 'other';
        $this->assertEquals('bar', $c->getValue('foo'));
    }
    
    public function testGetValue2()
    {
        $c = new Configuration(array(
            'foo' => array(
                'bar' => 42,
                'baz' => 'something'
            )
        ));
        $this->assertEquals(42, $c->getValue('foo/bar'));
        $this->assertEquals('something', $c->getValueUnchecked('foo/baz'));
        $this->assertNull($c->getValue('doesntexist'));
        $this->assertNull($c->getValue('foo/doesntexist'));
        $this->assertNull($c->getValue('foo/bar/doesntexist'));
        $value = $c->getValue('foo/bar');
        $value = 'other';
        $this->assertEquals(42, $c->getValue('foo/bar'));
    }
    
    public function testSetValue1()
    {
        $c = new Configuration(array(
            'foo' => 'bar'
        ));
        $this->assertEquals('bar', $c->getValue('foo'));
        $c->setValue('foo', 'other');
        $this->assertEquals('other', $c->getValue('foo'));
    }
    
    public function testSetValue2()
    {
        $c = new Configuration(array(
            'foo' => array(
                'bar' => 42,
                'baz' => 'something'
            )
        ));
        $this->assertEquals(42, $c->getValue('foo/bar'));
        $c->setValue('foo/bar', 'other');
        $this->assertEquals('other', $c->getValue('foo/bar'));
    }
    
    public function testAppendValue1()
    {
        $c = new Configuration(array(
            'foo' => 'bar'
        ));
        $this->assertNull($c->getValue('blah'));
        $c->appendValue('blah', 'one');
        $this->assertEquals(array('one'), $c->getValue('blah'));
        $c->appendValue('blah', 'two');
        $this->assertEquals(array('one', 'two'), $c->getValue('blah'));
        $c->appendValue('blah', 'two');
        $this->assertEquals(array('one', 'two', 'two'), $c->getValue('blah'));
    }
    
    public function testAppendValue2()
    {
        $c = new Configuration(array(
            'foo' => array(
                'bar' => true
            )
        ));
        $this->assertNull($c->getValue('foo/blah'));
        $c->appendValue('foo/blah', 'one');
        $this->assertEquals(array('one'), $c->getValue('foo/blah'));
        $c->appendValue('foo/blah', 'two');
        $this->assertEquals(array('one', 'two'), $c->getValue('foo/blah'));
        $c->appendValue('foo/blah', 'two');
        $this->assertEquals(array('one', 'two', 'two'), $c->getValue('foo/blah'));
    }
    
    public function testMerge()
    {
        $pc = new Configuration(array(
            'site' => array(
                'title' => "Untitled",
                'root' => "/"
            )
        ));
        
        $this->assertEquals("Untitled", $pc->getValue('site/title'));
        $this->assertEquals("/", $pc->getValue('site/root'));
        $this->assertEquals(null, $pc->getValue('site/other'));
        $this->assertEquals(null, $pc->getValue('foo/bar'));
        $this->assertEquals(null, $pc->getValue('simple'));
        $pc->merge(array(
            'site' => array(
                'title' => "Merged Title",
                'root' => "http://root",
                'other' => "Something"
            ),
            'foo' => array(
                'bar' => "FOO BAR!"
            ),
            'simple' => "simple value"
        ));
        $this->assertEquals("Merged Title", $pc->getValue('site/title'));
        $this->assertEquals("http://root", $pc->getValue('site/root'));
        $this->assertEquals("Something", $pc->getValue('site/other'));
        $this->assertEquals("FOO BAR!", $pc->getValue('foo/bar'));
        $this->assertEquals("simple value", $pc->getValue('simple'));
    }
}
