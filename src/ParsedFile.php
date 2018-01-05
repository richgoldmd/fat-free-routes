<?php

namespace RichardGoldstein\FatFreeRoutes;


/**
 * Represents data obtained from a single file and mod time for the file.
 * Used to keep the process speedy by storing modified time of a parsed file along
 * with the parsed routes. This is serializable.
 *
 * User: richardgoldstein
 * Date: 8/29/17
 * Time: 1:18 PM
 */
class ParsedFile
{
    public $filename;
    public $mtime;
    public $routes;

    /**
     * ParsedFile constructor.
     * Given the filename, record the modified time.
     *
     * @param null $filename
     */
    public function __construct($filename = null)
    {
        $this->filename = $filename;
        $this->mtime = filemtime($filename);
        $this->routes = [];
    }

    /**
     * Add a route to the cache for this file
     *
     * @param Route $route
     */
    public function addRoute(Route $route)
    {
        $this->routes[] = $route;
    }

}