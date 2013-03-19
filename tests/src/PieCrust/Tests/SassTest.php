<?php

namespace PieCrust\Tests;

use PieCrust\Baker\PieCrustBaker;
use PieCrust\Util\PathHelper;
use PieCrust\Mock\MockFileSystem;


class SassTest extends PieCrustTestCase
{
    public function testSass()
    {
        $temp = array();
        $returnCode = 0;
        //TODO: this may output stuff to `stderr`...
        @exec('scss --help', $temp, $returnCode);
        if ($returnCode != 0)
        {
            $this->markTestIncomplete("Sass doesn't seem to be available. Skipping test.");
            return;
        }

        $fs = MockFileSystem::create(true, true)
            ->withTemplate('default', '{{content|raw}}')
            ->withAsset('sass/theme.scss', <<<EOD
#navbar {
  width: 80%;
  height: 23px;

  ul { list-style-type: none; }
  li {
    float: left;
    a { font-weight: bold; }
  }
}
EOD
            );

        $pc = $fs->getApp();
        $baker = new PieCrustBaker($pc);
        $baker->bake();

        $this->assertEquals(<<<EOD
#navbar {
  width: 80%;
  height: 23px; }
  #navbar ul {
    list-style-type: none; }
  #navbar li {
    float: left; }
    #navbar li a {
      font-weight: bold; }

EOD
            ,
            file_get_contents($fs->url('kitchen/_counter/sass/theme.css'))
        );
    }
}

