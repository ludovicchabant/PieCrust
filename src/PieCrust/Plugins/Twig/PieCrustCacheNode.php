<?php

namespace PieCrust\Plugins\Twig;


class PieCrustCacheNode extends \Twig_Node
{
    public function __construct($params, $body, $lineno, $tag)
    {
        parent::__construct(array('body' => $body), $params, $lineno, $tag);
    }

    public function compile(\Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('$pieCrust = $context["PIECRUST_APP"];' . PHP_EOL)
            ->write('if ($pieCrust->isCachingEnabled() && $pieCrust->getConfig()->getValue("baker/is_baking")) {' . PHP_EOL)
            ->write('    $cacheId = "' . $this->getAttribute('id') . '";' . PHP_EOL)
            ->write('    $cacheHash = md5($cacheId);' . PHP_EOL)
            ->write('    $cachePath = $pieCrust->getCacheDir() . "bake_t/0/" . $cacheHash;' . PHP_EOL)
            ->write('    if (is_file($cachePath)) {' . PHP_EOL)
            ->write('        echo file_get_contents($cachePath);' . PHP_EOL)
            ->write('    } else {' . PHP_EOL)
            ->write('        ob_start();' . PHP_EOL)
            ->subcompile($this->getNode('body'))
            ->write('        $source = ob_get_clean();' . PHP_EOL)
            ->write('        file_put_contents($cachePath, $source);' . PHP_EOL)
            ->write('        echo $source;' . PHP_EOL)
            ->write('    }' . PHP_EOL)
            ->write('} else {' . PHP_EOL)
            ->subcompile($this->getNode('body'))
            ->write('}' . PHP_EOL)
        ;
    }
}
