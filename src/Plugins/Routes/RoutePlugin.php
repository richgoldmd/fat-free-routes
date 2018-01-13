<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/11/18
 * Time: 5:41 PM
 */

namespace RichardGoldstein\FatFreeRoutes\Plugins\Routes;


use GetOpt\GetOpt;
use GetOpt\Option;
use phpDocumentor\Reflection\Php\Class_;
use phpDocumentor\Reflection\Php\Method;
use RichardGoldstein\FatFreeRoutes\ParsedFile;
use RichardGoldstein\FatFreeRoutes\ParsedFileCache;
use RichardGoldstein\FatFreeRoutes\Plugins\Plugin;

class RoutePlugin extends Plugin
{
    private $hasRouteBase = false;
    private $routeBase = '';

    // Post-parse data
    private $routes = [];


    public function setCommandLineOptions(GetOpt $opts)
    {
        $opts->addOption(
            Option::create(null, 'no-f3routes', GetOpt::NO_ARGUMENT)
                  ->setDescription('Disable the Fat-Free Routes plugin.')
        );
        return '';
    }

    public function parseOptions(GetOpt $opts)
    {
        // Return false if this is disabled.
        return !$opts->getOption('no-f3routes');
    }

    public function tagsToProcess()
    {
        return ['f3routes',
                // Tags that dont require a prefix
                [
                    'route',
                    'devroute',
                    'routeMap',
                    'devrouteMap',
                    'routeBase'
                    // 'routeJS' - Deprecated in this version
                ],
                // tags that DO require a prefix
                []
        ];
    }

    const ROUTE_MAP_REGEX = '/(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+)(?:\h+(?:\[(\w+)\]))?/';


    public function startClass(ParsedFile $pf, Class_ $class)
    {
        parent::startClass($pf, $class);
        $this->hasRouteBase = false;
        $this->routeBase = '';
    }

    /**
     * @param ParsedFile $pf
     * @param Class_ $class
     * @param string $tag
     * @param string $content
     *
     * @throws \Exception
     */
    public function processClassTag(ParsedFile $pf, Class_ $class, $tag, $content)
    {
        $fqsen = (string)$class->getFqsen();

        // We only use the first line of the docBlock
        $content = trim(explode("\n", $content)[0]);

        switch ($tag) {
            case 'routeBase':
                if ($this->hasRouteBase) {
                    // There can be only one
                    $cdb = $class->getDocBlock();
                    throw new \Exception(
                        "Multiple @routeBase specified for class {$class->getName()} in  {$pf->filename} [near line {$cdb->getLocation()->getLineNumber()}]: @{$tag} {$content}"
                    );
                } else {
                    if (!preg_match('|^(?:/[^\h]+)+$|u', $content)) {
                        throw new \Exception("Poorly formed routeBase: $content");
                    }
                    $this->routeBase = $content;
                    $this->hasRouteBase = true;
                    $this->echoVerbose("Class $fqsen: @routeBase {$this->routeBase}");
                }
                break;
            case 'routeMap':
            case 'devrouteMap':
                if (preg_match(self::ROUTE_MAP_REGEX, $content, $parts)) {
                    // @alias: /some/path/@token [option,options,...]
                    // $alias = 1, $path = 2, option = 4
                    $rpath = $this->routeBase . $parts[2];
                    if ($rpath != '/' && substr($rpath, -1, 1) == '/') {
                        $rpath = substr($rpath, 0, -1);
                    }

                    $pf->addData(
                        get_class(),
                        new MapRoute(
                            '',
                            $fqsen,
                            $rpath,
                            strtolower($tag),
                            $parts[1],
                            isset($parts[4]) && in_array('js', explode(',', strtolower($parts[4])))
                        )
                    );
                    $this->echoVerbose("Class $fqsen: @routeMap $rpath");

                } else {
                    // This is an error that should halt processing
                    /** @noinspection PhpUndefinedMethodInspection */
                    $cdb = $class->getDocBlock();
                    throw new \Exception(
                        "Poorly formed @routeMap in {$pf->filename} [near line {$cdb->getLocation()->getLineNumber()}]: " .
                        "@{$tag} {$content}"
                    );
                }
                break;
        }
    }


    const TYPES = ['sync', 'ajax', 'cli'];

