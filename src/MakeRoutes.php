<?php

namespace RichardGoldstein\FatFreeRoutes;

use phpDocumentor\Reflection\File\LocalFile;
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

    private function parseFiles()
    {
        $projectFactory = ProjectFactory::createInstance();
        try {
            $project = $projectFactory->create('Routing', $this->filesToParse);

            $types = ['sync', 'ajax', 'cli'];


            // Iterate the reflection of the files that needed updating and update the cache object
            /** @var \phpDocumentor\Reflection\Php\File $f */
            foreach ($project->getFiles() as $f) {
                // Create new Parsed File object
                $pf = new ParsedFile($f->getPath());

                // Iterate the classes and methods to find routes
                // Locate methods with a @Route annotation
                foreach ($f->getClasses() as $class) {

                    if (null !== ($cdb = $class->getDocBlock()) && ($cdb->hasTag('routeBase'))) {
                        $tag = $cdb->getTagsByName('routeBase');
                        /** @noinspection PhpUndefinedMethodInspection */
                        $base = trim($tag[0]->getDescription());
                        $this->echoVerbose("Class " . $class->getFqsen() . " Found base tag $base");
                    } else {
                        $base = '';
                    }
                    foreach ($class->getMethods() as $method) {
                        $db = $method->getDocBlock();

                        if (null !== $db) {

                            // Get all the tags that specify aliases to emit into js
                            $emitJStags = $db->getTagsByName('routeJS');
                            $js_aliases = [];
                            foreach ($emitJStags as $e) {
                                /** @noinspection PhpUndefinedMethodInspection */
                                $data = $e->getDescription();
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

                            if (($db->hasTag('route') || $db->hasTag('devroute'))) {

                                $connector = $method->isStatic() ? '::' : '->';

                                $tags = array_merge($db->getTagsByName('route'), $db->getTagsByName('devroute'));
                                // Doesnt work on re-aliased routes

                                /** @var \phpDocumentor\Reflection\DocBlock\Tag $t */
                                foreach ($tags as $tag) {
                                    // Extract the path string ($parts[3])
                                    /** @noinspection PhpUndefinedMethodInspection */
                                    preg_match(
                                        '/([\|\w]+)\h+(?:(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+))' .
                                        '(?:\h+\[(' . implode('|', $types) . ')\])?/u',
                                        $tag->getDescription(),
                                        $parts
                                    );
                                    $rpath = $base . $parts[3];
                                    if ($rpath != '/' && substr($rpath, -1, 1) == '/') {
                                        $rpath = substr($rpath, 0, -1);
                                    }
                                    $rr =
                                        $parts[1] . ' ' .
                                        (trim($parts[2]) != '' ? ('@' . $parts[2] . ': ') : '') .
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
                                            in_array($parts[2], $js_aliases)
                                        )
                                    );
                                }
                            }
                        }
                    }
                }
                // Add or update the ParsedFile object in the current cache
                $this->routeCache->addFile($pf);
            }
        } catch (\Exception $e) {
            cli_die($e->getMessage());
        }
    }

    private function renderTemplate($file, $content) {
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
            if ($r->tag == 'route') {
                $content .= '    ' . $r->makePHP();
            }
        }

        $content .= PHP_EOL . '   if ($includeDev) {' . PHP_EOL;

        foreach ($this->routes as $r) {
            if ($r->tag == 'devroute') {
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
            if ($r->tag == 'route' && $r->emitJS && $r->alias != '') {
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