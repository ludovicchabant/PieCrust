<?php

require_once (dirname(__DIR__) . '/unittest_setup.php');

use PieCrust\PieCrustConfiguration;


class PieCrustConfigurationTest extends PHPUnit_Framework_TestCase
{
    public function configurationDataProvider()
    {
        return array(
            array(
                array(
                    'site' => array('title' => 'Test Site')
                ),
                array(
                    'site/title' => 'Test Site',
                    'site/pretty_urls' => false,
                    'site/blogs' => array('blog')
                )
            ),
            array(
                array(
                    'site' => array('posts_per_page' => 10, 'date_format' => 'something'),
                ),
                array(
                    'blog/posts_per_page' => 10,
                    'blog/date_format' => 'something'
                )
            ),
            array(
                array(
                    'site' => array('blogs' => array('one', 'two'))
                ),
                array(
                    'one/posts_per_page' => 5,
                    'one/date_format' => 'F j, Y',
                    'two/posts_per_page' => 5,
                    'two/date_format' => 'F j, Y'
                )
            ),
            array(
                array(
                    'site' => array('blogs' => array('one', 'two')),
                    'one' => array('posts_per_page' => 10)
                ),
                array(
                    'one/posts_per_page' => 10,
                    'one/date_format' => 'F j, Y',
                    'two/posts_per_page' => 5,
                    'two/date_format' => 'F j, Y'
                )
            )
        );
    }
    
    /**
     * @dataProvider configurationDataProvider
     */
    public function testConfiguration($config, $expectedConfig)
    {
        $pc = new PieCrustConfiguration();
        $pc->set($config);
        
        foreach ($expectedConfig as $key => $value)
        {
            $this->assertEquals($value, $pc->getValue($key));
        }
    }
    
    public function testValidate()
    {
        $pc = new PieCrustConfiguration();
        
        $this->assertEquals("Untitled PieCrust Website", $pc->getValue('site/title'));
        $pc->merge(array(
            'site' => array(
                'title' => "Merged Title",
                'root' => "http://without-slash"
            )
        ));
        $this->assertEquals("Merged Title", $pc->getValue('site/title'));
        $this->assertEquals("http://without-slash/", $pc->getValue('site/root')); // should have added the trailing slash.
    }
}
