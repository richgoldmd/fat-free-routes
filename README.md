FatFreeRoutes
=============

PHP >= 7.0 (To use the tool), 
PHP >= 5.4 (For generated code)

This development tool allows one to specify routes in the 
[Fat Free Framework](http://fatfreeframework.com) in DocBlock format in the 
controller class.

Example
--------

```php

class MyFatFreeController {

   /**
    * @route GET @alias: /path/here
    *
    * @param \Base $f3
    * @param array $args
    */
   public function someHandler(\Base $f3, $args) {
      //...
   }

}

```

Adding the `@route` tag above is equivalent to calling 
```php
$f3->route('GET @alias: /path/here', 'MyFatFreeController->someHandler');
```
or specifying the route in a config file.

The too, `f3route` produces a php file suitable to be `required` in `index.php` and
provides a method to install the routes.
```php
function installRoutes($includeDev = true) {
   // ...
}
```

Require the generated file in `index.php` and call `installRoutes($includeDev);` before calling `$f3->run();`

There is also the option to generate a JavaScript file so that routes can be easily built in 
Javascript for front-end use.


Tags
----
The following DocBlock tags can be used

`@route`
Applies to a class method, and follows the same syntax as Fat Free Framework routes, using
the syntax 
```php
   @route <METHOD> [@alias:] path [ajax|cli|sync]
```
Replaceable tokens can be used.

`@devroute` Same as `@route` but only installed if installRoutes() is called with `true`.

`@routeJS` specifies which aliases are exposed in Javascript. This can be a comma separated list of
aliases defined in the same docblock.

`@routeBase` Specified in the DocBlock for the class, this prepends a path fragment to all
of the route paths specified in the methods by `@route` or `@devroute`

`@routeMap` and `@devrouteMap` The equivalent of the F3 map function, these create routes for
RESTful controllers. These also allow specification of an alias for exposure in the
Javascript output, which is enabled by adding `[js]` at the end of the tag:

```php
/**
 *
 * @routeMap @mapAlias: /company/@id [js]
 *
 */
class Company {
    // ...
}
```

This is equivalent to 
```php
$f3->map('/company/@id', 'Company');
```
If a routeBase is set, the map path will also be prepended.


Command Line Parameters
-----------------------
* `-f, --force`

    Force the entire route map to be regenerated. If no cache file is specified,
    this has no effect, as the route table will be generated from scratch in that case.
    
* `-v, --verbose`

    Verbose output.
    
* `--cache-file=<file>`

    If specified, a cache-file will save the resulting route map, so that only 
    files that have changed need to be re-parsed. The same cache file should be 
    used on subsequent calls for the same project.
    
* `--controller-dir=<directory>`

    *This is required.* Specifies the directory that contains controller files. This
    parameter can be specified more than once. Directories are scanned recusrsively.
    
* `--output-php=<file>`

   *This is required.* Specifies the file to be written with the generated PHP code.
   
* `--output-js=<file>`

    This is an optional parameter. If specified, a fle containing JS code and a route map
    will be produced. Only routes explicitly flagged for JS output will be emitted,    

Example
-------
```bash
   f3routes --controller-dir=/myprojects/src/controllers \
         --output-php=/myproject/src/generated/routes.php \
         --cache-file=/myproject/src/generated/cache.f3r
```

TODO
----
1. Elaborate on the JavaScript output
2. Command Line Parameters
3. More examples
4. How to use the rendered PHP and JavaScript