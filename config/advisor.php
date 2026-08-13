<?php

return [
    'provider' => env('ADVISOR_AI_PROVIDER', 'openai'),
    'model' => env('ADVISOR_AI_MODEL'),
    'timeout' => (int) env('ADVISOR_AI_TIMEOUT', 60),
    'prompt_version' => 1,
    'max_clarification_questions' => 3,
    'max_clarification_rounds' => 1,
    'max_repair_attempts' => 1,
    'max_provider_calls' => 3,
];
