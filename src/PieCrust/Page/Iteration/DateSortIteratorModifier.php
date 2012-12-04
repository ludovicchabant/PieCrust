<?php

namespace PieCrust\Page\Iteration;

use PieCrust\PieCrustException;


class DateSortIteratorModifier extends BaseIteratorModifier
{
    public function dependsOnOrder()
    {
        return false;
    }

    public function affectsOrder()
    {
        return true;
    }

    public function modify($items)
    {
        if (false === usort($items, array($this, "sortByReverseTimestamp")))
            throw new PieCrustException("Error while sorting posts by timestamp.");
        return $items;
    }

    protected function sortByReverseTimestamp($post1, $post2)
    {
        $timestamp1 = $post1->getDate();
        $timestamp2 = $post2->getDate();

        if ($timestamp1 == $timestamp2)
            return 0;
        if ($timestamp1 < $timestamp2)
            return 1;
        return -1;
    }
}

