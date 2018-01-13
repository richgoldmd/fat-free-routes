<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/11/18
 * Time: 9:23 AM
 */

namespace RichardGoldstein\FatFreeRoutes\Plugins;


use GetOpt\GetOpt;
use phpDocumentor\Reflection\Php\Class_;
use phpDocumentor\Reflection\Php\Method;
use RichardGoldstein\FatFreeRoutes\ParsedFile;
use RichardGoldstein\FatFreeRoutes\ParsedFileCache;

class PluginManager implements PluginRegistrar
{
    /**
     * @var PluginEntry[]
     */
    protected $plugins = [];


    public function registerPlugin(Plugin $p)
    {
        $this->plugins[get_class($p)] = new PluginEntry($p);
    }

    /**
     * Count the active plugins
     *
     * @return int
     */
    public function countActive()
    {
        return array_reduce($this->plugins, function($c, $o) {
            return $c + ($o->active ? 1 : 0);
        }, 0);
    }

    /**
     * @param string $prefix PSR-5 Compatible prefix for the tags
     * @param string[] $tags Array of tags (without prefix)
     * @param bool $prefixMandatory Is thre prefix mandatory for these tags
     *
     * @return null|string
     */
    private function getRegex($prefix, $tags, $prefixMandatory)
    {
        if (!is_array($tags) || !count($tags)) {
            return null;
        }
        $prefix = preg_quote($prefix, '/');
        $tagSelector = implode(
            '|',
            array_map(
                function ($tn) {
                    return preg_quote($tn, '/');
                },
                $tags
            )
        );
        $prefixModifier = $prefixMandatory ? '' : '?';
        return '/^(?:\\\\?' . $prefix . '\\\\|' . $prefix . '-)' . $prefixModifier . '(' . $tagSelector . ')$/u';

    }

    public function setCommandLineOptions(GetOpt $opts)
    {
        $help = [];
        foreach ($this->plugins as &$p) {
            $h = $p->plugin->setCommandLineOptions($opts);
            if ($h && trim($h) != '') {
                $help[] = $h;
            }
        }
        return implode(PHP_EOL, $help);
        // $opts->setHelp($helpDefault . implode(PHP_EOL, $help));
    }

    /**
     * Have each plugin parse the command line options and return if it will be active or not.
     *
     * @param GetOpt $opts
     */
    public function parseOptions(GetOpt $opts)
    {
        foreach ($this->plugins as &$p) {
            $p->active = $p->plugin->parseOptions($opts);
        }
    }

    /**
     * Prepare the plugins and generate the regexes for the tags
     *
     * @param $defaultPrefix
     */
    public function preparePlugins($defaultPrefix)
    {
        foreach ($this->plugins as &$p) {
            if ($p->active) {
                list($p->prefix, $p->tags, $p->prefixedTags) = $p->plugin->tagsToProcess();

                // Make the regexes for this plugins tags
                $prefix = $p->prefix ?? $defaultPrefix;
                $p->tagRegex = $this->getRegex($prefix, $p->tags, false);
                $p->prefixedTagRegex = $this->getRegex($prefix, $p->prefixedTags, true);
            }
        }
    }


    private function matchTag($tag)
    {
        $matches = [];
        $tn = trim($tag);

        // Given a tag, get a list of matching plugins along with the base tag name for that service that matched.
        foreach ($this->plugins as $key => $p) {
            if ($p->active) {
                if (($p->tagRegex && preg_match($p->tagRegex, $tn, $m)) ||
                    ($p->prefixedTagRegex && preg_match($p->prefixedTagRegex, $tn, $m))) {
                    $matches[] = [$key, $m[1], $tag];
                }
            }
        }
        return $matches;
    }

    public function startClass(ParsedFile $pf, Class_ $class)
    {
        foreach ($this->plugins as &$p) {
            if ($p->active) {
                $p->plugin->startClass($pf, $class);
            }
        }
    }

    public function startMethod(ParsedFile $pf, Class_ $class, Method $method)
    {
        foreach ($this->plugins as &$p) {
            if ($p->active) {
                $p->plugin->startMethod($pf, $class, $method);
            }
        }
    }

    public function endMethod(ParsedFile $pf, Class_ $class, Method $method)
    {
        foreach ($this->plugins as &$p) {
            if ($p->active) {
                $p->plugin->endMethod($pf, $class, $method);
            }
        }
    }

    public function endClass(ParsedFile $pf, Class_ $class)
    {
        foreach ($this->plugins as &$p) {
            if ($p->active) {
                $p->plugin->endClass($pf, $class);
            }
        }
    }

    public function processClass(ParsedFile $pf, Class_ $class)
    {
        if (null !== ($cdb = $class->getDocBlock())) {
            foreach ($cdb->getTags() as $tag) {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->processClassTag($pf, $class, $tag->getName(), trim($tag->getDescription()));
            }
        }
    }

    public function processClassTag(ParsedFile $pf, Class_ $class, $tagName, $content)
    {
        $matches = $this->matchTag($tagName);
        foreach ($matches as $m) {
            $this->plugins[$m[0]]->plugin->processClassTag($pf, $class, $m[1], $content);
        }
    }

    public function processMethod(ParsedFile $pf, Class_ $class, Method $method)
    {
        if (null !== ($mdb = $method->getDocBlock())) {
            foreach ($mdb->getTags() as $tag) {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->processMethodTag($pf, $class, $method, $tag->getName(), trim($tag->getDescription()));
            }
        }

    }

    public function processMethodTag(ParsedFile $pf, Class_ $class, Method $method, $tagName, $content)
    {
        $matches = $this->matchTag($tagName);
        foreach ($matches as $m) {
            $this->plugins[$m[0]]->plugin->processMethodTag($pf, $class, $method, $m[1], $content);
        }
    }

    public function postParse(ParsedFileCache $pfc)
    {
        foreach ($this->plugins as $key => $p) {
            if ($p->active) {
                $p->plugin->postParse($pfc);
            }
        }
    }

    public function generatePHP()
    {
        $snips = [];
        foreach ($this->plugins as $key => $p) {
            if ($p->active) {
                $snips[] = $p->plugin->generatePHP();
            }
        }

        return implode(PHP_EOL . PHP_EOL, array_filter($snips));
    }

    public function generateJS()
    {
        $snips = [];
        foreach ($this->plugins as $key => $p) {
            if ($p->active) {
                $snips[] = $p->plugin->generateJS();
            }
        }

        return implode(PHP_EOL . PHP_EOL, array_filter($snips));
    }


}
