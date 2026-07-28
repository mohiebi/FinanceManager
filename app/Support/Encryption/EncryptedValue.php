<?php

namespace App\Support\Encryption;

use JsonSerializable;
use Stringable;

/**
 * Stands in for a value the server cannot read because the owner's vault is armed.
 *
 * It serialises to `{"__enc":1,"c":"...","f":"amount"}`, which is what lets the
 * existing API resources ship ciphertext to the browser without being rewritten —
 * `'amount' => $this->amount` produces this wrapper automatically.
 */
final readonly class EncryptedValue implements JsonSerializable, Stringable
{
    public function __construct(
        private string $ciphertext,
        private string $field,
    ) {}

    public function ciphertext(): string
    {
        return $this->ciphertext;
    }

    public function field(): string
    {
        return $this->field;
    }

    /**
     * @return array{__enc: int, c: string, f: string}
     */
    public function jsonSerialize(): array
    {
        return [
            '__enc' => 1,
            'c' => $this->ciphertext,
            'f' => $this->field,
        ];
    }

    /**
     * Deliberately not numeric-looking.
     *
     * Plenty of existing code does `(float) $transaction->amount`. If this returned
     * something castable, that code would quietly compute totals of zero. A visible
     * marker makes the omission fail loudly in a test instead.
     */
    public function __toString(): string
    {
        return '•••';
    }
}
