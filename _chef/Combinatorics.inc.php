<?php

function fact($int)
{
    if ($int < 2)
        return 1;
    for ($f = 2; $int-1 > 1; $f *= $int--);
    return $f;
}

function array_combinations(array $tags)
{
    $results = array(array());
    foreach ($tags as $tag)
    {
        $num = count($results);
        for ($i = 0; $i < $num; $i++)
        {
            array_push($results, array_merge($results[$i], array($tag)));
        }
    }
    array_shift($results);
    return $results;
}

function array_deepsort(array &$arr)
{
    $num = count($arr);
    for ($i = 0; $i < $num; $i++)
    {
        sort($arr[$i]);
    }
    sort($arr);
}
