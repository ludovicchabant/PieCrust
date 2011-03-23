<?php

require_once 'libs/phamlp/haml/filters/_HamlMarkdownFilter.php';


class Markdown_Parser_Wrapper
{
    // PhamlP calls 'safeTransform' but this function doesn't seem to exit
    // anymore in the Markdown_Parser. Let's wrap it.
    public function safeTransform($text)
    {
        return Markdown($text);
    }
}

class HamlMarkdownFilter extends _HamlMarkdownFilter
{
    public function init()
    {
        $this->vendorPath = PIECRUST_APP_DIR . 'libs/markdown/markdown.php';
        $this->vendorClass = 'Markdown_Parser_Wrapper';
        parent::init();
    }
    
    public function run($text)
    {
        $text = str_replace('"', '\"', $text);  // Looks like the PHamlP parser is too dumb to escape quotes before inserting this in PHP code... *sigh*
        return parent::run($text);
    }
}
