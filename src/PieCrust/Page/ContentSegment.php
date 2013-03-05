<?php

namespace PieCrust\Page;


/**
 * A content segment from a page.
 */
class ContentSegment
{
    public $parts;

    public function __construct($content = null, $format = null)
    {
        $this->parts = array();
        if ($content !== null)
            $this->parts[] = new ContentSegmentPart($content, $format);
    }
}

