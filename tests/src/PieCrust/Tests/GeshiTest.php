<?php

namespace PieCrust\Tests;

use PieCrust\Page\Page;
use PieCrust\Mock\MockFileSystem;


class GeshiTest extends PieCrustTestCase
{
    public function testGeshi()
    {
        $fs = MockFileSystem::create()
            ->withPage('/foo', array(), <<<EOD
{% geshi 'python' %}
foo = 42
bar = 0
{% endgeshi %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $this->assertEquals(
            <<<EOD
<pre class="python" style="font-family:monospace;">foo <span style="color: #66cc66;">=</span> <span style="color: #ff4500;">42</span>
bar <span style="color: #66cc66;">=</span> <span style="color: #ff4500;">0</span></pre>

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testGeshiWithClasses()
    {
        $fs = MockFileSystem::create()
            ->withPage('/foo', array(), <<<EOD
{% geshi 'python' use_classes %}
foo = 42
{% endgeshi %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $this->assertEquals(
            <<<EOD
<pre class="python">foo <span class="sy0">=</span> <span class="nu0">42</span></pre>

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testGeshiWithClassesAndLineNumbers()
    {
        $fs = MockFileSystem::create()
            ->withPage('/foo', array(), <<<EOD
{% geshi 'python' use_classes line_numbers %}
foo = 42
{% endgeshi %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $this->assertEquals(
            <<<EOD
<pre class="python"><ol><li class="li1"><div class="de1">foo <span class="sy0">=</span> <span class="nu0">42</span></div></li></ol></pre>

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testGeshiWithClassesAndOverallClass()
    {
        $fs = MockFileSystem::create()
            ->withPage('/foo', array(), <<<EOD
{% geshi 'python' use_classes class='my-code' %}
foo = 42
{% endgeshi %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $this->assertEquals(
            <<<EOD
<pre class="python my-code">foo <span class="sy0">=</span> <span class="nu0">42</span></pre>

EOD
            ,
            $page->getContentSegment()
        );
    }

    public function testGeshiWithClassesAndOverallId()
    {
        $fs = MockFileSystem::create()
            ->withPage('/foo', array(), <<<EOD
{% geshi 'python' use_classes id='my-code' %}
foo = 42
{% endgeshi %}
EOD
            );
        $pc = $fs->getApp();
        $page = Page::createFromUri($pc, '/foo', false);
        $this->assertEquals(
            <<<EOD
<pre class="python" id="my-code">foo <span class="sy0">=</span> <span class="nu0">42</span></pre>

EOD
            ,
            $page->getContentSegment()
        );
    }
}

