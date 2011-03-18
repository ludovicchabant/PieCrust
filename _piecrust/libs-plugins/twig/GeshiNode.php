<?php


class GeshiNode extends Twig_Node
{
    public function __construct($language, $body, $lineno, $tag)
    {
        parent::__construct(array('body' => $body), array('language' => $language), $lineno, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write("ob_start();\n")
            ->subcompile($this->getNode('body'))
            ->write('$source_' . $this->getLine() . " = ob_get_clean();\n")
            ->write('geshi_highlight($source_' . $this->getLine() . ', \'' . $this->getAttribute('language') . '\')')
            ->raw(";\n")
        ;
    }
}
