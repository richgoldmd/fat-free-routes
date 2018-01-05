<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/5/18
 * Time: 2:17 PM
 */

namespace RichardGoldstein\FatFreeRoutes;

use PHPUnit\Framework\TestCase;

class ParsedFileTest extends TestCase
{

    public function testConstruct()
    {
        $pf = new ParsedFile(__FILE__);
        $this->assertEquals(__FILE__, $pf->filename);
        $this->assertEquals(filemtime(__FILE__), $pf->mtime);
    }

    public function testAddRoute()
    {
        $pf = new ParsedFile(__FILE__);
        $pf->addRoute(new Route('1', '2', '3', '4'));
        $pf->addRoute(new Route('1', '2', '3', '4'));
        $pf->addRoute(new Route('1', '2', '3', '4'));
        $this->assertEquals(3, count($pf->routes));
    }
}
