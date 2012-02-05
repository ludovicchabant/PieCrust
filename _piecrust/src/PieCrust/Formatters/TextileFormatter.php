<?php

namespace PieCrust\Formatters;

use PieCrust\IPieCrust;


class TextileFormatter implements IFormatter
{
    public function initialize(IPieCrust $pieCrust)
    {
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_DEFAULT;
    }

    public function isExclusive()
    {
        return true;
    }
    
    public function supportsFormat($format)
    {
        return preg_match('/textile|tex/i', $format);
    }
    
    public function format($text)
    {
        require_once 'Textile/classTextile.php';
        
        $textile = new \Textile();
        return $textile->TextileThis($text);
    }
}
