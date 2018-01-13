<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/11/18
 * Time: 9:22 AM
 */

namespace RichardGoldstein\FatFreeRoutes;


use GetOpt\ArgumentException;
use GetOpt\ArgumentException\Missing;
use GetOpt\GetOpt;
use GetOpt\Option;
use phpDocumentor\Reflection\File\LocalFile;
use phpDocumentor\Reflection\Php\Class_;
use phpDocumentor\Reflection\Php\Method;
use phpDocumentor\Reflection\Php\ProjectFactory;
use RichardGoldstein\FatFreeRoutes\Plugins\Plugin;
use RichardGoldstein\FatFreeRoutes\Plugins\PluginManager;
use RichardGoldstein\FatFreeRoutes\Plugins\PluginRegistrar;

class TagProcessor implements PluginRegistrar
{
    /**
     * @var PluginManager
     */
    protected $pluginMgr;

    /**
     * @var GetOpt
     */
    private $getOpt = null;

    /**
     * @var string
     */
    private $additionalHelp = '';

    /** @var ParsedFileCache */
    private $parsedFileCache;

    private $cacheFileName = null;

    private $filesToParse = [];

    /**
     * @var bool Verbose output
     */
    private $verbose = false;

    public function __construct()
    {
        $this->pluginMgr = new PluginManager();
    }


    public function registerPlugin(Plugin $p)
    {
        $this->pluginMgr->registerPlugin($p);
        $p->setEchoCallback([$this, 'echoVerbose']);
    }


    public function run()
    {
        try {

            // 0. Plugins have already been registered.
            // 1. Setup standard options
            $this->setupOptions();

            // 2. Allow plugins the opportunity to update the options, save additional help text
            $this->additionalHelp = $this->pluginMgr->setCommandLineOptions($this->getOpt);

            // 3. Attempt to parse the command line
            $this->parseCommandLine();  // throws on error

            // 4. Allow the plugins to get info from paramaters
            $this->pluginMgr->parseOptions($this->getOpt);

            if (!$this->pluginMgr->countActive()) {
                throw new \Exception('There are no active plugins. No action taken.');
            }
            // 5. Prepare the plugins with the default prefix
            $this->pluginMgr->preparePlugins('f3routes');

            // 6. Prepare Route Cache
            $this->getRouteCache();

            // 7. Build the list of files to parse
            $this->buildFileList();

            // 8. Parse the files - This will update all of the ParsedFile objects for touched files.
            $this->parseFiles();

            // 9. Post parse handling/error checking
            $this->pluginMgr->postParse($this->parsedFileCache);

            // 10. Save cache file if specified
            if ($this->cacheFileName) {
                $this->echoVerbose("Saving cache file {$this->cacheFileName}.");
                $this->parsedFileCache->saveToFile($this->cacheFileName);
            }

            // 11. Collect php snippets and generate file
            if (false === file_put_contents(
                    $this->getOpt->getOption('output-php'),
                    self::PHP_START . $this->pluginMgr->generatePHP() . self::PHP_END
                )) {
                throw new \Exception("Error writing output file {$this->getOpt->getOption('output-php')}.");
            }

            // 12. Collect JS snippets and generate file
            if ($this->getOpt->getOption('output-js')) {
                if (false === file_put_contents(
                        $this->getOpt->getOption('output-js'),
                        self::JS_START . $this->pluginMgr->generateJS() . self::JS_END
                    )) {
                    throw new \Exception("Error writing output file {$this->getOpt->getOption('output-js')}.");
                }

            }

            $this->echoVerbose('Done.');
        } catch (\Exception $e) {
            file_put_contents('php://stderr', $e->getMessage() . PHP_EOL);
            exit(1);
        }

    }

    private function parseCommandLine()
    {
        try {
            try {
                $this->getOpt->process();

                if ($this->getOpt->getOption('help')) {
                    echo $this->getOpt->getHelpText() . PHP_EOL . $this->additionalHelp . PHP_EOL;
                    exit(0);
                }

                // Test require options
                foreach (['source-dir', 'output-php'] as $opt) {
                    if (!$this->getOpt->getOption($opt)) {
                        throw new ArgumentException("Option '$opt' must be specified.");
                    }
                }

                $this->verbose = $this->getOpt->getOption('verbose');

            } catch (Missing $exception) {
                // catch missing exceptions if help is requested
                if (!$this->getOpt->getOption('help')) {
                    throw $exception;
                }
            }
        } catch (ArgumentException $exception) {
            file_put_contents('php://stderr', PHP_EOL . $exception->getMessage() . PHP_EOL);
            echo PHP_EOL . $this->getOpt->getHelpText() . PHP_EOL . $this->additionalHelp . PHP_EOL;
            exit(1);
        }
    }

