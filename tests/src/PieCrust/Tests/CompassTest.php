<?php

namespace PieCrust\Tests;

use PieCrust\Baker\PieCrustBaker;
use PieCrust\Util\PathHelper;
use PieCrust\Mock\MockFileSystem;


class CompassTest extends PieCrustTestCase
{
    public function testCompass()
    {
        $temp = array();
        $returnCode = 0;
        //TODO: this may output stuff to `stderr`...
        @exec('compass --help', $temp, $returnCode);
        if ($returnCode != 0)
        {
            $this->markTestIncomplete("Compass doesn't seem to be available. Skipping test.");
            return;
        }

        $fs = MockFileSystem::create(true, true)
            ->withConfig(array('compass' => array('use_compass' => true)))
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
        @exec('compass init stand_alone "'.$fs->url('kitchen').'"');

        $pc = $fs->getApp();
        $baker = new PieCrustBaker($pc);
        $baker->bake();

        $this->assertEquals(<<<EOD
/* line 1, ../sass/theme.scss */
#navbar {
  width: 80%;
  height: 23px;
}
/* line 5, ../sass/theme.scss */
#navbar ul {
  list-style-type: none;
}
/* line 6, ../sass/theme.scss */
#navbar li {
  float: left;
}
/* line 8, ../sass/theme.scss */
#navbar li a {
  font-weight: bold;
}

EOD
            ,
            file_get_contents($fs->url('kitchen/_counter/stylesheets/theme.css'))
        );
    }
}

