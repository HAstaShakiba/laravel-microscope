<?php

namespace Imanghafoori\LaravelMicroscope\Analyzers;

class ClassMethods
{
    public static function read($tokens)
    {
        $i = 0;
        $class = [
            'name' => null,
            'methods' => [],
            'type' => '',
        ];
        $methods = [];

        while (isset($tokens[$i])) {
            $token = $tokens[$i];

            if ($token[0] == T_CLASS && $tokens[$i - 1][0] !== T_DOUBLE_COLON) {
                $class['name'] = $tokens[$i + 2];
                $class['type'] = T_CLASS;
                $class['is_abstract'] = ($tokens[$i - 2][0] === T_ABSTRACT);
            } elseif ($token[0] == T_INTERFACE) {
                $class['name'] = $tokens[$i + 2];
                $class['type'] = T_INTERFACE;
            } elseif ($token[0] == T_TRAIT) {
                $class['name'] = $tokens[$i + 2];
                $class['type'] = T_TRAIT;
            }

            if ($class['name'] === null || $tokens[$i][0] != T_FUNCTION) {
                $i++;
                continue;
            }

            if (! \is_array($name = $tokens[$i + 2])) {
                $i++;
                continue;
            }

            [$visibility, $isStatic, $isAbstract] = self::findVisibility($tokens, $i - 2);
            [, $signature, $endSignature] = Ifs::readCondition($tokens, $i + 2);
            [$char, $charIndex] = FunctionCall::forwardTo($tokens, $endSignature, [':', ';', '{']);

            [$returnType, $hasNullableReturnType, $char, $charIndex] = self::processReturnType($char, $tokens, $charIndex);

            if ($char == '{') {
                [$body, $i] = FunctionCall::readBody($tokens, $charIndex);
            } elseif ($char == ';') {
                $body = [];
            }

            $i++;
            $methods[] = [
                'name' => $name,
                'visibility' => $visibility,
                'signature' => $signature,
                'body' => Refactor::toString($body),
                'startBodyIndex' => [$charIndex, $i],
                'returnType' => $returnType,
                'nullable_return_type' => $hasNullableReturnType,
                'is_static' => $isStatic,
                'is_abstract' => $isAbstract,
            ];
        }

        $class['methods'] = $methods;

        return $class;
    }

    private static function findVisibility($tokens, $i)
    {
        $isStatic = ($tokens[$i][0] == T_STATIC);
        $isStatic && ($i = $i - 2);

        $isAbstract = ($tokens[$i][0] == T_ABSTRACT);
        $isAbstract && ($i = $i - 2);

        $hasModifier = \in_array($tokens[$i][0], [T_PUBLIC, T_PROTECTED, T_PRIVATE]);
        $visibility = $hasModifier ? $tokens[$i] : [T_PUBLIC, 'public'];

        // We have to cover both syntax:
        //     public abstract function x() {
        //     abstract public function x() {
        if (! $isAbstract) {
            $isAbstract = ($tokens[$i - 2][0] == T_ABSTRACT);
        }

        return [$visibility, $isStatic, $isAbstract];
    }

    private static function processReturnType($char, $tokens, $charIndex)
    {
        if ($char != ':') {
            return [null, null, $char, $charIndex];
        }

        [$returnType, $returnTypeIndex] = FunctionCall::getNextToken($tokens, $charIndex);

        // In case the return type is like this: function c() : ?string {...
        $hasNullableReturnType = ($returnType == '?');

        if ($hasNullableReturnType) {
            [$returnType, $returnTypeIndex] = FunctionCall::getNextToken($tokens, $returnTypeIndex);
        }

        [$char, $charIndex] = FunctionCall::getNextToken($tokens, $returnTypeIndex);

        return [$returnType, $hasNullableReturnType, $char, $charIndex];
    }
}
