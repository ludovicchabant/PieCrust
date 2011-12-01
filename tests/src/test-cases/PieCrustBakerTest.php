<?php

require_once 'unittest_setup.php';

use PieCrust\Baker\PieCrustBaker;


class PieCrustBakerTest extends PHPUnit_Framework_TestCase
{
    public function globToRegexDataProvider()
    {
        return array(
            array('blah', '/blah/'),
            array('/blah/', '/blah/'),
            array('/^blah.*\\.css/', '/^blah.*\\.css/'),
            array('blah.*', '/blah\\.[^\\/\\\\]*/'),
            array('blah?.css', '/blah[^\\/\\\\]\\.css/')
        );
    }

    /**
     * @dataProvider globToRegexDataProvider
     */
    public function testGlobToRegex($in, $expectedOut)
    {
        $out = PieCrustBaker::globToRegex($in);
        $this->assertEquals($expectedOut, $out);
    }

    public function testGlobToRegexExample()
    {
        $pattern = PieCrustBaker::globToRegex('blah*.css');
        $this->assertTrue(preg_match($pattern, 'dir/blah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah2.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blahblah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah.blah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah.blah.css/something') == 1);
        $this->assertFalse(preg_match($pattern, 'blah/something.css') == 1);

        $pattern = PieCrustBaker::globToRegex('blah?.css');
        $this->assertFalse(preg_match($pattern, 'dir/blah.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blah1.css') == 1);
        $this->assertTrue(preg_match($pattern, 'dir/blahh.css') == 1);
        $this->assertFalse(preg_match($pattern, 'dir/blah/yo.css') == 1);
    }
}

