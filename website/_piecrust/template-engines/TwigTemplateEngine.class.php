<?php

class TwigTemplateEngine implements ITemplateEngine
{   
    public function initialize($config)
    {
        require_once(PIECRUST_APP_DIR . 'libs/twig/lib/Twig/Autoloader.php');
        Twig_Autoloader::register();
    }
    
    public function renderPage($pieCrustApp, $pageConfig, $pageData)
    {
        $layoutName = 'default';
        if (array_key_exists('layout', $pageConfig))
            $layoutName = $pageConfig['layout'];
        
        $loader = new Twig_Loader_Filesystem($pieCrustApp->getTemplatesDir());
        $twig = new Twig_Environment($loader,
                                     array(
                                        'cache' => false //$pieCrustApp->getCompiledTemplatesDir()
                                    ));
        $twig->addFunction('pcurl', new Twig_Function_Function('twig_pcurl_function'));
        
        $template = $twig->loadTemplate($layoutName . '.html');
        echo $template->render($pageData);
    }
}

function twig_pcurl_function($value)
{
    return PIECRUST_URL_BASE . '/?/' . $value;
}
