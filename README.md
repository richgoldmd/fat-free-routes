FatFreeRoutes
=============
#### Note:
This tool has been completely refactored into a plugable architecture to allow for future expansion. 
Note the following:
1. The command line parameter names have changed
2. The @routeJS tag is no longer supported, and has been replaced by the `[js]` modifier.
3. The documentation is a work-in-progress.

***

### Note that this version is being deprecated and has been moved to the legacy branch 
The code has been refactored into a pluggable architecture to allow for future expansion, 
and will be posted shortly.

***

PHP >= 7.0 (To use the tool), 
PHP >= 5.4 (For generated code)

This development tool allows one to specify routes in the 
[Fat Free Framework](http://fatfreeframework.com) in DocBlock format in the 
controller class. Furthermore, it is build with a pluggable interface to allow for future
expansion beyond route generation.

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

The tool, `f3routes` produces a php file suitable to be `required` in `index.php` and
provides a method to install the routes.
```php
function installRoutes($includeDev = true) {
   // ...
}
```

Require the generated file in `index.php` and call `installRoutes($includeDev);` 
before calling `$f3->run();`

There is also the option to generate a JavaScript file so that routes can be easily built in 
Javascript for front-end use.


Tags
----
The following DocBlock tags can be used

`@route`
Applies to a class method, and follows the same syntax as Fat Free Framework routes, using
the syntax 
```
   @route <METHOD> @alias: path [ajax|cli|sync]
```
Replaceable tokens can be used in the path. The alias is optional, as are the route modifiers
in brackets `[  ]`.

`f3routes` supports additional values in the route modifier portion surrounded by brackets `[ ]`.
This can be a comma-separated list of the existing F3 modifiers (ajax, cli, or sync), and the
following additional modifiers:
* `ttl=<number>` Set the `$ttl` parameter to `Base->route()`
* `kbps=<number>` Set the `$kbps` parameter to `Base->route()`
* `js` Expose the alias in the Javascript code. 

The order of the items in the modifier section in unimportant, except that only the last
one of `ajax`, `sync`, or `cli` are preserved.

Here are some examples of `@route` tags using modifiers:

```
   @route GET|POST @alias: /some/path/@id [sync]
   @route GET @alias2: /some/other/path [ajax,js,ttl=3600]
```  

`@devroute` Same as `@route` but only installed if installRoutes() is called with `true`.

`@routeBase` Specified in the DocBlock for the class, this prepends a path fragment to all
of the route paths specified in the methods by `@route` or `@devroute`. 

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
If a @routeBase is set, the map path will also be prepended.

Note that f3routes supports the proposed PSR-5 PHPDoc standard: all tags can be prefixed 
or namespaced with `f3routes`. The following are equivalent:

```php
   /**
    *
    * @route GET /mypath
    * @f3routes-route GET /mypath
    * @f3routes\route GET /mypath
    * @\f3routes\route GET /mypath
    */
    public function myHandler(....) {
        // ...
```

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
    
* `--source-dir=<directory>`

    *This is required.* Specifies the directory that contains PHP files. This
    parameter can be specified more than once. Directories are scanned recursively.
    
* `--output-php=<file>`

   *This is required.* Specifies the file to be written with the generated PHP code.
   
* `--output-js=<file>`

    This is an optional parameter. If specified, a fle containing JS code and a route map
    will be produced. Only routes explicitly flagged for JS output will be emitted,    


One use case is to incorporate f3routes into a file watcher, and update the routes in 
real-time as the controller classes are modified.

Example
-------
```bash
   f3routes --source-dir=/myprojects/src/controllers \
         --output-php=/myproject/src/generated/routes.php \
         --cache-file=/myproject/src/generated/cache.f3r
```

Installation
------------
`f3routes` can be installed via composer:
```bash
   composer require --dev richardgoldstein/fat-free-routes
```
You may need to specify the minimum stability as this is still in dev.

Once installed via composer, `f3routes` can be found in vendor/bin:
```bash
   ./vendor/bin/f3routes --controller-dir=...
```
or
```bash
   composer exec f3routes ...
```

I include it in a gulp task:
```js

var run=require('gulp-run');

// ...

gulp.task('php-routes', ['clean-dist-app'], function(cb) {
    var cmd = new run.Command([
        './vendor/bin/f3routes',
        '--cache-file=./conf/route-cache.f3r',
        '--source-dir=./src/controllers',
        '--output-php=./conf/routes.php',
        '--output-js=./assets/js/generated/routes.js'
    ].join(' '));
   cmd.exec('', cb);
});

```
TODO
----
1. Elaborate on the JavaScript output
2. [Plug-In documentation](PLUGINS.md)
3. Needs new unit tests since refactoring.
3. More examples
4. How to use the rendered PHP and JavaScript

