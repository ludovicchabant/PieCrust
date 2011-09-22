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
        
        $actualConfig = $pc->get();
        foreach ($expectedConfig as $key => $value)
        {
            $paths = explode('/', $key);
            $cur = $actualConfig;
            foreach ($paths as $p)
            {
                $this->assertArrayHasKey($p, $cur);
                $cur = $cur[$p];
            }
            $this->assertEquals($value, $cur);
        }
    }
}
