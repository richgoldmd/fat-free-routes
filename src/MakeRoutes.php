<?php

namespace RichardGoldstein\FatFreeRoutes;

use phpDocumentor\Reflection\Php\Method;
use phpDocumentor\Reflection\File\LocalFile;
use phpDocumentor\Reflection\Php\Class_;
use phpDocumentor\Reflection\Php\ProjectFactory;


/**
 *
 * User: richardgoldstein
 * Date: 1/5/18
 * Time: 6:59 AM
 */
class MakeRoutes
{
    /**
     * @var ProcessParameters
     */
    private $params;

    /**
     * @var RouteCache
     */
    private $routeCache = null;

    /**
     * @var LocalFile[]
     */
    private $filesToParse = [];

    /**
     * @var Route[]
     */
    private $routes = [];

    public function __construct(ProcessParameters $p)
    {
        $this->params = $p;
    }

    protected function echoVerbose($t)
    {
        if ($this->params->verbose) {
            echo $t . PHP_EOL;
        }
    }

    protected function getRouteCache()
    {
        if ($this->params->force) {
            $this->routeCache = new RouteCache();
            $this->echoVerbose("Forcing rebuild of route table.");
            return;
        }
        if ($this->params->cacheFilename) {
            $this->routeCache = RouteCache::loadFromFile($this->params->cacheFilename, $didSucceed);
            if ($didSucceed) {
                $this->echoVerbose("RouteCache loaded from {$this->params->cacheFilename}");
            } else {
                $this->echoVerbose("Could not load RouteCache from file. Continuing anyway.");
            }
        } else {
            $this->routeCache = new RouteCache();
        }

    }

    private function iterateControllerDirectory($cdir)
    {
        if (strlen($cdir) > 1 && substr($cdir, -1, 1) != DIRECTORY_SEPARATOR) {
            $cdir .= DIRECTORY_SEPARATOR;
        }
        $dir = dir($cdir);
        while (false !== ($e = $dir->read())) {
            if (is_file($cdir . $e)) {
                if ($this->routeCache->shouldReloadFile($cdir . $e)) {
                    $this->filesToParse[] = new LocalFile($cdir . $e);
                } else {
                    $this->echoVerbose("Skipping unchanged file {$cdir}{$e}.");
                }
            } elseif (is_dir($cdir . $e) && substr($e, 0, 1) != '.') {
                // Recurse
                $this->iterateControllerDirectory($cdir . $e);
            }
        }
    }

    private function buildFileList()
    {
        foreach ($this->params->controllers as $cpath) {
            $this->echoVerbose("Scanning Controller directory {$cpath}...");
            $this->iterateControllerDirectory($cpath);
        }
    }


    /**
     * @param Class_ $class
     * @param ParsedFile $pf
     *
     * @return string routeBase
     */
    private function parseClass(Class_ $class, ParsedFile $pf)
    {

        if (null !== ($cdb = $class->getDocBlock()) && ($cdb->hasTag('routeBase'))) {
            $tag = $cdb->getTagsByName('routeBase');
            /** @noinspection PhpUndefinedMethodInspection */
            $base = trim(explode("\n", $tag[0]->getDescription())[0]);
            $this->echoVerbose("Class " . $class->getFqsen() . " Found base tag $base");
        } else {
            $base = '';
        }

        /** var string $routeMapRegex
         * The below code attempts to split the fourth grou pon commas, but this regex does not yet
         * match commas in the [modifiers] group
         */

        $routeMapRegex = '/(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+)(?:\h+(?:\[(\w+)\]))?/';
        // Test for routeMap
        if ($cdb !== null && ($cdb->hasTag('routeMap') || $cdb->hasTag('devrouteMap'))) {
            $tags = array_merge($cdb->getTagsByName('routeMap'), $cdb->getTagsByName('devrouteMap'));
            foreach ($tags as $tag) {
                /** @noinspection PhpUndefinedMethodInspection */
                $data = trim(explode("\n", $tag->getDescription())[0]);
                preg_match(
                    $routeMapRegex,
                    $data,
                    $parts
                );
                // @alias: /some/path/@token [option,options,...]
                // $alias = 1, $path = 2, option = 4
                $rpath = $base . $parts[2];
                if ($rpath != '/' && substr($rpath, -1, 1) == '/') {
                    $rpath = substr($rpath, 0, -1);
                }

                /** @noinspection PhpUndefinedMethodInspection */
                $pf->addRoute(
                    new MapRoute(
                        '',
                        $class->getFqsen(),
                        $rpath,
                        strtolower($tag->getName()),
                        $parts[1],
                        isset($parts[4]) && in_array('js', explode(',', strtolower($parts[4])))
                    )
                );
            }
        }
        return $base;

    }

