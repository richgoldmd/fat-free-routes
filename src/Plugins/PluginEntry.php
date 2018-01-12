<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/11/18
 * Time: 9:24 AM
 */

namespace RichardGoldstein\FatFreeRoutes\Plugins;


class PluginEntry
{
    /**
     * @var Plugin
     */
    public $plugin;
    /**
     * @var bool
     */
    public $active;
    /**
     * @var null|string
     */
    public $prefix;
    /**
     * @var string[]
     */
    public $tags;

    /** @var string[] */
    public $prefixedTags;

    public $tagRegex;
    public $prefixedTagRegex;


    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->active = false;
        $this->prefix = null;
        $this->tags = [];
        $this->prefixedTags = [];
        $this->tagRegex = null;
        $this->prefixedTagRegex = null;

    }
}
