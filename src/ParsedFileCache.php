<?php

namespace RichardGoldstein\FatFreeRoutes;


/**
 * Class to create, read, write, update the cache of route data retrieved from files.
 * This is serializable. Serialization errors should just result in a full re-parse
 *
 * User: richardgoldstein
 * Date: 8/29/17
 * Time: 1:16 PM
 */
class ParsedFileCache
{
    /**
     * @var ParsedFile[]
     */
    public $files = [];

    public function __construct() {
    }

    /**
     * Add a ParsedFile object to this cache
     * @param ParsedFile $f
     */
    public function addFile(ParsedFile $f)
    {
        $this->files[$f->filename] = $f;
    }

    /**
     * Test if a given file with specified name and mtime should be reloaded
     *
     * @param $filename
     *
     * @return bool
     */
    public function shouldReloadFile($filename)
    {
        if (!isset($this->files[$filename])) {
            return true;
        }
        return $this->files[$filename]->mtime < filemtime($filename);
    }

    /**
     * Save the cache to a file.
     * @param $filename
     *
     * @return bool
     */
    public function saveToFile($filename) {
        // Serialize paranoia
        try {
            return false !== file_put_contents($filename, serialize($this));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Load a ParsedFileCache from a file.
     * This method is paranoid about serialization errors. Since its a dev-time tool, we handle gracefully
     * but return a flag so failure can be reported.
     *
     * @param $filename
     * @param $didSucceed
     *
     * @return mixed|ParsedFileCache
     */
    public static function loadFromFile($filename, &$didSucceed) {
        if (!file_exists($filename)) {
            $didSucceed = false;
            return new self();
        }

        $c = file_get_contents($filename);
        if ($c === false || trim($c) == '') {
            $didSucceed = false;
            return new self();
        } else {
            // A little paranoia
            try {
                $cache = unserialize($c);
                if ($cache === false || !$cache instanceof self) {
                    $didSucceed = false;
                    return new self();
                } else {
                    $didSucceed = true;
                    return $cache;
                }
            } catch (\Throwable $e) {
                $didSucceed = false;
                return new self();
            }
        }
    }
}
