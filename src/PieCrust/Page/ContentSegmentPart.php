<?php

namespace PieCrust\Page;


/**
 * Part of a content segment.
 */
class ContentSegmentPart
{
    public $content;
    public $format;

    public function __construct($content, $format = null)
    {
        $this->content = $content;
        $this->format = $format;
    }
}

