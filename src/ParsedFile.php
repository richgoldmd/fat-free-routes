<?php

namespace RichardGoldstein\FatFreeRoutes;


/**
 * Represents data obtained from a single file and mod time for the file.
 * Used to keep the process speedy by storing modified time of a parsed file along
 * with the parsed data from tags. This is serializable.
 *
 * User: richardgoldstein
 * Date: 8/29/17
 * Time: 1:18 PM
 */
class ParsedFile implements \Serializable
{
    public $filename;
    public $mtime;
    /** @var array
     *  @deprecated
     */
    public $routes;

    /**
     * @var array Array of data keyed in the plugin class name
     */
    public $pluginData;

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
        $this->pluginData = [];
        $this->routes = [];
    }

    /**
     * Add a route to the cache for this file
     *
     * @param Route $route
     * @deprecated
     */
    public function addRoute(Route $route)
    {
        $this->routes[] = $route;
    }

    public function addData($plugin, \Serializable $data)
    {
        if (!isset($this->pluginData[$plugin])) {
            $this->pluginData[$plugin] = [];
        }
        $this->pluginData[$plugin][] = $data;
    }

    public function getData($plugin) {
        return $this->pluginData[$plugin] ?? [];
    }

    public function serialize() {
        return serialize([$this->filename, $this->mtime, $this->pluginData]);
    }

    public function unserialize($serialized)
    {
        list($this->filename, $this->mtime, $this->pluginData) = unserialize($serialized);
    }

}