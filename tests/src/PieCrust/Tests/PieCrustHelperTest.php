<?php

namespace PieCrust\Tests;

use PieCrust\PieCrust;
use PieCrust\Util\PieCrustHelper;
use PieCrust\Mock\MockFileSystem;
use PieCrust\Mock\MockPieCrust;


class PieCrustHelperTest extends PieCrustTestCase
{
    public function formatUriDataProviderWhenRun()
    {
        return array(
            array('', '/'),
            array('', '/?!debug', '/', true, true),
            array('', '/?/', '/', false),
            array('', '/?/&!debug', '/', false, true),

            array('', '/root/', '/root'),
            array('', '/root/?!debug', '/root', true, true),
            array('', '/root/?/', '/root', false),
            array('', '/root/?/&!debug', '/root', false, true),

            array('test', '/test'),
            array('test', '/test?!debug', '/', true, true),
            array('test', '/?/test', '/', false),
            array('test', '/?/test&!debug', '/', false, true),

            array('test', '/root/test', '/root'),
            array('test', '/root/test?!debug', '/root', true, true),
            array('test', '/root/?/test', '/root', false),
            array('test', '/root/?/test&!debug', '/root', false, true),

            array('somewhere/test', '/somewhere/test'),
            array('somewhere/test', '/somewhere/test?!debug', '/', true, true),
            array('somewhere/test', '/?/somewhere/test', '/', false),
            array('somewhere/test', '/?/somewhere/test&!debug', '/', false, true),

            array('somewhere/test', '/root/somewhere/test', '/root'),
            array('somewhere/test', '/root/somewhere/test?!debug', '/root', true, true),
            array('somewhere/test', '/root/?/somewhere/test', '/root', false),
            array('somewhere/test', '/root/?/somewhere/test&!debug', '/root', false, true),

            array('test.ext', '/test.ext'),
            array('test.ext', '/test.ext?!debug', '/', true, true),
            array('test.ext', '/?/test.ext', '/', false),
            array('test.ext', '/?/test.ext&!debug', '/', false, true),

            array('test.ext', '/root/test.ext', '/root'),
            array('test.ext', '/root/test.ext?!debug', '/root', true, true),
            array('test.ext', '/root/?/test.ext', '/root', false),
            array('test.ext', '/root/?/test.ext&!debug', '/root', false, true),

            array('somewhere/test.ext', '/somewhere/test.ext'),
            array('somewhere/test.ext', '/somewhere/test.ext?!debug', '/', true, true),
            array('somewhere/test.ext', '/?/somewhere/test.ext', '/', false),
            array('somewhere/test.ext', '/?/somewhere/test.ext&!debug', '/', false, true),

            array('somewhere/test.ext', '/root/somewhere/test.ext', '/root'),
            array('somewhere/test.ext', '/root/somewhere/test.ext?!debug', '/root', true, true),
            array('somewhere/test.ext', '/root/?/somewhere/test.ext', '/root', false),
            array('somewhere/test.ext', '/root/?/somewhere/test.ext&!debug', '/root', false, true)
        );
    }

    /**
     * @dataProvider formatUriDataProviderWhenRun
     */
    public function testFormatUriWhenRun($pageUri, $expectedUri, $siteRoot = '/', $prettyUrls = true, $debug = false)
    {
        $fs = MockFileSystem::create();
        $fs->withConfig(array(
            'site' => array(
                'root' => $siteRoot,
                'pretty_urls' => $prettyUrls
            )
        ));
        $pc = new PieCrust(array(
            'root' => $fs->getAppRoot(), 
            'cache' => false,
            'debug' => $debug)
        );

        $uri = PieCrustHelper::formatUri($pc, $pageUri);
        $this->assertEquals($expectedUri, $uri);
    }

    public function formatUriDataProviderWhenBaked()
    {
        return array(
            array('', '/'),
            array('', '/', '/', true, true),
            array('', '/', '/', false),
            array('', '/', '/', false, true),

            array('', '/root/', '/root'),
            array('', '/root/', '/root', true, true),
            array('', '/root/', '/root', false),
            array('', '/root/', '/root', false, true),

            array('test', '/test'),
            array('test', '/test', '/', true, true),
            array('test', '/test/', '/', true, true, true),
            array('test', '/test.html', '/', false),
            array('test', '/test.html', '/', false, true),

            array('test', '/root/test', '/root'),
            array('test', '/root/test', '/root', true, true),
            array('test', '/root/test/', '/root', true, true, true),
            array('test', '/root/test.html', '/root', false),
            array('test', '/root/test.html', '/root', false, true),

            array('somewhere/test', '/somewhere/test'),
            array('somewhere/test', '/somewhere/test', '/', true, true),
            array('somewhere/test', '/somewhere/test/', '/', true, true, true),
            array('somewhere/test', '/somewhere/test.html', '/', false),
            array('somewhere/test', '/somewhere/test.html', '/', false, true),

            array('somewhere/test', '/root/somewhere/test', '/root'),
            array('somewhere/test', '/root/somewhere/test', '/root', true, true),
            array('somewhere/test', '/root/somewhere/test/', '/root', true, true, true),
            array('somewhere/test', '/root/somewhere/test.html', '/root', false),
            array('somewhere/test', '/root/somewhere/test.html', '/root', false, true),

            array('test.ext', '/test.ext'),
            array('test.ext', '/test.ext', '/', true, true),
            array('test.ext', '/test.ext', '/', false),
            array('test.ext', '/test.ext', '/', false, true),

            array('test.ext', '/root/test.ext', '/root'),
            array('test.ext', '/root/test.ext', '/root', true, true),
            array('test.ext', '/root/test.ext', '/root', false),
            array('test.ext', '/root/test.ext', '/root', false, true),

            array('somewhere/test.ext', '/somewhere/test.ext'),
            array('somewhere/test.ext', '/somewhere/test.ext', '/', true, true),
            array('somewhere/test.ext', '/somewhere/test.ext', '/', false),
            array('somewhere/test.ext', '/somewhere/test.ext', '/', false, true),

            array('somewhere/test.ext', '/root/somewhere/test.ext', '/root'),
            array('somewhere/test.ext', '/root/somewhere/test.ext', '/root', true, true),
            array('somewhere/test.ext', '/root/somewhere/test.ext', '/root', false),
            array('somewhere/test.ext', '/root/somewhere/test.ext', '/root', false, true)
        );
    }

    /**
     * @dataProvider formatUriDataProviderWhenBaked
     */
    public function testFormatUriWhenBaked($pageUri, $expectedUri, $siteRoot = '/', $prettyUrls = true, $debug = false, $trailingSlash = false)
    {
        $fs = MockFileSystem::create();
        $fs->withConfig(array(
            'site' => array(
                'root' => $siteRoot,
                'pretty_urls' => $prettyUrls
            ),
            'baker' => array(
                'is_baking' => true,
                'trailing_slash' => $trailingSlash
            )
        ));
        $pc = new PieCrust(array(
            'root' => $fs->getAppRoot(), 
            'cache' => false,
            'debug' => $debug)
        );

        $uri = PieCrustHelper::formatUri($pc, $pageUri);
        $this->assertEquals($expectedUri, $uri);
    }
}

