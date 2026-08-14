<?php

test('the environment example documents the advisor provider configuration', function () {
    $environmentExample = file_get_contents(base_path('.env.example'));

    expect($environmentExample)->not->toBeFalse()
        ->and($environmentExample)->toContain('ADVISOR_AI_PROVIDER=openai')
        ->and($environmentExample)->toContain('ADVISOR_AI_MODEL=gpt-5-mini')
        ->and($environmentExample)->toContain('OPENAI_API_KEY=')
        ->and($environmentExample)->toContain('OPENAI_URL=https://api.openai.com/v1')
        ->and($environmentExample)->not->toContain('VITE_OPENAI_API_KEY');
});
