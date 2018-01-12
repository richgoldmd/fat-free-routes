<?php
/**
 *
 * User: richardgoldstein
 * Date: 1/11/18
 * Time: 8:57 AM
 */

namespace RichardGoldstein\FatFreeRoutes\Plugins;


interface PluginRegistrar
{
    public function registerPlugin(Plugin $p);
}