    private function setupOptions()
    {
        $this->getOpt = new GetOpt();


        $this->getOpt->addOptions(
            [
                Option::create('h', 'help', GetOpt::NO_ARGUMENT)
                      ->setDescription('Show this help and quit.'),

                Option::create('f', 'force', GetOpt::NO_ARGUMENT)
                      ->setDescription('Ignore any cached data and rebuild the route table'),

                Option::create('v', 'verbose', GetOpt::NO_ARGUMENT)
                      ->setDescription('Be verbose (default is silent except for error)'),

                Option::create('s', 'source-dir', GetOpt::MULTIPLE_ARGUMENT)
                      ->setDescription(
                          'Path containing PHP files to scan. Will recurse sub-directories. ' .
                          'Can be repeated to specify multiple directories. Required.'
                      )
                      ->setArgumentName('source-dir'),

                Option::create('o', 'output-php', GetOpt::REQUIRED_ARGUMENT)
                      ->setDescription('The PHP output file. Required.')
                      ->setArgumentName('php-file'),

                Option::create('j', 'output-js', GetOpt::REQUIRED_ARGUMENT)
                      ->setDescription('The JS output file. Optional.')
                      ->setArgumentName('js-file'),

                Option::create('c', 'cache-file', GetOpt::REQUIRED_ARGUMENT)
                      ->setDescription('The file in which to store the route table cache. Optional.')
                      ->setArgumentName('cache-file')
                      ->setValidation(
                          function ($v) {
                              return is_dir(dirname($v));
                          }
                      )
            ]
        );

    }

    /**
     * Set up the ParsedFileCache. If forcing then just create a new o   ne, otherwise attempt to
     * load from the specified file. If file load fails, return gracefully with a new empty
     * ParsedFileCache object.
     */
    private function getRouteCache()
    {
        $this->cacheFileName = $this->getOpt->getOption('cache-file');

        if ($this->getOpt->getOption('force')) {
            $this->parsedFileCache = new ParsedFileCache();
            $this->echoVerbose("Forcing rebuild of route table.");
            return;
        }

        if (null !== $this->cacheFileName ) {
            $this->parsedFileCache = ParsedFileCache::loadFromFile($this->cacheFileName, $didSucceed);
            if ($didSucceed) {
                $this->echoVerbose("ParsedFileCache loaded from {$this->cacheFileName}");
            } else {
                $this->echoVerbose("Could not load ParsedFileCache from file. Continuing anyway.");
            }
        } else {
            $this->parsedFileCache = new ParsedFileCache();
        }

    }

    private function buildFileList()
    {
        foreach ($this->getOpt->getOption('source-dir') as $cpath) {
            $this->echoVerbose("Scanning Source directory {$cpath}...");
            $this->iterateSourceDirectory($cpath);
        }
    }

    private function iterateSourceDirectory($cdir)
    {
        if ($cdir != DIRECTORY_SEPARATOR && substr($cdir, -1, 1) != DIRECTORY_SEPARATOR) {
            $cdir .= DIRECTORY_SEPARATOR;
        }
        $dir = dir($cdir);
        while (false !== ($e = $dir->read())) {
            if (is_file($cdir . $e)) {
                // Test extension - should be .php
                if (substr(strrchr($e, "."), 1) != 'php') {
                    $this->echoVerbose("Skipping non-php file {$cdir}{$e}.");
                } else {
                    if ($this->parsedFileCache->shouldReloadFile($cdir . $e)) {
                        $this->filesToParse[] = new LocalFile($cdir . $e);
                    } else {
                        $this->echoVerbose("Skipping unchanged file {$cdir}{$e}.");
                    }
                }
            } elseif (is_dir($cdir . $e) && substr($e, 0, 1) != '.') {
                // Recurse
                $this->iterateSourceDirectory($cdir . $e);
            }
        }
    }

    /**
     * @throws \phpDocumentor\Reflection\Exception
     */
    private function parseFiles()
    {
        $projectFactory = ProjectFactory::createInstance();
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

                $this->pluginMgr->startClass($pf, $class);
                $this->pluginMgr->processClass($pf, $class);


                /** @var Method $method */
                foreach ($class->getMethods() as $method) {
                    $this->pluginMgr->startMethod($pf, $class, $method);
                    $this->pluginMgr->processMethod($pf, $class, $method);
                    $this->pluginMgr->endMethod($pf, $class, $method);

                }
                $this->pluginMgr->endClass($pf, $class);

            }

            // Add or update the ParsedFile object in the current cache
            $this->parsedFileCache->addFile($pf);
        }
    }


    public function echoVerbose($t)
    {
        if ($this->verbose) {
            echo $t . PHP_EOL;
        }
    }

    const PHP_START = <<<'PHP_START'
<?php
/*
    FatFreeRoutes
    Auto-generated routes file for FatFreeFramework project.
    Tool by Rich Goldstein, MD
*/

PHP_START;

    const PHP_END = <<<'PHP_END'

// EOF

PHP_END;

    const JS_START = <<<'JS_START'
/*
    Auto-generated file for FatFreeFramework project.
    Tool by Rich Goldstein, MD
*/

JS_START;

const JS_END= <<<'JS_END'

// EOF

JS_END;



}
