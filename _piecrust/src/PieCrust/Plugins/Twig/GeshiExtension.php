<?php

require_once 'Geshi/geshi.php';

require_once 'GeshiTokenParser.php';


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
    
    public function getFunctions()
    {
        return array(
            'geshi_css' => new Twig_Function_Method($this, 'getGeshiCss')
        );
    }
    
    public function getGeshiCss($value)
    {
        $geshi = new Geshi('', $value);
        return $geshi->get_stylesheet(false);
    }
}
