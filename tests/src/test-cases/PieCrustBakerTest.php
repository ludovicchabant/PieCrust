<?php

require_once 'unittest_setup.php';

use PieCrust\PieCrust;
use PieCrust\Baker\PieCrustBaker;


class PieCrustBakerTest extends PHPUnit_Framework_TestCase
{
    public function testBakePageWithTagList()
    {
        $templateContents = <<<EOD
{% for t in site.tags %}
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

        $app = new PieCrust(array('cache' => false, 'root' => vfsStream::url('root/kitchen')));

        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertFileExists(
            vfsStream::url('root/kitchen/_counter/2010/01/01/first-post.html')
        );
        $this->assertFileExists(
            vfsStream::url('root/kitchen/_counter/2011/01/01/second-post.html')
        );
        $this->assertFileExists(
            vfsStream::url('root/kitchen/_counter/2012/01/01/third-post.html')
        );
        $this->assertFileExists(
            vfsStream::url('root/kitchen/_counter/2011/01/01/second-post/bar.jpg')
        );

        $this->assertEquals(
            "blah, 3\nFirst post.",
            file_get_contents(vfsStream::url('root/kitchen/_counter/2010/01/01/first-post.html'))
        );
        $this->assertEquals(
            "blah, 3\nSecond post: /2011/01/01/second-post/bar.jpg",
            file_get_contents(vfsStream::url('root/kitchen/_counter/2011/01/01/second-post.html'))
        );
        $this->assertEquals(
            "blah, 3\nThird post.",
            file_get_contents(vfsStream::url('root/kitchen/_counter/2012/01/01/third-post.html'))
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

        $app = new PieCrust(array('cache' => false, 'root' => vfsStream::url('root/kitchen')));

        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertFileExists(vfsStream::url('root/kitchen/_counter/foo/index.html'));
        $this->assertEquals(
            "Third post.\nSecond post.\nFirst post.\n",
            file_get_contents(vfsStream::url('root/kitchen/_counter/foo/index.html'))
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

        $app = new PieCrust(array('cache' => false, 'root' => vfsStream::url('root/kitchen')));

        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertFileExists(vfsStream::url('root/kitchen/_counter/foo/index.html'));
        $this->assertEquals(
            "Second post.\nFirst post.\n",
            file_get_contents(vfsStream::url('root/kitchen/_counter/foo/index.html'))
        );
    }
}

