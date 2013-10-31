<?php

namespace PieCrust\Tests;

use PieCrust\PieCrust;
use PieCrust\Baker\PieCrustBaker;
use PieCrust\Mock\MockFileSystem;


class MultiBlogTest extends PieCrustTestCase
{
    public function testDefaultPostLayout()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array(
                'default_format' => 'none',
                'blogs' => array('otherblog', 'testblog')
            )))
            ->withTemplate(
                'post',
                'Title: {{ page.title }}'
            )
            ->withTemplate(
                'testblog/post',
                'Overridden, Title: {{ page.title }}'
            )
            ->withPost(
                'test1', 
                5, 8, 2012, 
                array('title' => 'test1 title'), 
                'Blah blah',
                'testblog'
            )
            ->withPost(
                'test2', 
                6, 8, 2012, 
                array('title' => 'test2 title'), 
                'Blah blah',
                'otherblog'
            );
        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->bake();

        $expectedContents = 'Overridden, Title: test1 title';
        $this->assertEquals(
            $expectedContents,
            file_get_contents($fs->url('kitchen/_counter/testblog/2012/08/05/test1.html'))
        );

        $expectedContents = 'Title: test2 title';
        $this->assertEquals(
            $expectedContents,
            file_get_contents($fs->url('kitchen/_counter/otherblog/2012/08/06/test2.html'))
        );
    }

    public function testUrlWithMultipleBlogs()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array('blogs' => array('one', 'two')),
                'two' => array('tag_url' => 'tagged/%tag%')
            ))
            ->withTemplate('default', '')
            ->withTemplate('post', '{{content|raw}}')
            ->withPage(
                'normal',
                array('layout' => 'none', 'format' => 'none'),
                <<<'EOD'
{{pctagurl('foo')}}
{{pctagurl('foo', 'one')}}
{{pctagurl('bar', 'two')}}
EOD
            )
            ->withPage(
                'second',
                array('layout' => 'none', 'format' => 'none', 'blog' => 'two'),
                <<<'EOD'
{{pctagurl('foo')}}
{{pctagurl('foo', 'one')}}
{{pctagurl('bar', 'two')}}
EOD
            )
            ->withPost('post1', 1, 1, 2012, array('format' => 'none'), "POST ONE {{pctagurl('foo')}}", 'one')
            ->withPost('post2', 1, 1, 2012, array('format' => 'none'), "POST TWO {{pctagurl('bar')}}", 'two');

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertEquals(
            "/one/tag/foo.html\n/one/tag/foo.html\n/tagged/bar.html",
            file_get_contents($fs->url('kitchen/_counter/normal.html'))
        );
        $this->assertEquals(
            "/tagged/foo.html\n/one/tag/foo.html\n/tagged/bar.html",
            file_get_contents($fs->url('kitchen/_counter/second.html'))
        );
        $this->assertEquals(
            "POST ONE /one/tag/foo.html",
            file_get_contents($fs->url('kitchen/_counter/one/2012/01/01/post1.html'))
        );
        $this->assertEquals(
            "POST TWO /tagged/bar.html",
            file_get_contents($fs->url('kitchen/_counter/two/2012/01/01/post2.html'))
        );
    }

    public function testUrlInTemplateWithMultipleBlogs()
    {
        $fs = MockFileSystem::create()
            ->withConfig(array(
                'site' => array('blogs' => array('one', 'two')),
                'two' => array('tag_url' => 'tagged/%tag%')
            ))
            ->withTemplate('default', "{{content|raw}}\nTPL:{{pctagurl('foo')}}")
            ->withTemplate('post', "{{content|raw}}\nTPL:{{pctagurl('bar')}}")
            ->withPage(
                'normal',
                array('format' => 'none'),
                <<<'EOD'
{% for post in one.posts %}
{{post.content|raw}}
{% endfor %}
{% for post in two.posts %}
{{post.content|raw}}
{% endfor %}
EOD
            )
            ->withPage(
                'second',
                array('format' => 'none', 'blog' => 'two'),
                <<<'EOD'
{% for post in one.posts %}
{{post.content|raw}}
{% endfor %}
{% for post in two.posts %}
{{post.content|raw}}
{% endfor %}
EOD
            )
            ->withPost('post1', 1, 1, 2012, array('format' => 'none'), "POST ONE {{pctagurl('foo')}}", 'one')
            ->withPost('post2', 1, 1, 2012, array('format' => 'none'), "POST TWO {{pctagurl('bar')}}", 'two');

        $app = $fs->getApp();
        $baker = new PieCrustBaker($app);
        $baker->bake();

        $this->assertEquals(
            "POST ONE /one/tag/foo.html\nPOST TWO /tagged/bar.html\n\nTPL:/one/tag/foo.html",
            file_get_contents($fs->url('kitchen/_counter/normal.html'))
        );
        $this->assertEquals(
            "POST ONE /one/tag/foo.html\nPOST TWO /tagged/bar.html\n\nTPL:/tagged/foo.html",
            file_get_contents($fs->url('kitchen/_counter/second.html'))
        );
        $this->assertEquals(
            "POST ONE /one/tag/foo.html\nTPL:/one/tag/bar.html",
            file_get_contents($fs->url('kitchen/_counter/one/2012/01/01/post1.html'))
        );
        $this->assertEquals(
            "POST TWO /tagged/bar.html\nTPL:/tagged/bar.html",
            file_get_contents($fs->url('kitchen/_counter/two/2012/01/01/post2.html'))
        );
    }
}

