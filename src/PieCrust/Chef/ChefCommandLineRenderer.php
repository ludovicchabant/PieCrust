<?php

namespace PieCrust\Chef;

use \Phar;


class ChefCommandLineRenderer extends \Console_CommandLine_Renderer_Default
{
    public function __construct($parser = false)
    {
        parent::__construct($parser);
    }

    public function version()
    {
        $vars = array(
            'progname' => $this->name(),
            'version' => $this->parser->version
        );
        $pharPath = Phar::running(false);
        if ($pharPath)
        {
            $phar = new Phar($pharPath);
            $metadata = $phar->getMetadata();
            if ($metadata && isset($metadata['version']))
            {
                $vars['version'] .= " (installed binary version {$metadata['version']})";
            }
        }

        return $this->parser->message_provider->get('PROG_VERSION_LINE', $vars);
    }
}

