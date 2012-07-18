<?php

namespace PieCrust\Plugins\Twig;

use \Twig_Token;


class PieCrustFormatterTokenParser extends \Twig_TokenParser
{
    public function getTag()
    {
        return 'pcformat';
    }
    
    public function parse(Twig_Token $token)
    {
        $params = array(
            "format" => null
        );
        $lineno = $token->getLine();
        if ($this->parser->getStream()->test(Twig_Token::BLOCK_END_TYPE) == false)
        {
            $params['format'] = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue();
        }
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

        return new PieCrustFormatterNode($params, $body, $lineno, $this->getTag());
    }

    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endpcformat');
    }
}
