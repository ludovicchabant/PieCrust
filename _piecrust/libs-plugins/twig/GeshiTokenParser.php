<?php

require_once 'GeshiNode.php';


class GeshiTokenParser extends Twig_TokenParser
{
    public function getTag()
    {
        return 'geshi';
    }
    
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $language = $this->parser->getStream()->expect(Twig_Token::STRING_TYPE)->getValue();
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

        return new GeshiNode($language, $body, $lineno, $this->getTag());
    }

    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endgeshi');
    }
}
