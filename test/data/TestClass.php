<?php


/**
 * Class TestClass
 * @xrouteBase /base
 * @xf3routes\routeBase /base
 * @x\f3routes\routeBase /base
 * @f3routes-routeBase
 *
 * @f3routes\routeMap /map
 */
class TestClass
{

    /**
     * @route GET @somealias: /here
     * @f3routes-route POST /here/f3-routes/post
     * @f3routes\route POST /here/namespaced
     * @\f3routes\devroute POST /dev/namespaced
     * @f3routes-devroute POST|GET /dev/prefixed
     *
     * @\f3routes\routeJS somealias
     */
    public function handler()
    {

    }
}