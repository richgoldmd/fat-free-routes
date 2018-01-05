<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/5/18
 * Time: 3:41 PM
 */

namespace RichardGoldstein\FatFreeRoutes;

use PHPUnit\Framework\TestCase;

class ProcessParametersTest extends TestCase
{

    public static $output = '';

    public function testUsage()
    {
        ProcessParameters::usage('some error');
        $this->expectOutputRegex('/some error/');
        $this->assertRegExp('/Usage: f3routes/', self::$output);
    }

    // It is difficult to test geptopt with phpunit because of the way getopt() retrieves the values from argv and argc
    // using a third-party getopt is overkill, so that function will not be tested.
}

// Mock this function but save the result for testing
function cli_die($x) {
    ProcessParametersTest::$output = $x;
}
