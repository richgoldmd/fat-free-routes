<?php


/**
 * Class TestClass
 * @xrouteBase /base
 * @xf3routes\routeBase /base
 * @\f3routes\routeBase /base/@id/12
 * x@f3routes-routeBase /base
 *
 * @f3routes\routeMap /map [js]
 */
class TestClass
{

    /**
     * @route POST @somealias: /here [js]
     * @f3routes-route POST @jsalias: /here/f3-routes/post [js]
     * @f3routes\route POST /here/namespaced
     * @\f3routes\devroute POST /dev/namespaced [ajax,ttl=3600,kbps=64]
     * @f3routes-devroute POST|GET /dev/prefixed
     * @f4routes-route GET /nope
     */
    public function handler()
    {

    }
}