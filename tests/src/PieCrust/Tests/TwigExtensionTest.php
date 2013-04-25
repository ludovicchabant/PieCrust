<?php

namespace PieCrust\Tests;

use PieCrust\PieCrust;
use PieCrust\Page\Page;
use PieCrust\Page\PageRenderer;
use PieCrust\Mock\MockFileSystem;


class TwigExtensionTest extends PieCrustTestCase
{
    public static function urlFunctionsDataProvider()
    {
        return array(
            array(
                array(),
                array(
                    '/?/foo',
                    '/?/foo/bar',
                    '/?/tag/tag1',
                    '/?/tag/tag1/tag2',
                    '/?/cat1',
                    '/?/2012/01/10/post1-test',
                    '/?/2012/01/10/post1-test'
                )
            ),
            array(
                array('baker' => array('is_baking' => true)),
                array(
                    '/foo.html',
                    '/foo/bar.html',
                    '/tag/tag1.html',
                    '/tag/tag1/tag2.html',
                    '/cat1.html',
                    '/2012/01/10/post1-test.html',
                    '/2012/01/10/post1-test.html'
                )
            ),
            array(
                array('site' => array('pretty_urls' => true)),
                array(
                    '/foo',
                    '/foo/bar',
                    '/tag/tag1',
                    '/tag/tag1/tag2',
                    '/cat1',
                    '/2012/01/10/post1-test',
                    '/2012/01/10/post1-test'
                )
            ),
            array(
                array(
                    'site' => array('pretty_urls' => true), 
                    'baker' => array('is_baking' => true)
                ),
                array(
                    '/foo',
                    '/foo/bar',
                    '/tag/tag1',
                    '/tag/tag1/tag2',
                    '/cat1',
                    '/2012/01/10/post1-test',
                    '/2012/01/10/post1-test'
                )
            ),
            array(
                array('site' => array(
                        'post_url' => 'blog/%year%/%month%/%slug%',
                        'tag_url' => 'blog/tag/%tag%',
                        'category_url' => 'blog/category/%category%'
                    )
                ),
                array(
                    '/?/foo',
                    '/?/foo/bar',
                    '/?/blog/tag/tag1',
                    '/?/blog/tag/tag1/tag2',
                    '/?/blog/category/cat1',
                    '/?/blog/2012/01/post1-test',
                    '/?/blog/2012/01/post1-test'
                )
            ),
            array(
                array('site' => array(
                        'pretty_urls' => true,
                        'post_url' => 'blog/%year%/%month%/%slug%',
                        'tag_url' => 'blog/tag/%tag%',
                        'category_url' => 'blog/category/%category%'
                    )
                ),
                array(
                    '/foo',
                    '/foo/bar',
                    '/blog/tag/tag1',
                    '/blog/tag/tag1/tag2',
                    '/blog/category/cat1',
                    '/blog/2012/01/post1-test',
                    '/blog/2012/01/post1-test'
                )
            ),
            array(
                array('site' => array(
                        'post_url' => 'blog/%year%/%month%/%slug%',
                        'tag_url' => 'blog/tag/%tag%',
                        'category_url' => 'blog/category/%category%'
                    ),
                    'baker' => array('is_baking' => true)
                ),
                array(
                    '/foo.html',
                    '/foo/bar.html',
                    '/blog/tag/tag1.html',
                    '/blog/tag/tag1/tag2.html',
                    '/blog/category/cat1.html',
                    '/blog/2012/01/post1-test.html',
                    '/blog/2012/01/post1-test.html'
                )
            ),
            array(
                array('site' => array(
                        'pretty_urls' => true,
                        'post_url' => 'blog/%year%/%month%/%slug%',
                        'tag_url' => 'blog/tag/%tag%',
                        'category_url' => 'blog/category/%category%'
                    ),
                    'baker' => array('is_baking' => true)
                ),
                array(
                    '/foo',
                    '/foo/bar',
                    '/blog/tag/tag1',
                    '/blog/tag/tag1/tag2',
                    '/blog/category/cat1',
                    '/blog/2012/01/post1-test',
                    '/blog/2012/01/post1-test'
                )
            )
        );
    }

