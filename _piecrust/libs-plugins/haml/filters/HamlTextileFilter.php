<?php

require_once 'libs/phamlp/haml/filters/_HamlTextileFilter.php';


class HamlTextileFilter extends _HamlTextileFilter
{
    public function init()
    {
        $this->vendorPath = PIECRUST_APP_DIR . 'libs/textile/classTextile.php';
        $this->vendorClass = 'Textile';
        parent::init();
    }
    
    public function run($text)
    {
        $text = str_replace('"', '\"', $text);  // Looks like the PHamlP parser is too dumb to escape quotes before inserting this in PHP code... *sigh*
        return parent::run($text);
    }
}
