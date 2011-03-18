<?php

require_once 'GeshiTokenParser.php';
require_once 'libs/geshi/geshi.php';


class GeshiExtension extends Twig_Extension
{
    public function getName()
    {
        return "geshi";
    }
    
    public function getTokenParsers()
    {
        return array(
            new GeshiTokenParser(),
        );
    }
}
