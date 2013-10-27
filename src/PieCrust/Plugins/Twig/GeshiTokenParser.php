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
            "use_classes" => false,
            "class" => false,
            "id" => false
        );
        $needsValue = array("class", "id");

        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $params["language"] = $stream->expect(Twig_Token::STRING_TYPE)->getValue();
        while ($stream->test(Twig_Token::BLOCK_END_TYPE) == false)
        {
            $argName = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
            if (!isset($params[$argName]))
                throw new Twig_Error_Syntax("'{$argName}' is not a valid argument for the GeSHi tag.", $lineno);
            if (in_array($argName, $needsValue))
            {
                $operator = $stream->expect(Twig_Token::OPERATOR_TYPE)->getValue();
                if ($operator != '=')
                    throw new Twig_Error_Syntax("'{$argName}' must be followed by an equal sign and a value.", $lineno);
                $argValue = $stream->expect(Twig_Token::STRING_TYPE)->getValue();
                $params[$argName] = $argValue;
            }
            else
            {
                $params[$argName] = true;
            }
        }
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new GeshiNode($params, $body, $lineno, $this->getTag());
    }

    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endgeshi');
    }
}
