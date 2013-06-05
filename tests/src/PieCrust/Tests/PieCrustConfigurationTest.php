<?php

namespace PieCrust\Tests;

use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustConfiguration;
use PieCrust\Mock\MockFileSystem;


class PieCrustConfigurationTest extends PieCrustTestCase
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

    public function templateDirectoriesDataProvider()
    {
        return array(
            array(
                null,
                array('kitchen/_content/templates/')
            ),
            array(
                '_content/override',
                array(
                    'kitchen/_content/override/',
                    'kitchen/_content/templates/'
                )
            ),
            array(
                array(
                    '_content/override',
                    '../common'
                ),
                array(
                    'kitchen/_content/override/',
                    'common/',
                    'kitchen/_content/templates/'
                )
            ),
            array(
                array(
                    '_content/override',
                    '_content/something',
                    '../common'
                ),
                array(
                    'kitchen/_content/override/',
                    'kitchen/_content/something/',
                    'common/',
                    'kitchen/_content/templates/'
                )
            )
        );
    }

    /**
     * @dataProvider templateDirectoriesDataProvider
     */
    public function testTemplateDirectories($config, $expectedDirs)
    {
        $config = array('site' => array(
            'templates_dirs' => $config
        ));
        $fs = MockFileSystem::create()
            ->withTemplatesDir()
            ->withConfig($config);
        $app = $fs->getApp();

        foreach ($expectedDirs as &$dir)
        {
            $dir = $fs->url($dir);
            if (!is_dir($dir))
                mkdir($dir);
        }
        $expectedDirs[] = PieCrustDefaults::RES_DIR() . 'theme/_content/templates/';

        $this->assertEquals(
            $expectedDirs,
            $app->getTemplatesDirs()
        );
    }

    public function templateDirectoriesWithTemeDataProvider()
    {
        return array(
            array(
                null,
                null,
                array('kitchen/_content/templates/')
            ),
            array(
                null,
                array('_content/layouts'),
                array(
                    'kitchen/_content/theme/_content/layouts/',
                    'kitchen/_content/templates/'
                )
            ),
            array(
                array('_content/special'),
                array('_content/layouts'),
                array(
                    'kitchen/_content/special/',
                    'kitchen/_content/theme/_content/layouts/',
                    'kitchen/_content/templates/'
                )
            )
        );
    }

    /**
     * @dataProvider templateDirectoriesWithTemeDataProvider
     */
    public function testTemplateDirectoriesWithTheme($config, $themeConfig, $expectedDirs)
    {
        if ($config != null)
        {
            $config = array('site' => array(
                'templates_dirs' => $config
            ));
        }
        else
        {
            $config = array();
        }

        if ($themeConfig != null)
        {
            $themeConfig = array('site' => array(
                'templates_dirs' => $themeConfig
            ));
        }
        else
        {
            $themeConfig = array();
        }

        $fs = MockFileSystem::create()
            ->withTemplatesDir()
            ->withConfig($config)
            ->withThemeConfig($themeConfig);
        $app = $fs->getApp();

        foreach ($expectedDirs as &$dir)
        {
            $dir = $fs->url($dir);
            if (!is_dir($dir))
                mkdir($dir);
        }
        $this->assertEquals(
            $expectedDirs,
            $app->getTemplatesDirs()
        );
    }
}
