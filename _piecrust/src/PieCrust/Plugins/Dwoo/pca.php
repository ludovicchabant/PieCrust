<?php

// No namespace because Dwoo doesn't support them with
// the directory loading system.
use PieCrust\TemplateEngines\DwooTemplateEngine;


class Dwoo_Plugin_pca extends Dwoo_Block_Plugin implements Dwoo_ICompilable_Block
{
    public function init($href, array $rest=array())
    {
    }

    public static function preProcessing(Dwoo_Compiler $compiler, array $params, $prepend, $append, $type)
    {
        $p = $compiler->getCompiledParams($params);
        if (isset($p['href']))
        {
            $p['href'] = '\'' . DwooTemplateEngine::formatUri(trim($p['href'], '/\'')) . '\'';
        }

        $out = Dwoo_Compiler::PHP_OPEN . 'echo \'<a '.self::paramsToAttributes($p);

        return $out.'>\';' . Dwoo_Compiler::PHP_CLOSE;
    }

    public static function postProcessing(Dwoo_Compiler $compiler, array $params, $prepend, $append, $content)
    {
        $p = $compiler->getCompiledParams($params);

        // no content was provided so use the url as display text
        if ($content == "") {
            // merge </a> into the href if href is a string
            if (substr($p['href'], -1) === '"' || substr($p['href'], -1) === '\'') {
                return Dwoo_Compiler::PHP_OPEN . 'echo '.substr($p['href'], 0, -1).'</a>'.substr($p['href'], -1).';'.Dwoo_Compiler::PHP_CLOSE;
            }
            // otherwise append
            return Dwoo_Compiler::PHP_OPEN . 'echo '.$p['href'].'.\'</a>\';'.Dwoo_Compiler::PHP_CLOSE;
        }

        // return content
        return $content . '</a>';
    }
}
