<?php

require 'ChefServer.class.php';

error_reporting(E_ALL);

try
{
    $server = new ChefServer(dirname(dirname(__FILE__)) . '/website');
    $server->run();
}
catch (Exception $e)
{
    echo $e;
    echo PHP_EOL;
}


/*Array
(
    [TZ] => Canada/Vancouver
    [HTTP_HOST] => localhost:8888
    [HTTP_CONNECTION] => keep-alive
    [HTTP_USER_AGENT] => Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; en-US) AppleWebKit/534.16 (KHTML, like Gecko) Chrome/10.0.648.133 Safari/534.16
    [HTTP_ACCEPT] => application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*\/*;q=0.5
    [HTTP_ACCEPT_ENCODING] => gzip,deflate,sdch
    [HTTP_ACCEPT_LANGUAGE] => en-US,en;q=0.8,fr-FR;q=0.6
    [HTTP_ACCEPT_CHARSET] => ISO-8859-1,utf-8;q=0.7,*;q=0.3
    [PATH] => /usr/bin:/bin:/usr/sbin:/sbin
    [SERVER_SIGNATURE] => <address>Apache/2.0.63 (Unix) PHP/5.3.2 DAV/2 Server at localhost Port 8888</address> 
 
    [SERVER_SOFTWARE] => Apache/2.0.63 (Unix) PHP/5.3.2 DAV/2
    [SERVER_NAME] => localhost
    [SERVER_ADDR] => ::1
    [SERVER_PORT] => 8888
    [REMOTE_ADDR] => ::1
    [DOCUMENT_ROOT] => /Applications/MAMP/htdocs
    [SERVER_ADMIN] => you@example.com
    [SCRIPT_FILENAME] => /Users/abdul/Work/WebSites/PieCrust/website/index.php
    [REMOTE_PORT] => 55497
    [GATEWAY_INTERFACE] => CGI/1.1
    [SERVER_PROTOCOL] => HTTP/1.1
    [REQUEST_METHOD] => GET
    [QUERY_STRING] => 
    [REQUEST_URI] => /piecrust/
    [SCRIPT_NAME] => /piecrust/index.php
    [PHP_SELF] => /piecrust/index.php
    [REQUEST_TIME] => 1300154454
    [argv] => Array
        (
        )
 
    [argc] => 0
)*/
