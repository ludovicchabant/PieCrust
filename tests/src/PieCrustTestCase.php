<?php

namespace PieCrust\Tests;

use PieCrust\Util\PathHelper;


class PieCrustTestCase extends \PHPUnit_Framework_TestCase
{
    public static function tearDownAfterClass()
    {
        $mockDir = PIECRUST_UNITTESTS_MOCK_DIR;
        if (is_dir($mockDir))
        {
            // On Windows, it looks like the file-system is a bit "slow".
            // And by "slow", I mean "retarded".
            $tries = 3;
            while ($tries > 0)
            {
                try
                {
                    PathHelper::deleteDirectoryContents($mockDir);
                    rmdir($mockDir);
                    $tries = 0;
                }
                catch (\Exception $e)
                {
                    $tries--;
                }
            }
        }
    }
}

