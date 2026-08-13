<?php

namespace App\Contracts;

interface AdvisorKnowledgeProvider
{
    /** @param array<string, mixed> $context
     * @return array{mode: string, version: int, sources: array<int, mixed>}
     */
    public function contextFor(array $context): array;
}
