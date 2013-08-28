<?php

namespace PieCrust\Chef;

use PieCrust\PieCrustDefaults;


/**
 * A PEAR log with pretty colors, at least on Mac/Linux.
 */
class ChefLogConsole extends \Log_console
{
    protected $color;
    protected $hasColorSupport;

    public function __construct($name, $ident = '', $conf = array(), $level = PEAR_LOG_DEBUG)
    {
        $conf = array_merge(
            array('lineFormat' => '%{message}'),
            $conf
        );
        parent::__construct($name, $ident, $conf, $level);

        $this->color = new \Console_Color2();
        $this->hasColorSupport = true;
        if (PieCrustDefaults::IS_WINDOWS())
            $this->hasColorSupport = false;
    }

    public function supportsColors()
    {
        return $this->hasColorSupport;
    }

    public function convertColors($str)
    {
        if (!$this->hasColorSupport)
            return $this->color->strip($str);
        return $this->color->convert($str);
    }

    public function log($message, $priority = null)
    {
        if ($this->hasColorSupport)
        {
            if ($priority === null)
                $priority = $this->_priority;

            $colorCode = false;
            switch ($priority)
            {
            case PEAR_LOG_EMERG:
            case PEAR_LOG_ALERT:
            case PEAR_LOG_CRIT:
                $colorCode = "%R";
                break;
            case PEAR_LOG_ERR:
                $colorCode = "%r";
                break;
            case PEAR_LOG_WARNING:
                $colorCode = "%y";
                break;
            case PEAR_LOG_DEBUG:
                $colorCode = "%K";
                break;
            }
            if ($colorCode)
            {
                $message = $this->color->convert(
                    $colorCode . 
                    $this->color->escape($message) .
                    "%n"
				);
            }
        }

        return parent::log($message, $priority);
    }
}
