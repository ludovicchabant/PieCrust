<?php

namespace PieCrust\IO;


class PostInfo
{
    public $year;
    public $month;
    public $day;
    public $name;
    public $extension;
    public $path;

    public $yearValue;
    public $monthValue;
    public $dayValue;

    public function __construct()
    {
    }

    public function getPath($pathFormat)
    {
        $replacements = array(
            '%year%' => $this->year,
            '%month%' => $this->month,
            '%day%' => $this->day,
            '%slug%' => $this->name,
            '%ext%' => $this->extension
        );
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $pathFormat
        );
    }

    public static function fromValues($year, $month, $day, $name, $extension = null, $path = null)
    {
        $pi = new PostInfo();

        $pi->yearValue = $year;
        $pi->monthValue = $month;
        $pi->dayValue = $day;
        
        $pi->year = sprintf("%d", $year);
        $pi->month = sprintf("%02d", $month);
        $pi->day = sprintf("%02d", $day);
        $pi->name = $name;
        $pi->extension = $extension;
        $pi->path = $path;

        return $pi;
    }

    public static function fromStrings($year, $month, $day, $name, $extension = null, $path = null)
    {
        $pi = new PostInfo();

        $pi->yearValue = intval($year);
        $pi->monthValue = intval($month);
        $pi->dayValue = intval($day);
        
        $pi->year = $year;
        $pi->month = $month;
        $pi->day = $day;
        $pi->name = $name;
        $pi->extension = $extension;
        $pi->path = $path;

        return $pi;
    }
}

