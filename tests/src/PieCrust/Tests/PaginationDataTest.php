<?php

namespace PieCrust\Tests;

use PieCrust\Data\PaginationData;
use PieCrust\Page\Page;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;


class PaginationDataTest extends PieCrustTestCase
{
    public function pageMetadataDataProvider()
    {
        return array(
            $this->makeTestParameters(array(
                'title' => 'A title'
            )),
            $this->makeTestParameters(array(
                'something' => array(
                    'nested' => true,
                    'foo' => 'bar'
                )
            ))
        );
    }

    /**
     * @dataProvider pageMetadataDataProvider
     */
    public function testPageMetadata($pageConfig, $pageContents, $expectedValues)
    {
        $fs = MockFileSystem::create()
            ->withPage(
                'foo',
                $pageConfig,
                ''
            );
        $pc = $fs->getApp();        
        $pc->getConfig()->setValue('site/pretty_urls', true);
        $pc->getConfig()->setValue('site/root', 'http://whatever/');
        $page = Page::createFromUri($pc, '/foo', false);
        $data = new PaginationData($page);
        foreach ($expectedValues as $key => $val)
        {
            $this->assertTrue(isset($data[$key]));
            $this->assertEquals($val, $data[$key]);
        }
    }

    public function testPageContents()
    {
        $content = <<<EOD
These are the contents.
They're quite awesome.
EOD;
        $summary = <<<EOD
This is the summary.
Yep.
EOD;
        $all = <<<EOD
$content
---summary---
$summary
EOD;

        $fs = MockFileSystem::create()
            ->withPage(
                'foo',
                array('format' => 'none'),
                $all
        );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $data = new PaginationData($page);
        $this->assertTrue(isset($data['content']));
        $this->assertEquals($content . "\n", $data['content']);
        $this->assertTrue(isset($data['summary']));
        $this->assertEquals($summary, $data['summary']);
    }

    private function makeTestParameters(array $custom, $contents = '')
    {
        $expected = $custom;
        $expected['url'] = 'http://whatever/foo';
        $expected['slug'] = 'foo';
        return array(
            $custom,
            $contents,
            $expected
        );
    }
}

