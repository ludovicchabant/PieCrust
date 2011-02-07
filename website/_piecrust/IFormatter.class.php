<?php

interface IFormatter
{
    const PRIORITY_HIGH = 1;
    const PRIORITY_DEFAULT = 0;
    const PRIORITY_LOW = -1;
    
    public function initialize($config);
    public function getPriority();
    public function supportsExtension($extension, $isUnformatted);
    public function format($text);
}

