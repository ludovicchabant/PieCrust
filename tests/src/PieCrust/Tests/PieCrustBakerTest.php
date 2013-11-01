<?php

namespace PieCrust\Tests;

use PieCrust\PieCrust;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Util\UriBuilder;


class PieCrustBakerTest extends PieCrustTestCase
{
    public function testEmptyBake()
    {
        $fs = MockFileSystem::create();

        $this->assertFalse(is_dir($fs->url('kitchen/_counter')));
        $app = $fs->getApp(array('cache' => false));
        $baker = new PieCrustBaker($app);
        $baker->bake();

        $structure = $fs->getStructure();
        $counter = $structure[$fs->getRootName()]['kitchen']['_counter'];
        $this->assertEquals(
            array('index.html'),
            $this->getVfsEntries($counter)
        );
    }

    public function testSimpleBake()
    {
        $fs = MockFileSystem::create()
            ->withPost('post1', 1, 1, 2010, array(), 'POST ONE')
            ->withPage('foo', array(), "Something");
        $app = $fs->getApp(array('cache' => false));
        $baker = new PieCrustBaker($app);
        $baker->bake();

        $structure = $fs->getStructure();
        $counter = $structure[$fs->getRootName()]['kitchen']['_counter'];
        $this->assertEquals(
            array('2010', 'index.html', 'foo.html'),
            $this->getVfsEntries($counter)
        );
    }

    public function testPostsWithOverridenDates()
    {
        $fs = MockFileSystem::create();
        $fs->withTemplate('default', '');
        $fs->withTemplate(
            'post',
            '/{{ page.date }}/ {{ content }}'
        );
        $fs->withPost(
            'test1', 
            5, 8, 2012, 
            array('date' => '2011-05-03', 'format' => 'none'), 
            'A test with overriden date'
        );
        $app = $fs->getApp(array('cache' => false));
        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertFileExists($fs->url('kitchen/_counter/2012/08/05/test1.html'));
        $expectedContents = '/May 3, 2011/ A test with overriden date';
        $this->assertEquals(
            $expectedContents,
            file_get_contents($fs->url('kitchen/_counter/2012/08/05/test1.html'))
        );
    }

    public function testBakePageWithTagList()
    {
        $templateContents = <<<EOD
{% for t in blog.tags %}
{{ t.name }}, {{ t.post_count }}
{% endfor %}
{{ content|raw }}
EOD;

        $fs = MockFileSystem::create();
        $fs->withTemplate(
            'post',
            $templateContents);
        $fs->withTemplate(
            'default',
            '');
        $fs->withPage(
            'foo',
            array('layout' => 'none', 'format' => 'none'),
            'FOO!');
        $fs->withPost(
            'first-post', 1, 1, 2010,
            array('format' => 'none', 'tags' => array('blah')),
            'First post.');
        $fs->withPost(
            'second-post', 1, 1, 2011,
            array('format' => 'none', 'tags' => array('blah')),
            'Second post: {{ assets.bar }}');
        $fs->withAsset('_content/posts/2011-01-01_second-post-assets/bar.jpg', 'BAR');
        $fs->withPost(
            'third-post', 1, 1, 2012,
            array('format' => 'none', 'tags' => array('blah')),
            'Third post.');

        $app = new PieCrust(array(
            'cache' => false, 
            'root' => $fs->url('kitchen'))
        );
        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertFileExists(
            $fs->url('kitchen/_counter/2010/01/01/first-post.html')
        );
        $this->assertFileExists(
            $fs->url('kitchen/_counter/2011/01/01/second-post.html')
        );
        $this->assertFileExists(
            $fs->url('kitchen/_counter/2012/01/01/third-post.html')
        );
        $this->assertFileExists(
            $fs->url('kitchen/_counter/2011/01/01/second-post/bar.jpg')
        );

        $this->assertEquals(
            "blah, 3\nFirst post.",
            file_get_contents($fs->url('kitchen/_counter/2010/01/01/first-post.html'))
        );
        $this->assertEquals(
            "blah, 3\nSecond post: /2011/01/01/second-post/bar.jpg",
            file_get_contents($fs->url('kitchen/_counter/2011/01/01/second-post.html'))
        );
        $this->assertEquals(
            "blah, 3\nThird post.",
            file_get_contents($fs->url('kitchen/_counter/2012/01/01/third-post.html'))
        );
    }

