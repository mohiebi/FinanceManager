<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class AdvisorConsultationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are CashPilot Advisor. Explain the frozen, validated recommendation using only the supplied profile and recommendation. Never invent live market facts, prices, news, contracts, metrics, or guaranteed returns. If the user asks to change allocations, set requires_recommendation_revision to true and explain that CashPilot must run the structured, validated recommendation workflow; do not put revised percentages in ordinary chat text.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'answer' => $schema->string()->required(),
            'requires_recommendation_revision' => $schema->boolean()->required(),
            'suggested_question' => $schema->string()->nullable()->required(),
        ];
    }
}
