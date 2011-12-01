<?php

require_once 'PhamlP/haml/filters/_HamlMarkdownFilter.php';


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
        $this->vendorPath = null;
        $this->vendorClass = 'Markdown_Parser_Wrapper';
        parent::init();
    }
    
    public function run($text)
    {
        // We need to programmatically include Markdown because we may
        // have to use Markdown-Extra instead of basic Markdown.
        $defaultIncludePath = 'Markdown/markdown.php';
        $extraIncludePath = 'MarkdownExtra/markdown.php';
        
        // The global $_PIECRUST_APP should be set by the Haml template engine
        // and the Haml formatter.
        $includeCode = '<?php '.
                       'if (!isset($_PIECRUST_APP)) { $markdownIncludePath = "' . $defaultIncludePath . '"; }'.
                       'else {'.
                            '$markdownConfig = $_PIECRUST_APP->getConfig()->getValue("markdown");'.
                            'if ($markdownConfig != null and $markdownConfig["use_markdown_extra"]) { $markdownIncludePath = "' . $extraIncludePath . '"; }'.
                            'else { $markdownIncludePath = "' . $defaultIncludePath . '"; }'.
                       '}'.
                       'require_once $markdownIncludePath;'.
                       '?>';
        
        $text = str_replace('"', '\"', $text);  // Looks like the PHamlP parser is too dumb to escape quotes before inserting this in PHP code... *sigh*
        return $includeCode . parent::run($text);
    }
}
