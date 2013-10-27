<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\Util\PageHelper;


class TagsCommand extends ChefCommand
{
    public function getName()
    {
        return 'tags';
    }
    
    public function setupParser(Console_CommandLine $parser, IPieCrust $pieCrust)
    {
        $parser->description = "Gets the list of tags used in the website.";
        $parser->addOption('order_by_name', array(
            'short_name'  => '-n',
            'long_name'   => '--order-name',
            'description' => "Orders the results by name.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('order_by_count', array(
            'short_name'  => '-c',
            'long_name'   => '--order-count',
            'description' => "Orders the results by number of posts.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('reverse', array(
            'short_name'  => '-r',
            'long_name'   => '--reverse',
            'description' => "Reverses the ordering.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addOption('count', array(
            'long_name'   => '--count',
            'description' => "Prints only the number of tags.",
            'default'     => false,
            'action'      => 'StoreTrue'
        ));
        $parser->addArgument('blog', array(
            'description' => "Only go through the given blogs. By default, this command goes through all the blogs in the website.",
            'optional'    => true,
            'multiple'    => true
        ));
    }

    public function run(ChefContext $context)
    {
        $logger = $context->getLog();
        $pieCrust = $context->getApp();
        $result = $context->getResult();

        // Validate options.
        if ($result->command->options['order_by_name'] &&
            $result->command->options['order_by_count'])
            throw new PieCrustException("Can't specify both '--order-name' and '--order-count'.");

        $blogKeys = $pieCrust->getConfig()->getValue('site/blogs');
        if ($result->command->args['blog'])
        {
            foreach ($result->command->args['blog'] as $blogKey)
            {
                if (!in_array($blogKey, $blogKeys))
                    throw new PieCrustException("No such blog in the website : {$blogKey}");
            }
            $blogKeys = $result->command->args['blog'];
        }

        $tags = array();
        foreach ($blogKeys as $blogKey)
        {
            $callback = function($post) use (&$tags) {
                $postTags = $post->getConfig()->getValue('tags');
                if ($postTags)
                {
                    if (!is_array($postTags))
                        $postTags = array($postTags);
                    foreach ($postTags as $t)
                    {
                        if (!isset($tags[$t]))
                            $tags[$t] = 0;
                        $tags[$t] += 1;
                    }
                }
            };
            PageHelper::processPosts($pieCrust, $blogKey, $callback);
        }

        // Only print the count?
        if ($result->command->options['count'])
        {
            $logger->info(count($tags));
            return 0;
        }

        // Sort appropriately.
        $reverse = $result->command->options['reverse'];
        if ($result->command->options['order_by_name'])
        {
            if ($reverse)
                krsort($tags);
            else
                ksort($tags);
        }
        else if ($result->command->options['order_by_count'])
        {
            if ($reverse)
                array_multisort($tags, SORT_DESC);
            else
                array_multisort($tags, SORT_ASC);
        }

        // Print the list.
        $logger->info(count($tags) . " tags.");
        foreach ($tags as $t => $count)
        {
            $logger->info("{$t} ({$count} posts)");
        }
    }
}