    /**
     * @dataProvider urlFunctionsDataProvider
     */
    public function testUrlFunctions($siteConfig, $expectedOutput)
    {
        $fs = MockFileSystem::create()
            ->withConfig($siteConfig)
            ->withPage(
                'test1', 
                array('format' => 'none', 'layout' => 'none'), 
                <<<EOD
{{pcurl('foo')}}
{{pcurl('foo/bar')}}
{{pctagurl('tag1')}}
{{pctagurl(['tag1', 'tag2'])}}
{{pccaturl('cat1')}}
{{pcposturl(2012, 1, 10, 'post1-test')}}
{{pcposturl('2012', '01', '10', 'post1-test')}}
EOD
        );
        $app = $fs->getApp();
        $page = Page::createFromUri($app, '/test1');
        $pageRenderer = new PageRenderer($page);
        $output = $pageRenderer->get();
        $lines = explode("\n", $output);
        $this->assertEquals($expectedOutput, $lines);
    }

    public static function urlFunctionsWithTwoBlogsDataProvider()
    {
        return array(
            array(
                array(),
                array(
                    '/?/blog-one/tag/tag1',
                    '/?/blog-one/tag/tag1',
                    '/?/blog-two/tag/tag1',
                    '/?/blog-one/cat1',
                    '/?/blog-one/cat1',
                    '/?/blog-two/cat1',
                    '/?/blog-one/2012/01/10/post1-test',
                    '/?/blog-one/2012/01/10/post1-test',
                    '/?/blog-two/2012/01/10/post1-test'
                )
            ),
            array(
                array('site' => array('pretty_urls' => true)),
                array(
                    '/blog-one/tag/tag1',
                    '/blog-one/tag/tag1',
                    '/blog-two/tag/tag1',
                    '/blog-one/cat1',
                    '/blog-one/cat1',
                    '/blog-two/cat1',
                    '/blog-one/2012/01/10/post1-test',
                    '/blog-one/2012/01/10/post1-test',
                    '/blog-two/2012/01/10/post1-test'
                )
            ),
            array(
                array('baker' => array('is_baking' => true)),
                array(
                    '/blog-one/tag/tag1.html',
                    '/blog-one/tag/tag1.html',
                    '/blog-two/tag/tag1.html',
                    '/blog-one/cat1.html',
                    '/blog-one/cat1.html',
                    '/blog-two/cat1.html',
                    '/blog-one/2012/01/10/post1-test.html',
                    '/blog-one/2012/01/10/post1-test.html',
                    '/blog-two/2012/01/10/post1-test.html'
                )
            ),
            array(
                array(
                    'site' => array('pretty_urls' => true), 
                    'baker' => array('is_baking' => true)
                ),
                array(
                    '/blog-one/tag/tag1',
                    '/blog-one/tag/tag1',
                    '/blog-two/tag/tag1',
                    '/blog-one/cat1',
                    '/blog-one/cat1',
                    '/blog-two/cat1',
                    '/blog-one/2012/01/10/post1-test',
                    '/blog-one/2012/01/10/post1-test',
                    '/blog-two/2012/01/10/post1-test'
                )
            )
        );
    }

