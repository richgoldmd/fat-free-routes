<?php

namespace RichardGoldstein\FatFreeRoutes;

use phpDocumentor\Reflection\DocBlock\Tag;
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

    /** @var string[] Verbose output */
    private $output = [];

    public function __construct(ProcessParameters $p)
    {
        $this->params = $p;
    }

    /**
     * Handle verbose output
     *
     * @param string $t String to echo
     */
    protected function echoVerbose($t)
    {
        // Cache verbose output and render it only in case of no error,
        // since STDOUT and STDERR will write asynchronously and terminal output is mangled.
        if ($this->params->verbose) {
            $this->output[] = $t;
        }
    }

    protected function die($x)
    {
        $this->output[] = '';
        $this->output[] = $x;
        if ($x) {
            fwrite(STDERR, $this->getVerboseOutput());
        }
        exit(1);

    }

    public function getVerboseOutput()
    {
        if (count($this->output)) {
            return implode(PHP_EOL, $this->output) . PHP_EOL;
        } else {
            return null;
        }

    }

    /**
     * Retrive a list if matching tags.
     *The DoicBlock is searched for exact matches as well as the same tag prefeixed by f3routes\, \f3routes\, or f3routes-
     *
     * @param array $tagNames Array of tagnames.
     *
     * @param array $prefixRequiredTagNames
     * @param Tag[] $tagList
     *
     * @return array Each element is [ $tagBase, $tag ] where $tagBase is the tag less and prefix (f3routes)
     */
    protected function getTags(array $tagNames, array $prefixRequiredTagNames, array $tagList)
    {
        $matches = [];
        $tagSelector = implode(
            '|',
            array_map(
                function ($tn) {
                    return preg_quote($tn, '/');
                },
                $tagNames
            )
        );
        $tagSelectorPrefixed = implode(
            '|',
            array_map(
                function ($tn) {
                    return preg_quote($tn, '/');
                },
                $prefixRequiredTagNames
            )
        );
        $hasTagNames = count($tagNames);
        $hasPrefixedTagNames = count($prefixRequiredTagNames);
        foreach ($tagList as $tag) {
            $tn = trim($tag->getName());
            if (($hasTagNames && preg_match(
                    '/^(?:\\\\?f3routes\\\\|f3routes-)?(' . $tagSelector . ')$/u',
                    $tn,
                    $m
                )) ||
                ($hasPrefixedTagNames && preg_match(
                    '/^(?:\\\\?f3routes\\\\|f3routes-)(' . $tagSelectorPrefixed . ')$/',
                    $tn,
                    $m
                ))) {
                $matches[] = [$m[1], $tag];
            }
        }
        return $matches;
    }

    /**
     * Set up the RouteCache. If forcing then just create a new o   ne, otherwise attempt to
     * load from the specified file. If file load fails, return gracefully with a new empty
     * RouteCache object.
     */
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

    /**
     * Locate all of the files to parse within a specified directory and add to $this->filesToParse
     * Skip files that have been cached and have not changed.
     *
     * @param $cdir
     */
    private function iterateControllerDirectory($cdir)
    {
        if (strlen($cdir) > 1 && substr($cdir, -1, 1) != DIRECTORY_SEPARATOR) {
            $cdir .= DIRECTORY_SEPARATOR;
        }
        $dir = dir($cdir);
        while (false !== ($e = $dir->read())) {
            if (is_file($cdir . $e)) {
                // Test extension - should be .php
                if (substr(strrchr($e, "."), 1) != 'php') {
                    $this->echoVerbose("Skipping non-php file {$cdir}{$e}.");
                } else {
                    if ($this->routeCache->shouldReloadFile($cdir . $e)) {
                        $this->filesToParse[] = new LocalFile($cdir . $e);
                    } else {
                        $this->echoVerbose("Skipping unchanged file {$cdir}{$e}.");
                    }
                }
            } elseif (is_dir($cdir . $e) && substr($e, 0, 1) != '.') {
                // Recurse
                $this->iterateControllerDirectory($cdir . $e);
            }
        }
    }

    /**
     * Iterator over all of the controller directories and search for files to parse
     */
    private function buildFileList()
    {
        foreach ($this->params->controllers as $cpath) {
            $this->echoVerbose("Scanning Controller directory {$cpath}...");
            $this->iterateControllerDirectory($cpath);
        }
    }


    /**
     * Parse the DocBlock of a class for @routeBase, @routeMap, and @devrouteMap
     *
     * @param Class_ $class
     * @param ParsedFile $pf
     *
     * @return string routeBase
     */
    private function parseClass(Class_ $class, ParsedFile $pf)
    {
        $base = '';

        // TODO - Test that methods implied by routeMap exist in class - but this wont know any
        // PREMAP value - ? set it on command line?
        if (null !== ($cdb = $class->getDocBlock()) ) {
            $tags = $this->getTags(['routeBase'], [], $cdb->getTags());
            // only one routeBase allowed!
            if (count($tags) > 1) {
                //$cn = $class->getName();
                /** @noinspection PhpUndefinedMethodInspection */
                $this->die(
                    "Multiple @routeBase specified for class {$class->getName()} in  {$pf->filename} [near line {$cdb->getLocation()->getLineNumber()}]: " .
                    PHP_EOL . "\t@{$tags[1][1]->getName()} {$tags[1][1]->getDescription()}");

            } else if (count($tags) == 1) {
                /** @noinspection PhpUndefinedMethodInspection */
                $base = trim(explode("\n", $tags[0][1]->getDescription())[0]);
                $this->echoVerbose("Class " . $class->getFqsen() . " Found base tag $base");
            }
        }

        /** var string $routeMapRegex
         * The below code attempts to split the fourth group on commas, but this regex does not yet
         * match commas in the [modifiers] group
         */

        $routeMapRegex = '/(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+)(?:\h+(?:\[(\w+)\]))?/';

        // Test for routeMap
        if ($cdb !== null /*&& ($cdb->hasTag('routeMap') || $cdb->hasTag('devrouteMap')) */) {
            $routeMapTags = $this->getTags(['routeMap','devrouteMap'], [], $cdb->getTags());
            foreach ($routeMapTags as $rtag) {
                /** @noinspection PhpUndefinedMethodInspection */
                $data = trim(explode("\n", $rtag[1]->getDescription())[0]);
                if (preg_match($routeMapRegex, $data, $parts)) {
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
                            strtolower($rtag[0]),
                            $parts[1],
                            isset($parts[4]) && in_array('js', explode(',', strtolower($parts[4])))
                        )
                    );
                } else {
                    // This is an error that should halt processing
                    /** @noinspection PhpUndefinedMethodInspection */
                    $this->die(
                        "Poorly formed @routeMap in {$pf->filename} [near line {$cdb->getLocation()->getLineNumber()}]: " .
                        PHP_EOL . "\t@{$rtag[1]->getName()} $data"
                    );
                }
            }
        }
        return $base;

    }

    /**
     * Parse the DocBlock of a class method, looking for @route, @devroute, @routeJS
     *
     * @param Class_ $class
     * @param Method $method
     * @param $base
     * @param ParsedFile $pf
     */
    private function parseMethod(Class_ $class, Method $method, $base, ParsedFile $pf)
    {
        $db = $method->getDocBlock();

        if (null !== $db) {
            $js_aliases = [];
            $jsTags = $this->getTags(['routeJS'], [], $db->getTags());

            // Get all the tags that specify aliases to emit into js
            foreach ($jsTags as $rtag) {
                /** @noinspection PhpUndefinedMethodInspection */
                $data = trim(explode("\n", $rtag[1]->getDescription())[0]);
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


            // Collect aliases found to validate @routeJS
            $aliases_found = [];
            $rtags = $this->getTags(['route','devroute'], [], $db->getTags());
            //if (($db->hasTag('route') || $db->hasTag('devroute'))) {
            if (count($rtags)) {

                $connector = $method->isStatic() ? '::' : '->';

                // Doesnt work on re-aliased routes

                /** @var \phpDocumentor\Reflection\DocBlock\Tag $t */
                foreach ($rtags as $rtag) {
                    // Extract the path string ($parts[3])
                    /** @noinspection PhpUndefinedMethodInspection */
                    $descrip = trim(explode("\n", $rtag[1]->getDescription())[0]);
                    if (preg_match($routeRegex, $descrip, $parts)) {
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
                        if ($parts[2] != '') {
                            $aliases_found[] = $parts[2];
                        }
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
                                strtolower($rtag[0]),
                                $parts[2],
                                $js || in_array($parts[2], $js_aliases),
                                $ttl,
                                $kbps
                            )
                        );
                    } else {
                        // This is an error that should halt processing
                        /** @noinspection PhpUndefinedMethodInspection */
                        $this->die(
                            "Poorly formed @route in {$pf->filename} [near line {$db->getLocation()->getLineNumber()}]: " .
                            PHP_EOL . "\t@{$rtag[1]->getName()} $descrip"
                        );
                    }
                }
            }
            // All of the routeJS aliases should be defined
            $not_found = array_diff($js_aliases, $aliases_found);
            if (count($not_found)) {
                $this->die(
                    "Undefined @routeJS alias" . (count($not_found) > 1 ? 'es' : '') .
                    " in {$pf->filename} [near line {$db->getLocation()->getLineNumber()}]: " .
                    PHP_EOL . "\t" .
                    implode(', ', $not_found)
                );

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
            $this->die($e->getMessage());
        }
    }

    private function renderTemplate($file, $content)
    {
        $template = file_get_contents($file);
        if ($template === false) {
            $this->die("Error loading template file $file");
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
            $this->die("Error writing output file {$this->params->phpOutput}.");
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
            $this->die("Error writing output file {$this->params->phpOutput}.");
        }

    }

    /**
     *
     */
    private function checkForErrors()
    {
        // Test for duplicates
        $aliases = array_filter(
            array_map(
                function (Route $rl) {
                    return ($rl->alias !== null && trim($rl->alias) != '') ? trim($rl->alias) : null;
                },
                $this->routes
            )
        );
        $duplicates = array_unique(array_diff_assoc($aliases, array_unique($aliases)));
        if (count($duplicates)) {
            $this->die(
                "Duplicate aliases found:" . PHP_EOL . "\t" .
                implode(
                    ', ',
                    array_map(
                        function ($a) {
                            return "@{$a}";
                        },
                        $duplicates
                    )
                )
            );
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

        $this->routes = $this->routeCache->getSortedList();

        // Check for duplicates...
        $this->checkForErrors();

        // Save route cache file if specified
        if ($this->params->cacheFilename) {
            $this->echoVerbose("Saving cache file {$this->params->cacheFilename}.");
            $this->routeCache->saveToFile($this->params->cacheFilename);
        }
        //
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