    public function testBakePageWithSeveralPostsInTheSameDay()
    {
        $indexContents = <<<EOD
{% for p in pagination.posts %}
{{ p.content|raw }}
{% endfor %}
EOD;

        $fs = MockFileSystem::create();
        $fs->withConfig(array('site' => array('default_format' => 'none')));
        $fs->withTemplate('default', '');
        $fs->withTemplate('post', <<<EOD
{{content|raw}}
Prev: {{pagination.prev_post.url}}
Next: {{pagination.next_post.url}}
EOD
        );
        $fs->withPage(
            'foo',
            array('layout' => 'none'),
            $indexContents);
        $fs->withPost(
            'before-post', 2, 1, 2012,
            array(),
            'Before...');
        $fs->withPost(
            'z-first-post', 4, 1, 2012,
            array('time' => '08:50'),
            'First post.');
        $fs->withPost(
            'a-second-post', 4, 1, 2012,
            array('time' => '12:30'),
            'Second post.');
        $fs->withPost(
            'b-third-post', 4, 1, 2012,
            array('time' => '17:05'),
            'Third post.');
        $fs->withPost(
            'after-post', 12, 1, 2012,
            array(),
            'After...');

        $app = new PieCrust(array(
            'cache' => false, 
            'root' => $fs->url('kitchen'))
        );

        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertFileExists($fs->url('kitchen/_counter/foo.html'));
        $this->assertEquals(
            "After...\nThird post.\nSecond post.\nFirst post.\nBefore...\n",
            file_get_contents($fs->url('kitchen/_counter/foo.html'))
        );

        $this->assertEquals(
            "Before...\nPrev: \nNext: /2012/01/04/z-first-post.html",
            file_get_contents($fs->url('kitchen/_counter/2012/01/02/before-post.html'))
        );
        $this->assertEquals(
            "First post.\nPrev: /2012/01/02/before-post.html\nNext: /2012/01/04/a-second-post.html",
            file_get_contents($fs->url('kitchen/_counter/2012/01/04/z-first-post.html'))
        );
        $this->assertEquals(
            "Second post.\nPrev: /2012/01/04/z-first-post.html\nNext: /2012/01/04/b-third-post.html",
            file_get_contents($fs->url('kitchen/_counter/2012/01/04/a-second-post.html'))
        );
        $this->assertEquals(
            "Third post.\nPrev: /2012/01/04/a-second-post.html\nNext: /2012/01/12/after-post.html",
            file_get_contents($fs->url('kitchen/_counter/2012/01/04/b-third-post.html'))
        );
        $this->assertEquals(
            "After...\nPrev: /2012/01/04/b-third-post.html\nNext: ",
            file_get_contents($fs->url('kitchen/_counter/2012/01/12/after-post.html'))
        );
    }

    public function testBakePageWithSeveralPostsInTheSameDayFiltered()
    {
        $indexContents = <<<EOD
---
layout: none
posts_filters:
    is_category: blah
---
{% for p in pagination.posts %}
{{ p.content|raw }}
{% endfor %}
EOD;

        $fs = MockFileSystem::create();
        $fs->withConfig(array('site' => array('default_format' => 'none')));
        $fs->withTemplate('default', '');
        $fs->withAsset('_content/pages/foo.html', $indexContents);
        $fs->withPost(
            'z-first-post', 1, 1, 2012,
            array('layout' => 'none', 'category' => 'blah', 'time' => '08:50'),
            'First post.');
        $fs->withPost(
            'a-second-post', 1, 1, 2012,
            array('layout' => 'none', 'time' => '12:30'),
            'Second post.');
        $fs->withPost(
            'b-third-post', 1, 1, 2012,
            array('layout' => 'none', 'category' => 'blah', 'time' => '17:05:32'),
            'Third post.');

        $app = new PieCrust(array(
            'cache' => false, 
            'root' => $fs->url('kitchen'))
        );

        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertFileExists($fs->url('kitchen/_counter/foo.html'));
        $this->assertEquals(
            "Third post.\nFirst post.\n",
            file_get_contents($fs->url('kitchen/_counter/foo.html'))
        );
    }

    public function bakePortableUrlsDataProvider()
    {
        return array(
            array(false, false, 'foo', './', './something/blah.html'),
            array(false, false, 'one/foo', '../', '../something/blah.html'),
            array(false, false, 'one/two/foo', '../../', '../../something/blah.html'),
            array(false, false, 'something/foo', '../', '../something/blah.html'),
            array(false, false, 'something/sub/foo', '../../', '../../something/blah.html'),

            array(true, true, 'foo', '../', '../something/blah/'),
            array(true, true, 'one/foo', '../../', '../../something/blah/'),
            array(true, true, 'one/two/foo', '../../../', '../../../something/blah/'),
            array(true, true, 'something/foo', '../../', '../../something/blah/'),
            array(true, true, 'something/sub/foo', '../../../', '../../../something/blah/'),

            array(true, false, 'foo', '../', '../something/blah'),
            array(true, false, 'one/foo', '../../', '../../something/blah'),
            array(true, false, 'one/two/foo', '../../../', '../../../something/blah'),
            array(true, false, 'something/foo', '../../', '../../something/blah'),
            array(true, false, 'something/sub/foo', '../../../', '../../../something/blah')
        );
    }

    /**
     * @dataProvider bakePortableUrlsDataProvider
     */
    public function testBakePortableUrls($prettyUrls, $trailingSlash, $url, $expectedSiteRoot, $expectedBlah)
    {
        $contents = <<<EOD
Root: {{ site.root }}
Blah: {{ pcurl('something/blah') }}
EOD;

        $fs = MockFileSystem::create();
        $fs->withTemplate('default', '');
        $fs->withPage(
            'something/blah',
            array('layout' => 'none', 'format' => 'none'),
            'BLAH'
        );
        $fs->withPage(
            $url,
            array('layout' => 'none', 'format' => 'none'),
            $contents
        );

        $app = new PieCrust(array(
            'cache' => false, 
            'root' => $fs->url('kitchen'))
        );
        $app->getConfig()->setValue('site/pretty_urls', $prettyUrls);
        $app->getConfig()->setValue('baker/portable_urls', true);
        if ($trailingSlash)
            $app->getConfig()->setValue('baker/trailing_slash', true);

        $savedSiteRoot = $app->getConfig()->getValue('site/root');
        $baker = new PieCrustBaker($app);
        $baker->bake();
        $this->assertEquals(
            $savedSiteRoot,
            $app->getConfig()->getValue('site/root')
        );

        $blahPath = 'blah.html';
        if ($prettyUrls)
            $blahPath = 'blah/index.html';
        $this->assertFileExists($fs->url('kitchen/_counter/something/' . $blahPath));
        $this->assertEquals(
            "BLAH",
            file_get_contents($fs->url('kitchen/_counter/something/' . $blahPath))
        );

        $expectedContents = <<<EOD
Root: {$expectedSiteRoot}
Blah: {$expectedBlah}
EOD;
        $urlPath = $url . '.html';
        if ($prettyUrls)
            $urlPath = $url . '/index.html';
        $this->assertFileExists($fs->url('kitchen/_counter/' . $urlPath));
        $this->assertEquals(
            $expectedContents,
            file_get_contents($fs->url('kitchen/_counter/' . $urlPath))
        );
    }

