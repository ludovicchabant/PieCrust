<?php

namespace PieCrust\IO;

use PieCrust\PieCrustDefaults;


class PagesRecursiveFilterIterator extends \RecursiveFilterIterator
{
    public function accept()
    {
        $filter = array(
            'Thumbs.db',
            PieCrustDefaults::CATEGORY_PAGE_NAME . '.html',
            PieCrustDefaults::TAG_PAGE_NAME . '.html'
        );

        $current = $this->current()->getFilename();
        if ($current[0] == '.')
            return false;
        if (in_array($current, $filter))
            return false;
        if ($current[strlen($current) - 1] == '~')
            return false;
        return true;
    }

}
