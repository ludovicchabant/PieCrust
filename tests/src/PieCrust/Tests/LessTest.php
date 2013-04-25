<?php

namespace PieCrust\Tests;

use PieCrust\Baker\PieCrustBaker;
use PieCrust\Util\PathHelper;
use PieCrust\Mock\MockFileSystem;


class LessTest extends PieCrustTestCase
{
    public function lessDataProvider()
    {
        return array(
            array(false),
            array(true)
        );
    }

    /**
     * @dataProvider lessDataProvider
     */
    public function testLess($usingJsTool)
    {
        $fs = MockFileSystem::create(true, true)
            ->withTemplate('default', '{{content|raw}}')
            ->withAsset('screen.less', <<<EOD
@red: #ff0000;
.foo {
    background: @red;
    .bar {
        border: 1px;
    }
}
EOD
            );
        if ($usingJsTool)
        {
            // Mark test as incomplete if `lessc` is not availble in
            // the PATH.
            $temp = array();
            $returnCode = 0;
            //TODO: this may output stuff to `stderr`...
            @exec('lessc --help', $temp, $returnCode);
            if ($returnCode != 0)
            {
                $this->markTestIncomplete("`lessc` doesn't seem to be available. Skipping test.");
                return;
            }

            $fs->withConfig(array(
                'less' => array(
                    'use_lessc' => true
                )
            ));
        }

        $pc = $fs->getApp();
        $baker = new PieCrustBaker($pc);
        $baker->bake();

        $this->assertEquals(<<<EOD
.foo {
  background: #ff0000;
}
.foo .bar {
  border: 1px;
}

EOD
            ,
            file_get_contents($fs->url('kitchen/_counter/screen.css'))
        );
    }
}

