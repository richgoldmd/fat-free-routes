<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/5/18
 * Time: 2:17 PM
 */

namespace RichardGoldstein\FatFreeRoutes;

use PHPUnit\Framework\TestCase;
use RichardGoldstein\FatFreeRoutes\Plugins\Routes\Route;

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
        $pf->addData(get_class(), new Route('1', '2', '3', '4'));
        $pf->addData(get_class(), new Route('1', '2', '3', '4'));
        $pf->addData(get_class(),new Route('1', '2', '3', '4'));
        $this->assertEquals(3, count($pf->getData(get_class())));
    }
}
