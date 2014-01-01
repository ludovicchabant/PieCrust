<?php

namespace PieCrust\Tests;

use PieCrust\Chef\Chef;
use PieCrust\Mock\MockFileSystem;


class ChefTest extends PieCrustTestCase
{
    public function preparePostDataProvider()
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        return array(
            array('flat', 'foo', "{$year}-{$month}-{$day}_foo.html")
        );
    }

    /**
     * @dataProvider preparePostDataProvider
     */
    public function testPreparePost($configFs, $slug, $expectedPath)
    {
        $fs = MockFileSystem::create()
            ->withConfig(array('site' => array('posts_fs' => $configFs)));

        $fullExpectedPath = $fs->url('kitchen/_content/posts/' . $expectedPath);
        $this->assertFalse(is_file($fullExpectedPath));
        $this->runChef($fs, array('prepare', 'post', $slug));
        $this->assertFileExists($fullExpectedPath);
    }

    public function findDataProvider()
    {
        return array(
            array(
                'foo',
                "_content/pages/foo.html\n".
                "_content/pages/foo/something.html\n".
                "_content/pages/foo/otherwise.html\n".
                "_content/pages/bar/foo.html\n".
                "_content/posts/2012-06-05_some-foo.html\n".
                "_content/templates/foos.html\n"
            ),
            array(
                array('foo', '--pages'),
                "_content/pages/foo.html\n".
                "_content/pages/foo/something.html\n".
                "_content/pages/foo/otherwise.html\n".
                "_content/pages/bar/foo.html\n"
            ),
            array(
                array('foo', '--posts'),
                "_content/posts/2012-06-05_some-foo.html\n"
            ),
            array(
                array('foo', '--templates'),
                "_content/templates/foos.html\n"
            ),
            array(
                'second',
                "_content/posts/2012-06-03_second.html\n"
            ),
            array(
                array('second', '--components'),
                "path: _content/posts/2012-06-03_second.html\n".
                "type: post\n".
                "uri: 2012/06/03/second\n".
                "slug: second\n".
                "year: 2012\n".
                "month: 06\n".
                "day: 03\n".
                "hour: 13\n".
                "minute: 53\n".
                "second: 44\n\n"
            )
        );
    }

    /**
     * @dataProvider findDataProvider
     */
    public function testFind($what, $expected)
    {
        $fs = MockFileSystem::create()
            ->withPost('first', 2, 6, 2012, array(), '')
            ->withPost('second', 3, 6, 2012, array('time' => '13:53:44'), '')
            ->withPost('third', 4, 6, 2012, array(), '')
            ->withPost('third-same-day', 4, 6, 2012, array(), '')
            ->withPost('some-foo', 5, 6, 2012, array(), '')
            ->withPage('foo', array(), '')
            ->withPage('foo/something', array(), '')
            ->withPage('foo/otherwise', array(), '')
            ->withPage('bar', array(), '')
            ->withPage('bar/foo', array(), '')
            ->withTemplate('default', '')
            ->withTemplate('post', '')
            ->withTemplate('foos', '');

        $logFile = $fs->url('tmp/log');
        if (!is_array($what))
            $what = array($what);

        // VFS behaves differently than the real one. Create an
        // empty file to not upset PHP...
        mkdir(dirname($logFile), 0777, true);
        file_put_contents($logFile, '');

        $args = array_merge(array('--log', $logFile, 'find'), $what);
        $this->runChef($fs, $args);
        $actual = file_get_contents($logFile);
        $actual = $this->stripLog($actual);
        $this->assertEquals($expected, $actual);
    }

    protected function runChef($fs, $args)
    {
        $chef = new Chef();
        array_splice($args, 0, 0, array('chef', '--root="'.$fs->getAppRoot().'"', '--quiet'));
        $chef->runUnsafe(count($args), $args);
    }

    protected function stripLog($log)
    {
        $lines = explode("\n", $log);
        $filtered = array();
        foreach ($lines as $line)
        {
            $matches = array();
            if (preg_match("/^.* \\[(?P<level>\w+)\\] (?P<text>.*)$/", $line, $matches))
            {
                if ($matches['level'] != 'debug')
                {
                    $filtered[] = str_replace('\\', '/', trim($matches['text']));
                }
            }
        }
        $filtered[] = '';
        return implode("\n", $filtered);
    }
}

