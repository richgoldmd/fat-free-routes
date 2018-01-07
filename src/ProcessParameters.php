<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/5/18
 * Time: 8:51 AM
 */

namespace RichardGoldstein\FatFreeRoutes;


/**
 * Class ProcessParameters
 *
 * @package RichardGoldstein\FatFreeRoutes
 */
class ProcessParameters
{
    /**
     * @var bool
     */
    public $verbose = false;
    /**
     * @var bool
     */
    public $force = false;

    public $cacheFilename = null;

    public $controllers = [];

    public $phpOutput = null;

    public $jsOutput = null;


    public static function usage($error = null)
    {
        $x = ($error) ? PHP_EOL . $error . PHP_EOL . PHP_EOL : '';

        fwrite(STDERR, $x.
        <<<OUTPUT
Usage: f3routes [options]

Generate Fat Free Framework Route table from DocBlock tags

    -f, --force                Ignore any cached data and rebuild the route table
    -v, --verbose              Be verbose (default is silent except for error)
    --controller-dir=<dirname> Path to a directory to parse recursively. Can be repeated.
    --cache-file=<filename>    The file in which to store the route table cache. Optional.
    --output-php=<filename>    The file to create the route table in. Required.
    --output-js=<filename>     The file to create the js alias router in. Optional.

Example:
    f3routes -v --cache-file=/myproject/routeCache.f3r --controller-dir=/myproject/controllers \\
            --output-php=/myproject/src/generated/routes.php


OUTPUT
        );
        exit(1);
    }

    /**
     * @codeCoverageIgnore Difficult to test because of the way getopt() uses argc/argv
     * @return ProcessParameters
     */
    public static function getopts()
    {
        $p = new self();

        $opts = 'vf';
        $longopts = [
            'cache-file::',
            'controller-dir:',
            'force',
            'verbose',
            'output-php::',
            'output-js::'
        ];

        $options = getopt($opts, $longopts);
        foreach ($options as $k => $v) {
            switch ($k) {
                case 'v':
                case 'verbose':
                    $p->verbose = true;
                    break;
                case 'f':
                case 'force':
                    $p->force = true;
                    break;
                case 'cache-file':
                    if (is_array($v)) {
                        self::usage("Only one cache-file file is allowed.");
                    } else {
                        $p->cacheFilename = $v;
                    }
                    break;
                case 'output-php':
                    if (is_array($v)) {
                        self::usage("Only one output-php file is allowed.");
                    } else {
                        $p->phpOutput = $v;
                    }
                    break;
                case 'output-js':
                    if (is_array($v)) {
                        self::usage("Only one output-js file is allowed.");
                    } else {
                        $p->jsOutput = $v;
                    }
                    break;
                case 'controller-dir':
                    if (!is_array($v)) {
                        $v = [$v];
                    }
                    foreach ($v as $fn) {
                        $p->controllers[] = $fn;
                    }
                    break;
            }
        }

        if (!count($p->controllers)) {
            self::usage('No controller directory specified.');
        }

        if (!$p->phpOutput) {
            self::usage('output-php file is required.');
        }

        if (!is_dir($x = dirname($p->phpOutput))) {
            self::usage("--output-php {$x} is not a valid directory.");
        };

        if ($p->jsOutput && !is_dir($x = dirname($p->jsOutput))) {
            self::usage("--output-js {$x} is not a valid directory.");
        };

        if ($p->cacheFilename && !is_dir($x = dirname($p->cacheFilename))) {
            self::usage("--cache-file {$x} is not a valid directory.");
        }

        foreach ($p->controllers as $d) {
            if (!is_dir($d)) {
                self::usage("--controller-dir {$d} is not a valid directory.");
            }
        }

        return $p;
    }
}