<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/5/18
 * Time: 2:21 PM
 */

namespace RichardGoldstein\FatFreeRoutes;

use PHPUnit\Framework\TestCase;

class RouteCacheTest extends TestCase
{

    public function testAddFile()
    {
        $testFile = __DIR__ . '/data/testPhpFile.php';
        $pf = new ParsedFile(__FILE__);
        $rc = new RouteCache();
        $rc->addFile($pf);
        $rc->addFile(new ParsedFile($testFile));
        $this->assertEquals(2, count($rc->files));
        $this->assertArrayHasKey(__FILE__, $rc->files, "Expected file key not found");
        $this->assertArrayHasKey($testFile, $rc->files, "Expected file key not found");
    }

    public function testShouldReloadFile()
    {
        $testFile = __DIR__ . '/data/testPhpFile.php';
        $rc = new RouteCache();
        $this->assertTrue($rc->shouldReloadFile(__FILE__));
        $this->assertTrue($rc->shouldReloadFile($testFile));

        $rc->addFile(new ParsedFile(__FILE__));
        $rc->addFile(new ParsedFile($testFile));
        $this->assertFalse($rc->shouldReloadFile(__FILE__));
        $this->assertFalse($rc->shouldReloadFile($testFile));
        // Touch the test file which will make it older that when it was loaded.
        touch($testFile);
        $this->assertFalse($rc->shouldReloadFile(__FILE__));
        $this->assertTrue($rc->shouldReloadFile($testFile));


    }



    private function makeRouteCache()
    {
        $pf1 = new ParsedFile(__FILE__);
        $pf1->addRoute(new Route('1','1','1','route'));
        $pf1->addRoute(new Route('2','2','2','route'));
        $pf1->addRoute(new Route('3','3','3','route'));
        $pf1->addRoute(new Route('4','4','4','route'));
        $pf1->addRoute(new Route('5','5','5','route'));
        $pf1->addRoute(new Route('6','6','6','route'));

        $testFile = __DIR__ . '/data/testPhpFile.php';

        $pf2 = new ParsedFile($testFile);
        $pf1->addRoute(new Route('10','10','10','route'));
        $pf1->addRoute(new Route('11','11','11','route'));
        $pf1->addRoute(new Route('12','12','12','route'));

        $rc = new RouteCache();
        $rc->addFile($pf1);
        $rc->addFile($pf2);
        return $rc;
    }

    public function testGetSortedList()
    {
        // Should return a merged list of sorted routes from ParsedFile.
        // Soerting is tested elsewhere. Test that we have x unique routes
        $rc = $this->makeRouteCache();

        $this->assertEquals(2, count($rc->files));
        $list = $rc->getSortedList();

        $this->assertEquals(9, count($list), "The list does not have the expected number of elements");
        // Pluck the paths out
        $paths = array_map(function(Route $r) {
            return $r->path;
        }, $list);
        $unique = array_unique($paths);
        $this->assertEquals(9, count($unique), "The sorted list did not contain unique values.");


    }

    public function testSaveToFile()
    {
        $fn = __DIR__ . '/data/test.ser';

        $rc = $this->makeRouteCache();
        @unlink($fn);
        $this->assertFileNotExists($fn, 'Could not ensure that test file did not exist before test');

        $rc->saveToFile($fn);
        $this->assertFileExists($fn, "File did not exist after save");

        return $fn;
    }

    /**
     * @depends testSaveToFile
     */
    public function testLoadFromFile($fn)
    {
        RouteCache::loadFromFile($fn, $didSucceed);
        $this->assertTrue($didSucceed, "Unable to load RouteCache from file.");
        return $fn;
    }

    /**
     * @depends testLoadFromFile
     *
     * @param $fn
     *
     * @return string
     */
    public function testLoadFromFileWithBadClass($fn) {
        // Mangle the file by replacing the classname RouteCache with RouteCacheX
        $read = file_get_contents($fn);
        $this->assertTrue($read !== false, "Unable to read the RouteCache file from disk");
        $text = str_replace('RichardGoldstein\FatFreeRoutes\RouteCache', 'RichardGoldstein\FatFreeRoutes\RouteCacheX', $read);
        $write = file_put_contents($fn, $text);
        $this->assertTrue($write !== false, "Unable to write RouteCache file back to disk");

        RouteCache::loadFromFile($fn, $didSucceed);
        $this->assertFalse($didSucceed, "Mangled Route Cache file was loaded.");
        return $fn;
    }

    /**
     * @param $fn
     *
     * @depends testLoadFromFileWithBadClass
     * @return string
     */
    public function testLoadFromFileWithCorruptFile($fn) {
        @unlink($fn);
        $this->assertFileNotExists($fn, 'Could not ensure that test file did not exist before test');
        $write = file_put_contents($fn, 'x');
        $this->assertTrue($write !== false, "Unable to write RouteCache file back to disk");

        RouteCache::loadFromFile($fn, $didSucceed);
        $this->assertFalse($didSucceed, "Mangled Route Cache file was loaded.");
        return $fn;


    }

    /**
     * @param $fn
     *
     * @depends testLoadFromFileWithCorruptFile
     * @return string
     */
    public function testLoadFromFileWithEmptyFile($fn) {
        @unlink($fn);
        $this->assertFileNotExists($fn, 'Could not ensure that test file did not exist before test');
        $write = file_put_contents($fn, '');
        $this->assertTrue($write !== false, "Unable to write RouteCache file back to disk");

        RouteCache::loadFromFile($fn, $didSucceed);
        $this->assertFalse($didSucceed, "Empty Route Cache file was loaded.");
        return $fn;
    }

    /**
     * @param $fn
     *
     * @depends testLoadFromFileWithEmptyFile
     * @return string
     */
    public function testLoadFromFileWithNoFile($fn) {
        @unlink($fn);
        $this->assertFileNotExists($fn, 'Could not ensure that test file did not exist before test');

        RouteCache::loadFromFile($fn, $didSucceed);
        $this->assertFalse($didSucceed, "Non existent Route Cache file was loaded.");
        return $fn;
    }

}
