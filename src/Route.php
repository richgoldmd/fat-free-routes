<?php

namespace RichardGoldstein\FatFreeRoutes;

/**
 * Route data obtained from the Reflection classes.
 * This class is serializable
 *
 * User: richardgoldstein
 * Date: 8/29/17
 * Time: 1:25 PM
 */
class Route
{
    /**
     * @var string The F3 Route parameter
     */
    public $route;
    /**
     * @var string The F# destination parameter - e.g. member function designation
     */
    public $dest;
    /**
     * @var string The URL template
     */
    public $path;
    /**
     * @var string The tag used to create the route, e.g. 'route' or 'devroute'
     */
    public $tag;
    /**
     * @var string The optional alias, excludes any @ symbol
     */
    public $alias;
    /**
     * @var bool SHould this alias (if specified) be emitted into the rendered javascript?
     */
    public $emitJS;

    /**
     * Route constructor.
     *
     * @param string $route The entire F3 route designator, as passed to $f3->route()
     * @param string $dest The name of the method to call, as passed to $f3->route()
     * @param string $path The route's url template
     * @param string $tag The tag used to create this route (eg 'route' or 'devroute'
     * @param string $alias Alias for this route
     * @param bool $emitJS Does this route get emitted into the js file? (only if alias is present)
     */
    public function __construct($route, $dest, $path, $tag, $alias = '', $emitJS = false)
    {
        $this->route = $route;
        $this->dest = $dest;
        $this->path = $path;
        $this->tag = $tag;
        $this->alias = $alias;
        $this->emitJS = $emitJS;
    }


    /**
     * Sort the routes based on path so that more spcecific paths come first (e.g. before paths with wildcard
     * arguments.
     *
     * @param array $routes
     */
    static public function sortRoutes(&$routes)
    {
        usort(
            $routes,

            function (Route $a, Route $b) {
                $a_p = explode('/', $a->path);
                $b_p = explode('/', $b->path);
                $a_hw = strchr($a->path, '*') !== false;
                $b_hw = strchr($b->path, '*') !== false;
                if ($a_hw && !$b_hw) {
                    return 1;   // b first
                } elseif ($b_hw && !$a_hw) {
                    return -1;    // a first
                } elseif ($b_hw && $a_hw) {
                    return count($a_p) - count($b_p);
                } else {
                    $count = min(count($a_p), count($b_p));
                    for ($i = 0; $i < $count; $i++) {
                        $ae = $a_p[$i];
                        $be = $b_p[$i];
                        if ($ae != $be) {
                            $a_a = substr($ae, 0, 1) == '@';
                            $b_a = substr($be, 0, 1) == '@';
                            if ($a_a && !$b_a) {
                                return 1;    // b first
                            } elseif ($b_a && !$a_a) {
                                return -1; // a first
                            } else {
                                return strcmp($ae, $be);
                            }
                        }
                    }
                    // All elements equal - shorter one first
                    return count($a_p) - count($b_p);
                }

            }
        );

    }

    /**
     * Generate the php to create the intended route.
     *
     * @return string
     */
    public function makePHP()
    {
        return "\$f3->route('{$this->route}', '{$this->dest}');" . PHP_EOL;
    }

}