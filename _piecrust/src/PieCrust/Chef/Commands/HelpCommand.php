<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;


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
    
    public function setupParser(Console_CommandLine $helpParser, IPieCrust $pieCrust)
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

    public function run(ChefContext $context)
    {
        $result = $context->getResult();

        // Display usage for `chef` along with the list of known additional topics.
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

        // Look for a help topic.
        if (isset($this->parser->helpTopics[$topic]))
        {
            $helpTopic = $this->parser->helpTopics[$topic];
            if (is_callable($helpTopic))
                call_user_func($helpTopic, $context);
            else
                echo $helpTopic;
            exit(0);
        }

        // Command help.
        $parentParser = $this->parser->parent;
        if (isset($parentParser->commands[$topic]))
        {
            echo $parentParser->commands[$topic]->displayUsage(false);
            exit(0);
        }

        throw new PieCrustException("No such command or help topic: " . $topic);
    }
}

