<?php

return [
    'provider' => env('ADVISOR_AI_PROVIDER', 'openai'),
    'model' => env('ADVISOR_AI_MODEL'),
    'timeout' => (int) env('ADVISOR_AI_TIMEOUT', 300),
    'execution_time_buffer' => (int) env('ADVISOR_AI_EXECUTION_TIME_BUFFER', 15),
    'prompt_version' => 2,
    'max_clarification_questions' => 3,
    'max_clarification_rounds' => 1,
    'max_repair_attempts' => 1,
    'max_provider_calls' => 3,
];