    /**
     * @param Class_ $class
     * @param Method $method
     * @param $base
     * @param ParsedFile $pf
     */
    private function parseMethod(Class_ $class, Method $method, $base, ParsedFile $pf)
    {
        $db = $method->getDocBlock();

        if (null !== $db) {

            // Get all the tags that specify aliases to emit into js
            $emitJStags = $db->getTagsByName('routeJS');
            $js_aliases = [];
            foreach ($emitJStags as $e) {
                /** @noinspection PhpUndefinedMethodInspection */
                $data = trim(explode("\n", $e->getDescription())[0]);
                $aliases = explode(',', $data);
                $js_aliases = array_merge(
                    $js_aliases,
                    array_filter(
                        array_map(
                            function ($e) {
                                return trim($e) != '' ? trim($e) : null;
                            },
                            $aliases
                        )
                    )
                );
            }

            $types = ['sync', 'ajax', 'cli'];

            /*
             * Original regex, which accepts only the above modifiers in the [] at the end of the route
             */
            /*
            $routeRegex = '/([\|\w]+)\h+(?:(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+))' .
                '(?:\h+\[(' . implode('|', $types) . ')\])?/u';
            */

            /*
             * This regex matches bracket patterns like
             * [ajax]
             * [ajax,param=value]
             * With allowance for spaces around the commas and equals
             * [ param= value , param2 =value2 ]
             *
             * /\[\h*((?:\w+(?:\h*=\h*\w+)?\h*(?:,\h*)?)+)\]/
             */
            /*
             * New Regex, which allows for word characters, equals, spaces, and commas
             * \[\h*((?:\w+(?:\h*=\h*\w+)?\h*(?:,\h*)?)+)\]
             */
            $routeRegex = '/([\|\w]+)\h+(?:(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+))' .
                '(?:\h+\[\h*((?:\w+(?:\h*=\h*\w+)?\h*(?:,\h*)?)+)\])?/u';


            if (($db->hasTag('route') || $db->hasTag('devroute'))) {

                $connector = $method->isStatic() ? '::' : '->';

                $tags = array_merge($db->getTagsByName('route'), $db->getTagsByName('devroute'));
                // Doesnt work on re-aliased routes

                /** @var \phpDocumentor\Reflection\DocBlock\Tag $t */
                foreach ($tags as $tag) {
                    // Extract the path string ($parts[3])
                    /** @noinspection PhpUndefinedMethodInspection */
                    preg_match(
                        $routeRegex,
                        trim(explode("\n", $tag->getDescription())[0]),
                        $parts
                    );
                    $rpath = $base . $parts[3];
                    if ($rpath != '/' && substr($rpath, -1, 1) == '/') {
                        $rpath = substr($rpath, 0, -1);
                    }
                    // $parts 5 may have options not recognized by F3- so find those that are (only allows one)
                    // and recompose $parts5
                    $ttl = null;
                    $kbps = null;
                    $js = null;
                    if (isset($parts[5])) {
                        $options = trim($parts[5]);
                        unset($parts[5]);
                        if ($options == '') {
                            unset($parts[5]);
                        } else {
                            $options = array_map(
                                function ($o) {
                                    $opt = explode('=', $o, 2);
                                    $opt[0] = trim($opt[0]);
                                    if (isset($opt[1])) {
                                        $opt[1] = trim($opt[1]);
                                    } else {
                                        $opt[1] = null;   // so we dont have to check isset() again
                                    }
                                    return $opt;
                                },
                                explode(',', $options)
                            );

                            // set flags based on options
                            foreach ($options as $opt) {
                                if (in_array(strtolower($opt[0]), $types)) {
                                    $parts[5] = strtolower($opt[0]);    // restore F3 type modifier
                                } else {
                                    switch (strtolower($opt[0])) {
                                        case 'js':
                                            $js = true;
                                            break;
                                        case 'ttl':
                                            $ttl = (int)$opt[1];
                                            break;
                                        case 'kbps':
                                            $kbps = (int)$opt[1];
                                            break;

                                    }
                                }
                            }
                        }
                    }
                    $parts[2] = trim($parts[2]);
                    $rr =
                        $parts[1] . ' ' .
                        ($parts[2] != '' ? ('@' . $parts[2] . ': ') : '') .
                        $rpath .
                        (isset($parts[5]) ? (' [' . $parts[5] . ']') : '');
                    // Save the route in the current ParsedFile object
                    /** @noinspection PhpUndefinedMethodInspection */
                    $pf->addRoute(
                        new Route(
                            $rr, //'' . $tag->getDescription(),
                            $class->getFqsen() . $connector . $method->getName(),
                            $rpath, // $base  . $parts[3],
                            strtolower($tag->getName()),
                            $parts[2],
                            $js || in_array($parts[2], $js_aliases),
                            $ttl,
                            $kbps
                        )
                    );
                }
            }
        }

    }

