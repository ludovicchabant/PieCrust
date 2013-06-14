<?php

namespace PieCrust\Plugins\Twig;

use \Twig_Token;


class PieCrustCacheTokenParser extends \Twig_TokenParser
{
    public function getTag()
    {
        return 'pccache';
    }
    
    public function parse(Twig_Token $token)
    {
        $params = array(
            "id" => null
        );
        $lineno = $token->getLine();
        if ($this->parser->getStream()->test(Twig_Token::BLOCK_END_TYPE) == false)
        {
            $params['id'] = $this->parser->getStream()->expect(Twig_Token::STRING_TYPE)->getValue();
        }
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

        return new PieCrustCacheNode($params, $body, $lineno, $this->getTag());
    }

    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endpccache');
    }
}
