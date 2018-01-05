<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/5/18
 * Time: 4:12 PM
 */

namespace RichardGoldstein\FatFreeRoutes;


/**
 * Class MapRoute
 * F3 Map routes have no aliases and are mapped directly to a class.
 * We support aliases for the js output but do not pass it along to the
 * $f3->map() call.
 *
 * @package RichardGoldstein\FatFreeRoutes
 */
class MapRoute extends Route
{
    public function __construct($route, $dest, $path, $tag, $alias = '', $emitJS = false)
    {
        parent::__construct($route, $dest, $path, $tag, $alias, $emitJS);
    }

    public function makePHP()
    {
        return "\$f3->map('{$this->path}', '{$this->dest}');" . PHP_EOL;
    }
}