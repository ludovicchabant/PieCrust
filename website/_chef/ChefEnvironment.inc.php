<?php

// Add the PieCrust app directory to the include path.
set_include_path(get_include_path() . PATH_SEPARATOR . 
				 (dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . '_piecrust') . PATH_SEPARATOR .
				 (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'libs'));

