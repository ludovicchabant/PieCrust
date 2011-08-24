<?php

// Setup the environment.
error_reporting(E_ALL ^ E_NOTICE);

set_include_path(get_include_path() . PATH_SEPARATOR . 
                 (dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . '_piecrust') . PATH_SEPARATOR .
                 (dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . '_chef'));