    /*
     * Original regex, which accepts only the above modifiers in the [] at the end of the route
     *
     *       $routeRegex = '/([\|\w]+)\h+(?:(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+))' .
     *   '(?:\h+\[(' . implode('|', $types) . ')\])?/u';
     *
     * This regex matches bracket patterns like
     *   [ajax]
     *   [ajax,param=value]
     * With allowance for spaces around the commas and equals
     *   [ param= value , param2 =value2 ]
     *
     *    /\[\h*((?:\w+(?:\h*=\h*\w+)?\h*(?:,\h*)?)+)\]/
     *
     *
     * New Regex, which allows for word characters, equals, spaces, and commas
     *    \[\h*((?:\w+(?:\h*=\h*\w+)?\h*(?:,\h*)?)+)\]
     */
    const ROUTE_REGEX = '/([\|\w]+)\h+(?:(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+))' .
    '(?:\h+\[\h*((?:\w+(?:\h*=\h*\w+)?\h*(?:,\h*)?)+)\])?/u';


    /**
     * @param ParsedFile $pf
     * @param Class_ $class
     * @param Method $method
     * @param string $tag
     * @param string $content
     *
     * @throws \Exception
     */
    public function processMethodTag(ParsedFile $pf, Class_ $class, Method $method, $tag, $content)
    {
        $fqsen = (string)$method->getFqsen();

        $content = trim(explode("\n", $content)[0]);

        if ($tag == 'route' || $tag == 'devroute') {

            $connector = $method->isStatic() ? '::' : '->';

            // Doesnt work on re-aliased routes

            // Extract the path string ($parts[3])
            /** @noinspection PhpUndefinedMethodInspection */
            if (preg_match(self::ROUTE_REGEX, $content, $parts)) {
                $rpath = $this->routeBase . $parts[3];
                if ($rpath != '/' && substr($rpath, -1, 1) == '/') {
                    $rpath = substr($rpath, 0, -1);
                }
                // $parts 5 may have options not recognized by F3- so find those that are (only allows one)
                // and recompose $parts5
                $ttl = null;
                $kbps = null;
                $js = false;
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
                            if (in_array(strtolower($opt[0]), self::TYPES)) {
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
                $pf->addData(
                    get_class(),
                    new Route(
                        $rr,
                        (string)$class->getFqsen() . $connector . $method->getName(),
                        $rpath,
                        strtolower($tag),
                        $parts[2],
                        $js,
                        $ttl,
                        $kbps
                    )
                );
                $this->echoVerbose("Method $fqsen: @$tag $rpath");

            } else {
                // This is an error that should halt processing

                $cdb = $method->getDocBlock();
                throw new \Exception(
                    "Poorly formed @route in {$pf->filename} [near line {$cdb->getLocation()->getLineNumber()}]: " .
                    "@{$tag} {$content}"
                );

            }

        }

    }


    private function getSortedRouteList(ParsedFileCache $pfc)
    {
        $a = [];
        /** @var ParsedFile $f */
        foreach ($pfc->files as $f) {
            $a = array_merge($a, $f->getData(get_class()));
        }
        Route::sortRoutes($a);
        $this->routes = $a;
        return $this->routes;
    }

    /**
     * @param ParsedFileCache $pfc
     *
     * @throws \Exception
     */
    public function postParse(ParsedFileCache $pfc)
    {
        $this->getSortedRouteList($pfc);

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
            throw new \Exception(
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

    public function generatePHP()
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

        return self::PHP_CODE_START . $content . self::PHP_CODE_END;

    }

    public function generateJS()
    {
        $lines = [];
        foreach ($this->routes as $r) {
            if (in_array($r->tag, ['route', 'routemap']) && $r->emitJS && $r->alias != '') {
                $lines[] = "        \"{$r->alias}\": \"{$r->path}\"";
            }
        }
        return self::JS_CODE_START . implode("," . PHP_EOL, $lines) . self::JS_CODE_END;

    }


    const PHP_CODE_START = <<<'CODE_START'
/**
 * Route Table fort FatFreeFramework Application auto-generated by FatFreeRoutes
 * DO NOT EDIT BY HAND - This will be overwritten
 *
 * @param bool $includeDev Include @devroute routes
 *
 * Call this method from index.php before calling $f3->run();
 */
function installRoutes($includeDev = true) {
    $f3 = \Base::instance();

CODE_START;

    const PHP_CODE_END = <<<'CODE_END'

};

/* Usage:
    installRoutes(true|false);
*/

CODE_END;

    const JS_CODE_START = <<<'JS_CODE_START'
var RouteAliases = {

    "alias": function(name, data) {
        if (this.routes.hasOwnProperty(name)) {
            var r = this.routes[name];
            return r.replace(/@([^@\/]+)/g, function (m1, m2) {
                return data.hasOwnProperty(m2) ? data[m2] : '';
            });
        } else {
            return '';
        }
    },
    "routes": {

JS_CODE_START;

    const JS_CODE_END = <<<'JS_CODE_END'

    }

};
/*
Usage:
    var url = RouteAliases.alias('removeCartItem', { cat: 'SomeCat', oid: 54 });
*/
JS_CODE_END;


}