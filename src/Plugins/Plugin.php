<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/11/18
 * Time: 8:55 AM
 */

namespace RichardGoldstein\FatFreeRoutes\Plugins;

use GetOpt\GetOpt;
use phpDocumentor\Reflection\Php\Class_;
use phpDocumentor\Reflection\Php\Method;
use RichardGoldstein\FatFreeRoutes\ParsedFile;
use RichardGoldstein\FatFreeRoutes\ParsedFileCache;


/**
 * Class Plugin
 * This is the base class for plugins which process tags.
 *
 * plugins are registered with the main process and define the tags of interest.
 * The plugin is notified when said tag is found and the context in which it is found.
 *
 * Once all files have been processed the plugin is called to render its output
 *
 * @package RichardGoldstein\FatFreeRoutes
 */
abstract class Plugin
{
    protected $echoVerbose = null;

    final public function register(PluginRegistrar $pr) {
        $pr->registerPlugin($this);
    }

    public function setEchoCallback(callable $fn) {
        $this->echoVerbose = $fn;
    }

    protected  function echoVerbose($t)
    {
        if ($this->echoVerbose) {
            call_user_func($this->echoVerbose, $t);
        }
    }

    /**
     * This is called before command line options are parsed so that this plugin can augment
     * the parameters that are required or optional.
     * Return a string to append to the help text.
     *
     * @param GetOpt $opts
     *
     * @return string
     */
    public function setCommandLineOptions(/** @noinspection PhpUnusedParameterInspection */ GetOpt $opts) {
        return '';
    }

    /**
     * Parse option. Uses the passed in Command object.
     *
     * Use pattern would be;
     * 1. Define parameters on the Command object
     * 2. Call parse() on the command object
     * 3. Read the parameters from the object
     * 4. Act on param values (set internal flags, etc)
     *
     * @param GetOpt $opts
     *
     * @return bool True if the plugin should be active
     */
    public function parseOptions(/** @noinspection PhpUnusedParameterInspection */ GetOpt $opts) {
        // return true of this plugin should be included in the processing.
        // If false, this plugin will be ignored

        // Base class returns true so if not overriden the plugin is active
        return true;
    }

    /**
     * Return the tags to be processed by this plugin. The list may be altered according
     * to options that have been set in th call to parseOptions(), which will occur before this
     * method is called.
     *
     * Return an array with the following members:
     *  [
     *      string|null prefix - The PSR-5 compatible prefex for these tags. If null, the default will be used
     *      string[] tags - The array of tags which will be parsed, optionally with the tag prefix
     *      string[] tags - Tags that will be parsed which require the presence of the prefix;
     *  ]
     *
     * @return array [ prefix|null, string[] tags with optional prefix, string[] tags with prefix required ]
     *               TODO Make this claass or method specific
     */
    abstract function tagsToProcess();

    /**
     * @param ParsedFile $pf
     * @param Class_ $class
     * @param string $tag
     * @param string $content
     */
    public function processClassTag(ParsedFile $pf, Class_ $class, $tag, $content)
    {

    }

    /**
     * @param ParsedFile $pf
     * @param Class_ $class
     * @param Method $method
     * @param string $tag
     * @param string $content
     */
    public function processMethodTag(ParsedFile $pf, Class_ $class, Method $method, $tag, $content)
    {

    }

    /**
     * Onve all files are parsed, this step can be used to check for errors that require all of the data,
     * as well as any other preparation that may be needed for that test and for output
     *
     * @param ParsedFileCache $pfc
     */
    public function postParse(ParsedFileCache $pfc)
    {

    }

    /**
     * Generate the PHP code relative to this plug in.
     * The code will be included into an aggregate file. DO not include start and end php tags
     * @return string
     */
    public function generatePHP()
    {
        return null;
    }

    public function generateJS()
    {
        return null;
    }
}