    public function urlFormatsDataProvider()
    {
        return array(
            array(
                false,
                false,
                <<<'EOD'
Normal: /normal.html
Normal in folder: /somewhere/normal.html
Ext: /foo.ext
Ext in folder: /somewhere/foo.ext
EOD
            ),
            array(
                true,
                false,
                <<<'EOD'
Normal: /normal
Normal in folder: /somewhere/normal
Ext: /foo.ext
Ext in folder: /somewhere/foo.ext
EOD
            ),
            array(
                true,
                true,
                <<<'EOD'
Normal: /normal/
Normal in folder: /somewhere/normal/
Ext: /foo.ext
Ext in folder: /somewhere/foo.ext
EOD
            )
        );
    }

    /**
     * @dataProvider urlFormatsDataProvider
     */
    public function testUrlFormats($prettyUrls, $trailingSlash, $expectedContents)
    {
        $fs = MockFileSystem::create();
        $fs->withTemplate('default', '');
        $fs->withPage(
            'test_page',
            array('layout' => 'none', 'format' => 'none'),
            <<<'EOD'
Normal: {{pcurl('normal')}}
Normal in folder: {{pcurl('somewhere/normal')}}
Ext: {{pcurl('foo.ext')}}
Ext in folder: {{pcurl('somewhere/foo.ext')}}
EOD
        );
        $fs->withPage(
            'other_page.foo',
            array('layout' => 'none', 'format' => 'none'),
            "THIS IS FOO!"
        );

        $app = new PieCrust(array(
            'cache' => false, 
            'root' => $fs->url('kitchen'))
        );
        $app->getConfig()->setValue('site/pretty_urls', $prettyUrls);
        if ($trailingSlash)
            $app->getConfig()->setValue('baker/trailing_slash', true);
        $baker = new PieCrustBaker($app);
        $baker->bake();

        $otherPagePath = 'other_page.foo';
        if ($prettyUrls)
            $otherPagePath = 'other_page.foo/index.html';
        $this->assertFileExists($fs->url('kitchen/_counter/' . $otherPagePath));
        $this->assertEquals(
            "THIS IS FOO!",
            file_get_contents($fs->url('kitchen/_counter/' . $otherPagePath))
        );

        $fileName = $prettyUrls ? 'test_page/index.html' : 'test_page.html';
        $this->assertFileExists($fs->url('kitchen/_counter/' . $fileName));
        $this->assertEquals(
            $expectedContents,
            file_get_contents($fs->url('kitchen/_counter/' . $fileName))
        );
    }

