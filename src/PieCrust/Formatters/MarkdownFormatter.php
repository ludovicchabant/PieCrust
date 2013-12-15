<?php

namespace PieCrust\Formatters;

use Michelf\Markdown;
use Michelf\MarkdownExtra;
use PieCrust\IPieCrust;


class MarkdownFormatter implements IFormatter
{
    protected $pieCrust;
    protected $useExtra;
    protected $useSundown;
    protected $parser;
    protected $parserConfig;

    public function initialize(IPieCrust $pieCrust)
    {
        $this->pieCrust = $pieCrust;
        $this->parser = null;
        $this->useExtra = $pieCrust->getConfig()->getValue('markdown/use_markdown_extra');
        $this->useSundown = $pieCrust->getConfig()->getValue('markdown/use_sundown');
        $this->parserConfig = $pieCrust->getConfig()->getValue('markdown/config');
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
        if (!$this->useSundown)
        {
            return $this->phpMarkdownFormat($text);
        }
        else
        {
            return $this->sundownFormat($text);
        }
    }

    private function phpMarkdownFormat($text)
    {
        if ($this->parser == null)
        {
            if ($this->useExtra)
                $this->parser = new MarkdownExtra();
            else
                $this->parser = new Markdown();

            if ($this->parserConfig)
            {
                foreach ($this->parserConfig as $param => $value)
                {
                    $this->parser->$param = $value;
                }
            }
        }

        $this->parser->fn_id_prefix = '';
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

    private function sundownFormat($text)
    {
        if ($this->parser == null)
        {
            $options = $this->parserConfig;
            if ($options == null)
                $options = array();

            $this->parser = new \Sundown\Markdown(
                \Sundown\Render\HTML,
                $options
            );
        }

        return $this->parser->render($text);
    }
}
