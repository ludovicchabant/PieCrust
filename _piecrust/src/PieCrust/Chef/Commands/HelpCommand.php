<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;


class HelpCommand extends ChefCommand
{
    protected $parser;

    public function getName()
    {
        return 'help';
    }

    public function requiresWebsite()
    {
        return false;
    }
    
    public function setupParser(Console_CommandLine $helpParser)
    {
        $helpParser->description = "Gets help on chef.";
        $helpParser->addArgument('topic', array(
            'description' => "The command or topic on which to get help.",
            'help_name'   => 'TOPIC',
            'optional'    => true
        ));

        $helpParser->helpTopics = array();

        $this->parser = $helpParser;
    }

    public function run(IPieCrust $pieCrust, Console_CommandLine_Result $result)
    {
        if (!isset($result->command->args['topic']))
        {
            $this->parser->parent->displayUsage(false);
            echo "Additional help topics:" . PHP_EOL;
            foreach (array_keys($this->parser->helpTopics) as $topic)
            {
                echo "  {$topic}" . PHP_EOL;
            }
            echo PHP_EOL;
            exit(0);
        }

        $topic = $result->command->args['topic'];
        if (!isset($this->parser->helpTopics[$topic]))
            throw new PieCrustException("No such help topic: " . $topic);

        echo $this->parser->helpTopics[$topic];
    }
}