    public function testBakeWithTheme()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none')))
            ->withAsset('_content/templates/default.html', '{{content|raw}}')
            ->withAsset('_content/pages/first-page.html', 'FIRST')
            ->withAsset('_content/pages/second-page.html', 'SECOND (OVERRIDE)')
            ->withAsset('_content/theme/_content/pages/second-page.html', 'SECOND')
            ->withAsset('_content/theme/_content/pages/theme-page.html', 'THEME')
            ->withAsset('normal.css', 'normal')
            ->withAsset('extra.css', 'extra (override)')
            ->withAsset('_content/theme/extra.css', 'extra')
            ->withAsset('_content/theme/special.css', 'special')
            ->withAsset('_content/theme/_content/theme_config.yml', '');

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $this->assertFileExists($fs->url('counter/first-page.html'));
        $this->assertEquals('FIRST', file_get_contents($fs->url('counter/first-page.html')));
        $this->assertFileExists($fs->url('counter/second-page.html'));
        $this->assertEquals('SECOND (OVERRIDE)', file_get_contents($fs->url('counter/second-page.html')));
        $this->assertFileExists($fs->url('counter/theme-page.html'));
        $this->assertEquals('THEME', file_get_contents($fs->url('counter/theme-page.html')));
        $this->assertFileExists($fs->url('counter/normal.css'));
        $this->assertEquals('normal', file_get_contents($fs->url('counter/normal.css')));
        $this->assertFileExists($fs->url('counter/extra.css'));
        $this->assertEquals('extra (override)', file_get_contents($fs->url('counter/extra.css')));
        $this->assertFileExists($fs->url('counter/special.css'));
        $this->assertEquals('special', file_get_contents($fs->url('counter/special.css')));
    }

    public function bakeTagPagesDataProvider()
    {
        return array(
            array('foo', 'bar', 'foo', 'bar', 'foo', 'bar'),
            array('foo zoo', 'bar', 'foo-zoo', 'bar', 'foo-zoo', 'bar'),
            array('foo zoo', 'bar baz', 'foo-zoo', 'bar-baz', 'foo-zoo', 'bar-baz'),
            array('foo/zoo', 'bar', 'foo-zoo', 'bar', 'foo-zoo', 'bar'),
            array('foo+zoo', 'bar', 'foo-zoo', 'bar', 'foo-zoo', 'bar'),
            array('foo-zoo', 'bar', 'foo-zoo', 'bar', 'foo-zoo', 'bar'),
            array('foo_zoo', 'bar', 'foo_zoo', 'bar', 'foo_zoo', 'bar'),
            array('.htaccess', 'functions.php', 'htaccess', 'functions-php', 'htaccess', 'functions-php'),
            array('épatant', 'bar', 'epatant', 'bar', 'epatant', 'bar'),
            array('épaTANT', 'bar', 'epatant', 'bar', 'epatant', 'bar'),
            array('épatant gâteau', 'bar', 'epatant-gateau', 'bar', 'epatant-gateau', 'bar'),
            array('épatant', 'bar', 'épatant', 'bar', '%C3%A9patant', 'bar', 'encode'),
            array('épatant gâteau', 'bar', 'épatant-gâteau', 'bar', '%C3%A9patant-g%C3%A2teau', 'bar', 'encode'),
            array('Разное', 'bar', 'Разное', 'bar', 'Разное', 'bar', 'transliterate'),
            array('Разное', 'bar', 'Разное', 'bar', '%D0%A0%D0%B0%D0%B7%D0%BD%D0%BE%D0%B5', 'bar', 'encode'),
            array('Это тэг', 'bar', 'Это-тэг', 'bar', 'Это-тэг', 'bar', 'transliterate'),
            array('Это тэг', 'bar', 'Это-тэг', 'bar', '%D0%AD%D1%82%D0%BE-%D1%82%D1%8D%D0%B3', 'bar', 'encode'),
            array('Тест', 'bar', 'Тест', 'bar', 'Тест', 'bar', 'transliterate'),
            array('Тест', 'bar', 'Тест', 'bar', '%D0%A2%D0%B5%D1%81%D1%82', 'bar', 'encode')
        );
    }

    /**
     * @dataProvider bakeTagPagesDataProvider
     */
    public function testBakeTagPages($tag1, $tag2, $fileName1, $fileName2, $slug1, $slug2, $slugify = false)
    {
        $config = array('site' => array(
            'default_format' => 'none',
            'posts_per_page' => 3
        ));
        if ($slugify !== false)
            $config['site']['slugify'] = $slugify;
        $fs = MockFileSystem::create()
            ->withConfig($config)
            ->withTemplate('default', '')
            ->withTemplate('post', '{{content|raw}}')
            ->withPage(
                '_tag', 
                array('layout' => 'none'),
                <<<EOD
TAG: {{tag}}
URI: {{page.url}}
{% for post in pagination.posts %}
{{ post.content|raw }}
{% endfor %}
EOD
            )
            ->withPost('post1', 1, 1, 2010, array('tags' => array($tag1)), 'POST ONE')
            ->withPost('post2', 2, 1, 2010, array('tags' => array($tag1)), 'POST TWO')
            ->withPost('post3', 3, 1, 2010, array('tags' => array($tag2)), 'POST THREE')
            ->withPost('post4', 4, 1, 2010, array('tags' => array($tag2)), 'POST FOUR')
            ->withPost('post5', 5, 1, 2010, array('tags' => array($tag1, $tag2)), 'POST FIVE')
            ->withPost('post6', 6, 1, 2010, array('tags' => array($tag1, $tag2)), 'POST SIX')
            ->withPost('post7', 7, 1, 2010, array('tags' => array($tag1)), 'POST SEVEN')
            ->withPost('post8', 8, 1, 2010, array('tags' => array($tag1)), 'POST EIGHT')
            ->withPost('post9', 9, 1, 2010, array('tags' => array($tag1)), 'POST NINE')
            ->withPost('post10', 10, 1, 2010, array('tags' => array($tag1)), 'POST TEN')
            ->withPost('post11', 11, 1, 2010, array('tags' => array($tag1)), 'POST ELEVEN')
            ->withPost('post12', 12, 1, 2010, array('tags' => array($tag2)), 'POST TWELVE');

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $tagFileNames = array(
            $fileName1,
            $fileName1.'.html',
            $fileName2,
            $fileName2.'.html'
        );
        sort($tagFileNames);
        $actual = $fs->getStructure();
        $actual = array_keys($actual[$fs->getRootName()]['counter']['tag']);
        sort($actual);
        $this->assertEquals($tagFileNames, $actual);

        $this->assertEquals(
            "TAG: {$tag1}\nURI: /tag/{$slug1}.html\nPOST ELEVEN\nPOST TEN\nPOST NINE\n", 
            file_get_contents($fs->url('counter/tag/'.$fileName1.'.html'))
        );
        $this->assertEquals(
            "TAG: {$tag1}\nURI: /tag/{$slug1}.html\nPOST EIGHT\nPOST SEVEN\nPOST SIX\n", 
            file_get_contents($fs->url('counter/tag/'.$fileName1.'/2.html'))
        );
        $this->assertEquals(
            "TAG: {$tag1}\nURI: /tag/{$slug1}.html\nPOST FIVE\nPOST TWO\nPOST ONE\n", 
            file_get_contents($fs->url('counter/tag/'.$fileName1.'/3.html'))
        );

        $this->assertEquals(
            "TAG: {$tag2}\nURI: /tag/{$slug2}.html\nPOST TWELVE\nPOST SIX\nPOST FIVE\n", 
            file_get_contents($fs->url('counter/tag/'.$fileName2.'.html'))
        );
        $this->assertEquals(
            "TAG: {$tag2}\nURI: /tag/{$slug2}.html\nPOST FOUR\nPOST THREE\n", 
            file_get_contents($fs->url('counter/tag/'.$fileName2.'/2.html'))
        );
    }

    public function testBakeTagPagesForMultipleBlogsWithThemeListing()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none', 'blogs' => array('one', 'two'))))
            ->withTemplate('default', '')
            ->withTemplate('post', '{{content|raw}}')
            ->withThemeConfig(array())
            ->withAsset(
                '_content/theme/_content/pages/_tag.html', 
                <<<EOD
---
layout: none
---
{% for post in pagination.posts %}
THEME: {{ post.content|raw }}
{% endfor %}
EOD
            )
            ->withPost('post1', 1, 1, 2010, array('tags' => array('foo')), '1/POST ONE', 'one')
            ->withPost('post2', 2, 1, 2010, array('tags' => array('foo')), '1/POST TWO', 'one')
            ->withPost('post3', 3, 1, 2010, array('tags' => array('bar')), '1/POST THREE', 'one')
            ->withPost('post4', 4, 1, 2010, array('tags' => array('bar')), '1/POST FOUR', 'one')
            ->withPost('post5', 5, 1, 2010, array('tags' => array('foo', 'bar')), '1/POST FIVE', 'one')
            ->withPost('post1', 1, 1, 2010, array('tags' => array('foo')), '2/POST ONE', 'two')
            ->withPost('post2', 2, 1, 2010, array('tags' => array('foo', 'bar')), '2/POST TWO', 'two')
            ->withPost('post3', 3, 1, 2010, array('tags' => array('foo')), '2/POST THREE', 'two')
            ->withPost('post4', 4, 1, 2010, array('tags' => array('bar')), '2/POST FOUR', 'two')
            ->withPost('post5', 5, 1, 2010, array('tags' => array('foo', 'bar')), '2/POST FIVE', 'two');

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $this->assertEquals(
            "THEME: 1/POST FIVE\nTHEME: 1/POST TWO\nTHEME: 1/POST ONE\n", 
            file_get_contents($fs->url('counter/one/tag/foo.html'))
        );
        $this->assertEquals(
            "THEME: 1/POST FIVE\nTHEME: 1/POST FOUR\nTHEME: 1/POST THREE\n", 
            file_get_contents($fs->url('counter/one/tag/bar.html'))
        );

        $this->assertEquals(
            "THEME: 2/POST FIVE\nTHEME: 2/POST THREE\nTHEME: 2/POST TWO\nTHEME: 2/POST ONE\n", 
            file_get_contents($fs->url('counter/two/tag/foo.html'))
        );
        $this->assertEquals(
            "THEME: 2/POST FIVE\nTHEME: 2/POST FOUR\nTHEME: 2/POST TWO\n", 
            file_get_contents($fs->url('counter/two/tag/bar.html'))
        );
    }

    public function bakeCategoryPageDataProvider()
    {
        return array(
            array('foo', 'bar', 'foo', 'bar', 'foo', 'bar'),
            array('foo zoo', 'bar', 'foo-zoo', 'bar', 'foo-zoo', 'bar'),
            array('foo zoo', 'bar baz', 'foo-zoo', 'bar-baz', 'foo-zoo', 'bar-baz'),
            array('foo/zoo', 'bar', 'foo-zoo', 'bar', 'foo-zoo', 'bar'),
            array('foo+zoo', 'bar', 'foo-zoo', 'bar', 'foo-zoo', 'bar'),
            array('foo-zoo', 'bar', 'foo-zoo', 'bar', 'foo-zoo', 'bar'),
            array('foo_zoo', 'bar', 'foo_zoo', 'bar', 'foo_zoo', 'bar'),
            array('épatant', 'bar', 'epatant', 'bar', 'epatant', 'bar'),
            array('épaTANT', 'bar', 'epatant', 'bar', 'epatant', 'bar'),
            array('épatant gâteau', 'bar', 'epatant-gateau', 'bar', 'epatant-gateau', 'bar'),
            array('épatant', 'bar', 'épatant', 'bar', '%C3%A9patant', 'bar', 'encode'),
            array('épatant gâteau', 'bar', 'épatant-gâteau', 'bar', '%C3%A9patant-g%C3%A2teau', 'bar', 'encode'),
            array('Разное', 'bar', 'Разное', 'bar', 'Разное', 'bar', 'transliterate'),
            array('Разное', 'bar', 'Разное', 'bar', '%D0%A0%D0%B0%D0%B7%D0%BD%D0%BE%D0%B5', 'bar', 'encode'),
            array('Это тэг', 'bar', 'Это-тэг', 'bar', 'Это-тэг', 'bar', 'transliterate'),
            array('Это тэг', 'bar', 'Это-тэг', 'bar', '%D0%AD%D1%82%D0%BE-%D1%82%D1%8D%D0%B3', 'bar', 'encode'),
            array('Тест', 'bar', 'Тест', 'bar', 'Тест', 'bar', 'transliterate'),
            array('Тест', 'bar', 'Тест', 'bar', '%D0%A2%D0%B5%D1%81%D1%82', 'bar', 'encode')
        );
    }

    /**
     * @dataProvider bakeCategoryPageDataProvider
     */
    public function testBakeCategoryPage($cat1, $cat2, $fileName1, $fileName2, $slug1, $slug2, $slugify = false)
    {
        $config = array(
            'site' => array('default_format' => 'none'),
            'blog' => array('category_url' => '/category/%category%')
        );
        if ($slugify !== false)
            $config['site']['slugify'] = $slugify;
        $fs = MockFileSystem::create()
            ->withConfig($config)
            ->withTemplate('default', '')
            ->withTemplate('post', '{{content|raw}}')
            ->withPage(
                '_category', 
                array('layout' => 'none'),
                <<<EOD
CATEGORY: {{category}}
URI: {{page.url}}
{% for post in pagination.posts %}
{{ post.content|raw }}
{% endfor %}
EOD
            )
            ->withPost('post1', 1, 1, 2010, array('category' => $cat1), 'POST ONE')
            ->withPost('post2', 2, 1, 2010, array('category' => $cat1), 'POST TWO')
            ->withPost('post3', 3, 1, 2010, array('category' => $cat2), 'POST THREE')
            ->withPost('post4', 4, 1, 2010, array('category' => $cat2), 'POST FOUR')
            ->withPost('post5', 5, 1, 2010, array('category' => $cat1), 'POST FIVE');

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $categoryFileNames = array($fileName1.'.html', $fileName2.'.html');
        sort($categoryFileNames);
        $actual = $fs->getStructure();
        $actual = array_keys($actual[$fs->getRootName()]['counter']['category']);
        sort($actual);
        $this->assertEquals($categoryFileNames, $actual);

        $this->assertEquals(
            "CATEGORY: {$cat1}\nURI: /category/{$slug1}\nPOST FIVE\nPOST TWO\nPOST ONE\n", 
            file_get_contents($fs->url('counter/category/'.$fileName1.'.html'))
        );
        $this->assertEquals(
            "CATEGORY: {$cat2}\nURI: /category/{$slug2}\nPOST FOUR\nPOST THREE\n", 
            file_get_contents($fs->url('counter/category/'.$fileName2.'.html'))
        );
    }

    public function testBakeCategoryPageForMultipleBlogsWithThemeListing()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none', 'blogs' => array('one', 'two'))))
            ->withTemplate('default', '')
            ->withTemplate('post', '{{content|raw}}')
            ->withThemeConfig(array())
            ->withAsset(
                '_content/theme/_content/pages/_category.html', 
                <<<EOD
---
layout: none
---
{% for post in pagination.posts %}
THEME: {{ post.content|raw }}
{% endfor %}
EOD
            )
            ->withPost('post1', 1, 1, 2010, array('category' => 'foo'), '1/POST ONE', 'one')
            ->withPost('post2', 2, 1, 2010, array('category' => 'foo'), '1/POST TWO', 'one')
            ->withPost('post3', 3, 1, 2010, array('category' => 'bar'), '1/POST THREE', 'one')
            ->withPost('post4', 4, 1, 2010, array('category' => 'bar'), '1/POST FOUR', 'one')
            ->withPost('post5', 5, 1, 2010, array('category' => 'foo'), '1/POST FIVE', 'one')
            ->withPost('post1', 1, 1, 2010, array('category' => 'foo'), '2/POST ONE', 'two')
            ->withPost('post2', 2, 1, 2010, array('category' => 'foo'), '2/POST TWO', 'two')
            ->withPost('post3', 3, 1, 2010, array('category' => 'bar'), '2/POST THREE', 'two')
            ->withPost('post4', 4, 1, 2010, array('category' => 'bar'), '2/POST FOUR', 'two')
            ->withPost('post5', 5, 1, 2010, array('category' => 'foo'), '2/POST FIVE', 'two');

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $this->assertEquals(
            "THEME: 1/POST FIVE\nTHEME: 1/POST TWO\nTHEME: 1/POST ONE\n", 
            file_get_contents($fs->url('counter/one/foo.html'))
        );
        $this->assertEquals(
            "THEME: 1/POST FOUR\nTHEME: 1/POST THREE\n", 
            file_get_contents($fs->url('counter/one/bar.html'))
        );

        $this->assertEquals(
            "THEME: 2/POST FIVE\nTHEME: 2/POST TWO\nTHEME: 2/POST ONE\n", 
            file_get_contents($fs->url('counter/two/foo.html'))
        );
        $this->assertEquals(
            "THEME: 2/POST FOUR\nTHEME: 2/POST THREE\n", 
            file_get_contents($fs->url('counter/two/bar.html'))
        );
    }

    public function testBakeMultiLoopPage()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none')))
            ->withTemplate('default', '{{content|raw}}')
            ->withTemplate('post', '{{content|raw}}')
            ->withPost('post1', 1, 1, 2010, array('category' => 'news'), 'NEWS: POST ONE')
            ->withPost('post2', 5, 2, 2010, array('category' => 'misc'), 'MISC: POST ONE')
            ->withPost('post3', 10, 3, 2010, array('category' => 'news'), 'NEWS: POST TWO')
            ->withPost('post4', 1, 1, 2011, array('category' => 'product'), 'PRODUCT ONE')
            ->withPost('post5', 5, 2, 2011, array('category' => 'news'), 'NEWS: POST THREE')
            ->withPost('post6', 10, 3, 2011, array('category' => 'news'), 'NEWS: POST FOUR')
            ->withPost('post7', 15, 3, 2011, array('category' => 'misc'), 'MISC: POST TWO')
            ->withPost('post8', 1, 1, 2012, array('category' => 'product'), 'PRODUCT TWO')
            ->withPost('post9', 5, 2, 2012, array('category' => 'product'), 'PRODUCT THREE')
            ->withPost('post10', 1, 1, 2013, array('category' => 'news'), 'NEWS: POST FIVE')
            ->withPage('foo', array(), <<<EOD
NEWS:
{% for p in blog.posts.in_category('news').limit(3) %}
{{p.content}}
{% endfor %}
MISC:
{% for p in blog.posts.in_category('misc').limit(3) %}
{{p.content}}
{% endfor %}
PRODUCTS:
{% for p in blog.posts.in_category('product').limit(3) %}
{{p.content}}
{% endfor %}
EOD
            );

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $expected = <<<EOD
NEWS:
NEWS: POST FIVE
NEWS: POST FOUR
NEWS: POST THREE
MISC:
MISC: POST TWO
MISC: POST ONE
PRODUCTS:
PRODUCT THREE
PRODUCT TWO
PRODUCT ONE

