<?php

namespace PieCrust\Plugins\Twig;

use \Twig_Token;
use \Twig_Error_Syntax;


class GeshiTokenParser extends \Twig_TokenParser
{
    public function getTag()
    {
        return 'geshi';
    }
    
    public function parse(Twig_Token $token)
    {
        $params = array(
            "language" => false,
            "line_numbers" => false,
            "use_classes" => false
        );
        $lineno = $token->getLine();
        $params["language"] = $this->parser->getStream()->expect(Twig_Token::STRING_TYPE)->getValue();
        while ($this->parser->getStream()->test(Twig_Token::BLOCK_END_TYPE) == false)
        {
            $argName = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue();
            if (!isset($params[$argName]))
            {
                throw new Twig_Error_Syntax('"' . $argName . '" is not a valid argument for the GeSHi tag.', $lineno);
            }
            $params[$argName] = true;
        }
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

        return new GeshiNode($params, $body, $lineno, $this->getTag());
    }

    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endgeshi');
    }
}
