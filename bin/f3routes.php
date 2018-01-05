#!/usr/bin/env php
<?php

use RichardGoldstein\FatFreeRoutes\MakeRoutes;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

function cli_die($x = null) {
	if ($x) {
		fwrite(STDERR, $x);
	}
    exit(1);
}

/*
 * Command line parameters
 *
    -f, --force                Ignore any cached data and rebuild the route table
    -v, --verbose              Be verbose (default is silent except for error)
    --controller-dir=<dirname> Path to a directory to parse recursively. Can be repeated.
    --cache-file=<filename>    The file in which to store the route table cache. Optional.
    --output-php=<filename>    The file to create the route table in. Required.
    --output-js=<filename>     The file to create the js alias router in. Optional.
 */

// getopts will cli_die() if the params are not valid.
$proc = new MakeRoutes(\RichardGoldstein\FatFreeRoutes\ProcessParameters::getopts());
$proc->run();

exit(0);
