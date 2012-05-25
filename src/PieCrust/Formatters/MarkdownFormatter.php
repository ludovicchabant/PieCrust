<?php

namespace PieCrust\Formatters;

use PieCrust\IPieCrust;


class MarkdownFormatter implements IFormatter
{
    protected $markdownLibDir;
    
    public function initialize(IPieCrust $pieCrust)
    {
        $config = $pieCrust->getConfig();
        $this->markdownLibDir = 'markdown';
        if ($pieCrust->getConfig()->getValue('markdown/use_markdown_extra') === true)
        {
            $this->markdownLibDir = 'markdown-extra';
        }
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_DEFAULT;
    }

    public function isExclusive()
    {
        return true;
    }
    
    public function supportsFormat($format)
    {
        return preg_match('/markdown|mdown|mkdn?|md/i', $format);
    }
    
    public function format($text)
    {
        require_once ('markdown/' . $this->markdownLibDir . '/markdown.php');
        return Markdown($text);
    }
}
