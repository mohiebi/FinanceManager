<?php

namespace App\Services\Advisor;

use App\Contracts\AdvisorKnowledgeProvider;

class ModelOnlyAdvisorKnowledgeProvider implements AdvisorKnowledgeProvider
{
    /** @param array<string, mixed> $context
     * @return array{mode: string, version: int, sources: array<int, mixed>}
     */
    public function contextFor(array $context): array
    {
        return ['mode' => 'model_only', 'version' => 1, 'sources' => []];
    }
}
