<?php

namespace PieCrust\Formatters;

use PieCrust\PieCrust;


class TextileFormatter implements IFormatter
{
    public function initialize(PieCrust $pieCrust)
    {
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_DEFAULT;
    }
    
    public function supportsFormat($format, $isUnformatted)
    {
        return $isUnformatted && preg_match('/textile|tex/i', $format);
    }
    
    public function format($text)
    {
        require_once 'Textile/classTextile.php';
        
        $textile = new \Textile();
        return $textile->TextileThis($text);
    }
}
