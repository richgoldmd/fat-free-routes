<?php
/**
 *
 * User: richardgoldstein
 * Date: 8/29/17
 * Time: 1:18 PM
 */

namespace RichardGoldstein\FatFreeRoutes;

/**
 * Class ParsedFile
 * Represents data obtained from a single file and mod time for the file.
 * Used to keep the process speedy by storing modified time of a parsed file along
 * with the parsed data from tags. This is serializable.
 *
 * @package RichardGoldstein\FatFreeRoutes
 */
class ParsedFile implements \Serializable
{
    /**
     * @var string
     */
    public $filename;
    /**
     * @var int
     */
    public $mtime;

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
    }


    /**
     * Add plugin-specific data
     *
     * @param string $plugin Usually the result of get_class() in the plugin
     * @param \Serializable $data
     */
    public function addData($plugin, \Serializable $data)
    {
        if (!isset($this->pluginData[$plugin])) {
            $this->pluginData[$plugin] = [];
        }
        $this->pluginData[$plugin][] = $data;
    }

    /**
     * @param string $plugin Usually get_class() in the plugin
     *
     * @return array|mixed Array of data saved by addData()
     */
    public function getData($plugin) {
        return $this->pluginData[$plugin] ?? [];
    }

    /**
     * Serialize the data herein. Done here to ensure that this can be written to a file.
     *
     * @return string
     */
    public function serialize() {
        return serialize([$this->filename, $this->mtime, $this->pluginData]);
    }

    /**
     * Unserialize this class instance
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        list($this->filename, $this->mtime, $this->pluginData) = unserialize($serialized);
    }

}