    private function parseFiles()
    {
        $projectFactory = ProjectFactory::createInstance();
        try {
            $project = $projectFactory->create('Routing', $this->filesToParse);


            // Iterate the reflection of the files that needed updating and update the cache object
            /** @var \phpDocumentor\Reflection\Php\File $f */
            foreach ($project->getFiles() as $f) {
                // Create new Parsed File object
                $pf = new ParsedFile($f->getPath());


                // Iterate the classes and methods to find routes
                // Locate methods with a @Route annotation
                /** @var Class_ $class */
                foreach ($f->getClasses() as $class) {

                    $base = $this->parseClass($class, $pf);


                    /** @var Method $method */
                    foreach ($class->getMethods() as $method) {
                        $this->parseMethod($class, $method, $base, $pf);
                    }
                }
                // Add or update the ParsedFile object in the current cache
                $this->routeCache->addFile($pf);
            }
        } catch (\Exception $e) {
            cli_die($e->getMessage());
        }
    }

    private function renderTemplate($file, $content)
    {
        $template = file_get_contents($file);
        if ($template === false) {
            cli_die("Error loading template file $file");
        }
        return str_replace('%%CONTENT%%', $content, $template);
    }

    private function writePhp()
    {
        $content = '';
        foreach ($this->routes as $r) {
            if ($r->tag == 'route' || $r->tag == 'routemap') {
                $content .= '    ' . $r->makePHP();
            }
        }

        $content .= PHP_EOL . '   if ($includeDev) {' . PHP_EOL;

        foreach ($this->routes as $r) {
            if ($r->tag == 'devroute' || $r->tag == 'devroutemap') {
                $content .= '       ' . $r->makePHP();
            }
        }

        $content .= '   }' . PHP_EOL;

        if (false === file_put_contents(
                $this->params->phpOutput,
                $this->renderTemplate(__DIR__ . '/template/php_template.php.templ', $content)
            )) {
            cli_die("Error writing output file {$this->params->phpOutput}.");
        }

    }

    private function writeJS()
    {
        $lines = [];
        foreach ($this->routes as $r) {
            if (in_array($r->tag, ['route', 'routemap']) && $r->emitJS && $r->alias != '') {
                $lines[] = "\t\t\"{$r->alias}\": \"{$r->path}\"";
            }
        }
        $content = implode("," . PHP_EOL, $lines);

        if (false === file_put_contents(
                $this->params->jsOutput,
                $this->renderTemplate(__DIR__ . '/template/js_template.js.templ', $content)
            )) {
            cli_die("Error writing output file {$this->params->phpOutput}.");
        }

    }

    public function run()
    {

        // Get Cache object
        $this->getRouteCache();

        // Build the file list
        $this->buildFileList();

        // Parse the files
        $this->parseFiles();

        // Save route cache file if specified
        if ($this->params->cacheFilename) {
            $this->echoVerbose("Saving cache file {$this->params->cacheFilename}.");
            $this->routeCache->saveToFile($this->params->cacheFilename);
        }
        //
        $this->routes = $this->routeCache->getSortedList();
        //
        // Write the PHP File
        $this->echoVerbose("Writing PHP File {$this->params->phpOutput}.");
        $this->writePhp();
        if ($this->params->jsOutput) {
            $this->echoVerbose("Writing JS File {$this->params->jsOutput}.");
            $this->writeJS();
        }
        $this->echoVerbose("Done.");
    }

}