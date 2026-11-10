<?php

namespace App\GraphQL\Scalars;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;

/**
 * Scalar JSON que devuelve arrays/objetos reales (sin convertirlos a string).
 */
class JsonObject extends ScalarType
{
    public string $name = 'JSON';

    public ?string $description = 'Estructura JSON arbitraria (objeto, lista o valor).';

    public function serialize($value)
    {
        return $value;
    }

    public function parseValue($value)
    {
        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        return AST::valueFromASTUntyped($valueNode, $variables);
    }
}