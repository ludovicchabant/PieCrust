<?php

namespace PieCrust\Formatters;

use PieCrust\IPieCrust;


class MarkdownFormatter implements IFormatter
{
    protected $pieCrust;
    protected $libDir;
    protected $parser;
    protected $markdown_config;
    
    public function initialize(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->parser = null;
        $this->libDir = 'markdown';
        if ($pieCrust->getConfig()->getValue('markdown/use_markdown_extra') === true)
        {
            $this->libDir = 'markdown-extra';
        }
        if ($markdown_config = $pieCrust->getConfig()->getValue('markdown/config'))
        {
            $this->markdown_config = $markdown_config;
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
        if ($this->parser == null)
        {
            require_once ('markdown/' . $this->libDir . '/markdown.php');
            $parserClass = MARKDOWN_PARSER_CLASS;
            $this->parser = new $parserClass;
        }

        $this->parser->fn_id_prefix = '';
        if (isset($this->markdown_config))
        {
            foreach ($this->markdown_config as $param=>$value)
            {
                $this->parser->$param = $value;
            }
        }
        $executionContext = $this->pieCrust->getEnvironment()->getExecutionContext();
        if ($executionContext != null)
        {
            $page = $executionContext->getPage();
            if ($page && !$executionContext->isMainPage())
            {
                $footNoteId = $page->getUri();
                $this->parser->fn_id_prefix = $footNoteId . "-";
            }
        }
        return $this->parser->transform($text);
    }
}
