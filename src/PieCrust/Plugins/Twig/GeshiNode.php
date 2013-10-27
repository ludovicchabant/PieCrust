<?php

namespace PieCrust\Plugins\Twig;


class GeshiNode extends \Twig_Node
{
    public function __construct($params, $body, $lineno, $tag)
    {
        parent::__construct(array('body' => $body), $params, $lineno, $tag);
    }

    public function compile(\Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('ob_start();' . PHP_EOL)
            ->subcompile($this->getNode('body'))
            ->write('$source = rtrim(ob_get_clean());' . PHP_EOL)
            ->write('$geshi = new GeSHi($source, \'' . $this->getAttribute('language') . '\');' . PHP_EOL)
        ;
        if ($this->getAttribute('use_classes'))
        {
            $compiler->write('$geshi->enable_classes();' . PHP_EOL);
        }
        if ($this->getAttribute('line_numbers'))
        {
            $compiler->write('$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);' . PHP_EOL);
        }
        if ($this->getAttribute('class'))
        {
            $compiler->write('$geshi->set_overall_class(\'' . $this->getAttribute('class') . '\');' . PHP_EOL);
        }
        if ($this->getAttribute('id'))
        {
            $compiler->write('$geshi->set_overall_id(\'' . $this->getAttribute('id') . '\');' . PHP_EOL);
        }
        $compiler->write('echo $geshi->parse_code() . PHP_EOL;' . PHP_EOL);
    }
}
