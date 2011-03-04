<?php

class MarkdownFormatter implements IFormatter
{
    protected $markdownLibDir;
    
    public function initialize(PieCrust $pieCrust)
    {
        $config = $pieCrust->getConfig();
        $this->markdownLibDir = 'markdown';
        if ($pieCrust->getConfigValue('markdown', 'use_markdown_extra') === true)
        {
            $this->markdownLibDir = 'markdown-extra';
        }
    }
    
    public function getPriority()
    {
        return IFormatter::PRIORITY_DEFAULT;
    }
    
    public function supportsFormat($format, $isUnformatted)
    {
        return $isUnformatted && preg_match('/markdown|mdown|mkdn?|md/i', $format);
    }
    
    public function format($text)
    {
        require_once(dirname(__FILE__) . '/../libs/' . $this->markdownLibDir . '/markdown.php');
        return Markdown($text);
    }
}
