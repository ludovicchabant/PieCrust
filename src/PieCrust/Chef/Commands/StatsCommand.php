<?php

namespace PieCrust\Chef\Commands;

use \Console_CommandLine;
use \Console_CommandLine_Result;
use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustException;
use PieCrust\Chef\ChefContext;
use PieCrust\IO\FileSystem;
use PieCrust\Util\PageHelper;
use PieCrust\Util\UriBuilder;


class StatsCommand extends ChefCommand
{
    public function getName()
    {
        return 'stats';
    }
    
    public function setupParser(Console_CommandLine $statsParser, IPieCrust $pieCrust)
    {
        $statsParser->description = "Gets some information about the current website.";
    }

    public function run(ChefContext $context)
    {
        $logger = $context->getLog();
        $pieCrust = $context->getApp();
        $result = $context->getResult();

        // Site title.
        $title = $pieCrust->getConfig()->getValue('site/title');
        if ($title == null)
            $title = "[Unknown Website Title]";

        // Compute the page count.
        $pageCount = 0;
        $callback = function ($page) use (&$pageCount) {
            $pageCount++;
        };
        PageHelper::processPages($pieCrust, $callback);

        // Compute the post count.
        $postCounts = array();
        $blogKeys = $pieCrust->getConfig()->getValueUnchecked('site/blogs');
        foreach ($blogKeys as $blogKey)
        {
            $postCounts[$blogKey] = count($pieCrust->getEnvironment()->getPosts($blogKey));
        }

        $logger->info("Stats for '{$title}':");
        $logger->info("Root  : {$pieCrust->getRootDir()}");
        $logger->info("Pages : {$pageCount}");
        foreach ($blogKeys as $blogKey)
        {
            $logger->info("Posts : {$postCounts[$blogKey]} (in '{$blogKey}')");
        }

        return 0;
    }
}

