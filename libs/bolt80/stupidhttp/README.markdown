StupidHttp
==========

As the name indicates, **StupidHttp** is a very simple (and not very smart) HTTP
library for PHP. It only supports one client, and implements only the minimum
features of the HTTP spec (and probably even less). However, it's a very
helpful library to have if you want to quickly preview a static website locally,
or if you want your application to feature a development web server.

## Quick Start

Here's a very quick example:

    require 'vendor/bolt80/stupidhttp/lib/StupidHttp/Autoloader.php';
    StupidHttp_Autoloader::register();

    $server = new StupidHttp_WebServer('www_root', 8080);
    $server->on('GET', '/')->call(function ($r) { echo 'Hello world!'; });
    $server->run(array('run_browser' => true));

What this does:

* includes and registers the StupidHttp auto-loader
* creates a new webserver on port `8080` with a static files root directory set
  to `www_root` (relative to the current working directory).
* defines a route that will respond `Hello world!` when a client requests the
  website's root.
* runs the default browser.

## Examples

StupidHttp ships with a few examples, appropriately located in the `examples`
directory. Just run the PHP file from the command-line and you should see a
simple web application show up in your default browser.

## Other Resources

For more information, documentation and examples, check out the [StupidHttp
website](http://bolt80.com/stupidhttp/).