    /**
     * @dataProvider urlFunctionsWithTwoBlogsDataProvider
     */
    public function testUrlFunctionsWithTwoBlogs($siteConfig, $expectedOutput)
    {
        if (!isset($siteConfig['site']))
            $siteConfig['site'] = array();
        $siteConfig['site']['blogs'] = array('blog-one', 'blog-two');

        $fs = MockFileSystem::create()
            ->withConfig($siteConfig)
            ->withPage(
                'test1',
                array('format' => 'none', 'layout' => 'none'),
                <<<EOD
{{pctagurl('tag1')}}
{{pctagurl('tag1', 'blog-one')}}
{{pctagurl('tag1', 'blog-two')}}
{{pccaturl('cat1')}}
{{pccaturl('cat1', 'blog-one')}}
{{pccaturl('cat1', 'blog-two')}}
{{pcposturl(2012, 1, 10, 'post1-test')}}
{{pcposturl(2012, 1, 10, 'post1-test', 'blog-one')}}
{{pcposturl(2012, 1, 10, 'post1-test', 'blog-two')}}
EOD
            );
        $app = $fs->getApp();
        $page = Page::createFromUri($app, '/test1');
        $pageRenderer = new PageRenderer($page);
        $output = $pageRenderer->get();
        $lines = explode("\n", $output);
        $this->assertEquals($expectedOutput, $lines);
    }

    public function urlFunctionsWithUnicodeDataProvider()
    {
        return array(
            array(false, 'des espaces', '/tag/des-espaces'),
            array(false, 'épatant', '/tag/epatant'),
            array(false, 'pâte à gateau', '/tag/pate-a-gateau'),
            array('transliterate', 'des espaces', '/tag/des-espaces'),
            array('transliterate', 'épatant', '/tag/epatant'),
            array('transliterate', 'pâte à gateau', '/tag/pate-a-gateau'),
            array('encode', 'des espaces', '/tag/des-espaces'),
            array('encode', 'épatant', '/tag/%C3%A9patant'),
            array('encode', 'pâte à gâteau', '/tag/p%C3%A2te-%C3%A0-g%C3%A2teau'),
            array('encode', 'Тест', '/tag/%D0%A2%D0%B5%D1%81%D1%82'),
            array('dash', 'des espaces', '/tag/des-espaces'),
            array('dash', 'épatant', '/tag/-patant'),
            array('dash', 'pâte à gâteau', '/tag/p-te-g-teau')
        );
    }

    /**
     * @dataProvider urlFunctionsWithUnicodeDataProvider
     */
    public function testUrlFunctionsWithUnicode($slugifyMode, $in, $out)
    {
        $config = array('site' => array('pretty_urls' => true));
        if ($slugifyMode !== false)
            $config['site']['slugify'] = $slugifyMode;
        $fs = MockFileSystem::create()
            ->withConfig($config)
            ->withPage(
                'test',
                array('format' => 'none', 'layout' => 'none'),
                "{{pctagurl('{$in}')}}"
            );
        $app = $fs->getApp();
        $page = Page::createFromUri($app, '/test');
        $actual = $page->getContentSegment();
        $this->assertEquals($out, $actual);
    }

    public function testTagUrlFunctionsWithBlogData()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array(
                'root' => 'localhost', 
                'pretty_urls' => true,
                'default_format' => 'none'
            )))
            ->withPost('post1', 1, 1, 2012, array('tags' => array('one')))
            ->withPost('post2', 1, 2, 2012, array('tags' => array('one', 'two')))
            ->withPost('post3', 1, 3, 2012, array('tags' => array('one')))
            ->withPost('post4', 1, 4, 2012, array('tags' => array('two')))
            ->withPage(
                'test',
                array('layout' => 'none'),
                <<<EOD
{% for t in blog.tags %}
TAG: {{t}} / {{t.name}}
LINK: {{pctagurl(t)}}
{% for p in t.posts %}
* {{p.slug}}
{% endfor %}
{% endfor %}
EOD
            );
        $app = $fs->getApp();
        $page = Page::createFromUri($app, '/test');
        $actual = $page->getContentSegment();
        $expected = <<<EOD
TAG: one / one
LINK: localhost/tag/one
* 2012/03/01/post3
* 2012/02/01/post2
* 2012/01/01/post1
TAG: two / two
LINK: localhost/tag/two
* 2012/04/01/post4
* 2012/02/01/post2

EOD;
        $this->assertEquals($expected, $actual);
    }

    protected function setUp()
    {
        $this->oldLocale = setlocale(LC_ALL, '0');
    }

    protected function tearDown()
    {
        setlocale(LC_ALL, $this->oldLocale);
    }
}