EOD
        ;

        $this->assertEquals(
            $expected,
            file_get_contents($fs->url('counter/foo.html'))
        );
    }

    public function testBakeBlogArchive()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none')))
            ->withTemplate('default', '{{content|raw}}')
            ->withTemplate('post', '{{content|raw}}')
            ->withPost('post1', 1, 1, 2010, array(), 'POST ONE')
            ->withPost('post2', 5, 2, 2010, array(), 'POST TWO')
            ->withPost('post3', 10, 3, 2010, array(), 'POST THREE')
            ->withPost('post4', 1, 1, 2011, array(), 'POST FOUR')
            ->withPost('post5', 5, 2, 2011, array(), 'POST FIVE')
            ->withPost('post6', 10, 3, 2011, array(), 'POST SIX')
            ->withPost('post7', 15, 3, 2011, array(), 'POST SEVEN')
            ->withPost('post8', 1, 1, 2012, array(), 'POST EIGHT')
            ->withPost('post9', 5, 2, 2012, array(), 'POST NINE')
            ->withPost('post10', 1, 1, 2013, array(), 'POST TEN')
            ->withPage('archive', array(), <<<EOD
{% for p in blog.posts %}
{{p.url}}: {{p.content}}
{% endfor %}
EOD
            )
            ->withPage('yearly', array(), <<<EOD
{% for y in blog.years %}
# {{y.name}} ({{y.post_count}})
{% for p in y.posts %}
{{p.url}}: {{p.content}}
{% endfor %}
{% endfor %}
EOD
            )
            ->withPage('monthly', array(), <<<EOD
{% for m in blog.months %}
# {{m.name}} ({{m.post_count}})
{% for p in m.posts %}
{{p.url}}: {{p.content}}
{% endfor %}
{% endfor %}
EOD
            );

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $this->assertEquals(
            <<<EOD
/2013/01/01/post10.html: POST TEN
/2012/02/05/post9.html: POST NINE
/2012/01/01/post8.html: POST EIGHT
/2011/03/15/post7.html: POST SEVEN
/2011/03/10/post6.html: POST SIX
/2011/02/05/post5.html: POST FIVE
/2011/01/01/post4.html: POST FOUR
/2010/03/10/post3.html: POST THREE
/2010/02/05/post2.html: POST TWO
/2010/01/01/post1.html: POST ONE

EOD
            ,
            file_get_contents($fs->url('counter/archive.html'))
        );

        $this->assertEquals(
            <<<EOD
# 2013 (1)
/2013/01/01/post10.html: POST TEN
# 2012 (2)
/2012/02/05/post9.html: POST NINE
/2012/01/01/post8.html: POST EIGHT
# 2011 (4)
/2011/03/15/post7.html: POST SEVEN
/2011/03/10/post6.html: POST SIX
/2011/02/05/post5.html: POST FIVE
/2011/01/01/post4.html: POST FOUR
# 2010 (3)
/2010/03/10/post3.html: POST THREE
/2010/02/05/post2.html: POST TWO
/2010/01/01/post1.html: POST ONE

EOD
            ,
            file_get_contents($fs->url('counter/yearly.html'))
        );
        $this->assertEquals(
             <<<EOD
# January 2013 (1)
/2013/01/01/post10.html: POST TEN
# February 2012 (1)
/2012/02/05/post9.html: POST NINE
# January 2012 (1)
/2012/01/01/post8.html: POST EIGHT
# March 2011 (2)
/2011/03/15/post7.html: POST SEVEN
/2011/03/10/post6.html: POST SIX
# February 2011 (1)
/2011/02/05/post5.html: POST FIVE
# January 2011 (1)
/2011/01/01/post4.html: POST FOUR
# March 2010 (1)
/2010/03/10/post3.html: POST THREE
# February 2010 (1)
/2010/02/05/post2.html: POST TWO
# January 2010 (1)
/2010/01/01/post1.html: POST ONE

EOD
            ,
            file_get_contents($fs->url('counter/monthly.html'))
        );
    }

    public function testFilteredLinker()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none')))
            ->withTemplate('default', '{{content|raw}}')
            ->withPage('foo1', array('category' => 'foos', 'tags' => array('a', 'b')), '')
            ->withPage('foo2', array('category' => 'foos', 'tags' => array('b')), '')
            ->withPage('bar1', array('category' => 'bars', 'tags' => array('a')), '')
            ->withPage('bar2', array('category' => 'bars', 'tags' => array('b')), '')
            ->withPage('bar3', array('category' => 'bars'), '')
            ->withAsset('_content/pages/by_category.html',
                <<<EOD
---
foos:
    is_category: foos
bars:
    is_category: bars
---
# FOOS
{% for p in site.pages.filter('foos') %}
{{p.url}}
{% endfor %}
# BARS
{% for p in site.pages.filter('bars') %}
{{p.url}}
{% endfor %}
EOD
            )
            ->withAsset('_content/pages/by_tag.html',
                <<<EOD
---
tag_a:
    has_tags: a
tag_b:
    has_tags: b
both:
    and:
        -
          has_tags: a
        -
          has_tags: b
---
# TAG A
{% for p in site.pages.filter('tag_a') %}
{{p.url}}
{% endfor %}
# TAG B
{% for p in site.pages.filter('tag_b') %}
{{p.url}}
{% endfor %}
# BOTH TAGS
{% for p in site.pages.filter('both') %}
{{p.url}}
{% endfor %}
EOD
            );

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $this->assertEquals(
             <<<EOD
# FOOS
/foo1.html
/foo2.html
# BARS
/bar1.html
/bar2.html
/bar3.html

EOD
            ,
            file_get_contents($fs->url('counter/by_category.html'))
        );

        $this->assertEquals(
             <<<EOD
# TAG A
/foo1.html
/bar1.html
# TAG B
/foo1.html
/foo2.html
/bar2.html
# BOTH TAGS
/foo1.html

EOD
            ,
            file_get_contents($fs->url('counter/by_tag.html'))
        );
    }

    public function testMultiTagLink()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('default_format' => 'none')))
            ->withTemplate('default', '{{content|raw}}')
            ->withTemplate('post', '{{content|raw}}')
            ->withPost('post1', 1, 1, 2010, array('tags' => array('one')), 'POST ONE')
            ->withPost('post2', 5, 2, 2010, array('tags' => array('one')), 'POST TWO')
            ->withPost('post3', 10, 3, 2010, array('tags' => array('two')), 'POST THREE')
            ->withPost('post4', 1, 1, 2011, array('tags' => array('three')), 'POST FOUR')
            ->withPost('post5', 5, 2, 2011, array('tags' => array('one', 'two')), 'POST FIVE')
            ->withPost('post6', 10, 3, 2011, array('tags' => array('two', 'three')), 'POST SIX')
            ->withPost('post7', 15, 3, 2011, array('tags' => array('one', 'two')), 'POST SEVEN')
            ->withPost('post8', 1, 1, 2012, array('tags' => array('two', 'three')), 'POST EIGHT')
            ->withPost('post9', 5, 2, 2012, array('tags' => array('one', 'three')), 'POST NINE')
            ->withPost('post10', 1, 1, 2013, array('tags' => array('one', 'two')), 'POST TEN')
            ->withPage('foo', array(), "{{pctagurl(['one','two'])}}")
            ->withPage('_tag', array(), "{{tag}}\n{% for p in pagination.posts %}\n{{p.content}}\n{% endfor %}");

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $structure = $fs->getStructure();
        $counterTag = $structure[$fs->getRootName()]['counter']['tag'];
        $this->assertEquals(
            array('one.html', 'one', 'three.html', 'two.html', 'two'),
            array_keys($counterTag)
        );
        $this->assertEquals(
            array('2.html', 'two.html'),
            array_keys($counterTag['one'])
        );
        $this->assertEquals(
            array('2.html'),
            array_keys($counterTag['two'])
        );

        $this->assertEquals(
            "one + two\nPOST TEN\nPOST SEVEN\nPOST FIVE\n",
            file_get_contents($fs->url('counter/tag/one/two.html'))
        );
    }

    public function testPageDeletion()
    {
        $fs = MockFileSystem::create()
            ->withPage('foo', array(), "Foo")
            ->withPage('bar', array(), "Bar");

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $structure = $fs->getStructure();
        $counter = $structure[$fs->getRootName()]['counter'];
        $this->assertEquals(
            array('index.html', 'foo.html', 'bar.html'),
            $this->getVfsEntries($counter)
        );

        unlink($fs->url('kitchen/_content/pages/bar.html'));
        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $structure = $fs->getStructure();
        $counter = $structure[$fs->getRootName()]['counter'];
        $this->assertEquals(
            array('index.html', 'foo.html'),
            $this->getVfsEntries($counter)
        );
    }

    public function testPageDeletionByPagination()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array(
                'default_format' => 'none',
                'posts_per_page' => 4
            )))
            ->withTemplate('default', '{{content|raw}}')
            ->withTemplate('post', '{{content|raw}}')
            ->withPost('post1', 1, 1, 2010, array(), 'POST ONE')
            ->withPost('post2', 5, 2, 2010, array(), 'POST TWO')
            ->withPost('post3', 10, 3, 2010, array(), 'POST THREE')
            ->withPost('post4', 1, 4, 2010, array(), 'POST FOUR')
            ->withPost('post5', 5, 5, 2010, array(), 'POST FIVE')
            ->withPost('post6', 10, 6, 2010, array(), 'POST SIX')
            ->withPost('post7', 15, 7, 2010, array(), 'POST SEVEN')
            ->withPost('post8', 1, 8, 2010, array(), 'POST EIGHT')
            ->withPost('post9', 5, 9, 2010, array(), 'POST NINE')
            ->withPost('post10', 1, 10, 2010, array(), 'POST TEN')
            ->withPage('_index', array(), <<<EOD
{% for p in pagination.posts %}
{{ p.title }}
{% endfor %}
EOD
            )
            ->withPage('foo', array(), <<<EOD
{% for p in pagination.posts %}
{{ p.title }}
{% endfor %}
EOD
            );

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $structure = $fs->getStructure();
        $counter = $structure[$fs->getRootName()]['counter'];
        $this->assertEquals(
            array('2010', 'index.html', '2.html', '3.html', 'foo.html', 'foo'),
            $this->getVfsEntries($counter)
        );
        $this->assertEquals(
            array('2.html', '3.html'),
            array_keys($counter['foo'])
        );

        $fs->withConfig(array('site' => array(
            'default_format' => 'none',
            'posts_per_page' => 11
        )));
        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->setBakeDir($fs->url('counter'));
        $baker->bake();

        $structure = $fs->getStructure();
        $counter = $structure[$fs->getRootName()]['counter'];
        $this->assertEquals(
            array('2010', 'index.html', 'foo.html', 'foo'),
            $this->getVfsEntries($counter)
        );
        $this->assertEquals(
            array(),
            array_keys($counter['foo'])
        );
    }

    private function getVfsEntries($structure)
    {
        $entries = array_keys($structure);
        if (DIRECTORY_SEPARATOR == '\\')
        {
            // On Windows, there's a bug in PHP or vfsStream that creates
            // an entry called '0' if you're creating a directory with the
            // recursive version of 'mkdir' (passing 'true' as the 3rd parameter).
            $zero = array_search(0, $entries, true);
            if ($zero !== false)
                array_splice($entries, $zero, 1);
        }
        return $entries;
    }
}

