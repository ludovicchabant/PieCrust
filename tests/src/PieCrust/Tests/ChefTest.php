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

    protected function runChef($fs, $args)
    {
        $chef = new Chef();
        array_splice($args, 0, 0, array('--root="'.$fs->getAppRoot().'"', '--quiet'));
        $chef->runUnsafe(count($args), $args);
    }
}

