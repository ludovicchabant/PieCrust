<?php

require_once 'unittest_setup.php';

use PieCrust\PieCrust;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Page\PageRepository;


class PieCrustBakerTest extends PHPUnit_Framework_TestCase
{
    public function testBakePageWithTagList()
    {
        PageRepository::clearPages();

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

        $this->assertEquals(4, count(PageRepository::getPages()));

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
}

