<?php

namespace App\Support;

use InvalidArgumentException;

class SafeFormulaEvaluator
{
    /** @var list<string> */
    private array $tokens = [];

    private int $position = 0;

    /** @var array<string, float> */
    private array $variables = [];

    /**
     * @param  array<string, float|int>  $variables
     */
    public function evaluate(string $formula, array $variables): float
    {
        $this->tokens = $this->tokenize($formula);
        $this->position = 0;
        $this->variables = array_map(fn (float|int $value): float => (float) $value, $variables);

        if ($this->tokens === []) {
            throw new InvalidArgumentException('Formula is empty.');
        }

        $value = $this->parseExpression();

        if ($this->peek() !== null) {
            throw new InvalidArgumentException('Unexpected token in formula.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $formula): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($formula);

        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $formula, $matches, 0, $offset) === 1) {
                $offset += strlen($matches[0]);

                continue;
            }

            if (preg_match('/\G(?:\d+(?:\.\d+)?|\.\d+|[A-Za-z_][A-Za-z0-9_]*|[+\-*\/()])/A', $formula, $matches, 0, $offset) !== 1) {
                throw new InvalidArgumentException('Formula contains an unsupported token.');
            }

            $tokens[] = $matches[0];
            $offset += strlen($matches[0]);
        }

        return $tokens;
    }

    private function parseExpression(): float
    {
        $value = $this->parseTerm();

        while (($token = $this->peek()) === '+' || $token === '-') {
            $this->next();
            $right = $this->parseTerm();
            $value = $token === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    private function parseTerm(): float
    {
        $value = $this->parseFactor();

        while (($token = $this->peek()) === '*' || $token === '/') {
            $this->next();
            $right = $this->parseFactor();

            if ($token === '/' && abs($right) < PHP_FLOAT_EPSILON) {
                throw new InvalidArgumentException('Formula divides by zero.');
            }

            $value = $token === '*' ? $value * $right : $value / $right;
        }

        return $value;
    }

    private function parseFactor(): float
    {
        $token = $this->next();

        if ($token === null) {
            throw new InvalidArgumentException('Formula ended unexpectedly.');
        }

        if ($token === '-') {
            return -$this->parseFactor();
        }

        if ($token === '+') {
            return $this->parseFactor();
        }

        if ($token === '(') {
            $value = $this->parseExpression();

            if ($this->next() !== ')') {
                throw new InvalidArgumentException('Formula has an unclosed parenthesis.');
            }

            return $value;
        }

        if (is_numeric($token)) {
            return (float) $token;
        }

        if (! array_key_exists($token, $this->variables)) {
            throw new InvalidArgumentException("Formula references unknown variable [{$token}].");
        }

        return $this->variables[$token];
    }

    private function peek(): ?string
    {
        return $this->tokens[$this->position] ?? null;
    }

    private function next(): ?string
    {
        $token = $this->peek();

        if ($token !== null) {
            $this->position++;
        }

        return $token;
    }
}
