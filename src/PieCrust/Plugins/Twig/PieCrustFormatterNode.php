<?php

namespace PieCrust\Plugins\Twig;


class PieCrustFormatterNode extends \Twig_Node
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
            ->write('$source = ob_get_clean();' . PHP_EOL)
            ->write('$format = \'' . $this->getAttribute('format') . '\';' . PHP_EOL)
            ->write('$pieCrust = $context[\'PIECRUST_APP\'];' . PHP_EOL)
            ->write('echo \PieCrust\Util\PieCrustHelper::formatText($pieCrust, $source, $format);' . PHP_EOL)
        ;
    }
}
