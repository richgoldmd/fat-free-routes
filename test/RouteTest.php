<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/5/18
 * Time: 1:44 PM
 */

namespace RichardGoldstein\FatFreeRoutes;

use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    public function testSortRoutes()
    {
        // Routes should be sorted so that wildcards follow more specific matches
        // Only the path is relevant. We'll use another parameter to indicate expected position
        // Wildcards should come last, most specific first. Non-wildcards but with placeholders
        // should come such that placeholders follow fixed values in yhe same position, then
        // longest to shortest in terms of segments
        $routes = [
            new Route(5, '', '/', 'route'),
            new Route(8, '', '/*', 'route'),
            new Route(3, '', '/sub/specific', 'route'),
            new Route(7, '', '/sub/*', 'route'),
            new Route(6, '', '/sub/lower/*', 'route'),
            new Route(4, '', '/sub/@tag', 'route'),
            new Route(2, '', '/sub/lower/@tag', 'route'),
            new Route(1, '', '/sub/lower/@tag/spec', 'route'),
            new Route(0, '', '/sub/lower/spec', 'route')
        ];
        Route::sortRoutes($routes);
        for ($i = 0; $i < count($routes); $i++) {
            $this->assertEquals($routes[$i]->route, $i);

        }
    }

    public function testMakePHP()
    {
        $r = new Route('theRoute', 'theDest', 'thePath', 'theTag', 'theAlias', true);
        $php = $r->makePHP();
        $this->assertRegExp('/^\s*\$f3->route\(\s*\'theRoute\',\s*\'theDest\'\);\s*$/', $php);
    }
}
