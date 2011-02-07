<?php

class PassThroughFormatter implements IFormatter
{
    public function initialize($config)
    {
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_LOW;
    }
    
    public function supportsExtension($extension, $isUnformatted)
    {
        return $isUnformatted;
    }
    
    public function format($text)
    {
        return $text;
    }
}
