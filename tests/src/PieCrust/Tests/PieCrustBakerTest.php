<?php

use PieCrust\PieCrust;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Mock\MockFileSystem;


class PieCrustBakerTest extends PHPUnit_Framework_TestCase
{
    public function testPostsWithOverridenDates()
    {
        $fs = MockFileSystem::create();
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
            'Second post: {{ asset.bar }}');
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
        $fs->withPage(
            'foo',
            array('layout' => 'none', 'format' => 'none'),
            $indexContents);
        $fs->withPost(
            'before-post', 2, 1, 2012,
            array('layout' => 'none', 'format' => 'none'),
            'Before...');
        $fs->withPost(
            'z-first-post', 4, 1, 2012,
            array('layout' => 'none', 'format' => 'none', 'time' => '08:50'),
            'First post.');
        $fs->withPost(
            'a-second-post', 4, 1, 2012,
            array('layout' => 'none', 'format' => 'none', 'time' => '12:30'),
            'Second post.');
        $fs->withPost(
            'b-third-post', 4, 1, 2012,
            array('layout' => 'none', 'format' => 'none', 'time' => '17:05:32'),
            'Third post.');
        $fs->withPost(
            'after-post', 12, 1, 2012,
            array('layout' => 'none', 'format' => 'none'),
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
    }

    public function testBakePageWithSeveralPostsInTheSameDayFiltered()
    {
        $indexContents = <<<EOD
{% for p in pagination.posts.skip(1) %}
{{ p.content|raw }}
{% endfor %}
EOD;

        $fs = MockFileSystem::create();
        $fs->withPage(
            'foo',
            array('layout' => 'none', 'format' => 'none'),
            $indexContents);
        $fs->withPost(
            'z-first-post', 1, 1, 2012,
            array('layout' => 'none', 'format' => 'none', 'time' => '08:50'),
            'First post.');
        $fs->withPost(
            'a-second-post', 1, 1, 2012,
            array('layout' => 'none', 'format' => 'none', 'time' => '12:30'),
            'Second post.');
        $fs->withPost(
            'b-third-post', 1, 1, 2012,
            array('layout' => 'none', 'format' => 'none', 'time' => '17:05:32'),
            'Third post.');

        $app = new PieCrust(array(
            'cache' => false, 
            'root' => $fs->url('kitchen'))
        );

        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertFileExists($fs->url('kitchen/_counter/foo.html'));
        $this->assertEquals(
            "Second post.\nFirst post.\n",
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
            ->withAsset('_content/theme/_content/config.yml', '');

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
}

