<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('the answer migration repairs a partial table using a MySQL-safe unique index name', function () {
    $index = 'investor_answers_assessment_question_unique';

    expect(strlen($index))->toBeLessThanOrEqual(64)
        ->and(Schema::hasIndex('investor_assessment_answers', $index))->toBeTrue();

    Schema::table('investor_assessment_answers', function (Blueprint $table) use ($index): void {
        $table->dropUnique($index);
    });

    expect(Schema::hasTable('investor_assessment_answers'))->toBeTrue()
        ->and(Schema::hasIndex('investor_assessment_answers', $index))->toBeFalse();

    $migration = require database_path('migrations/2026_08_13_153818_create_investor_assessment_answers_table.php');
    $migration->up();

    expect(Schema::hasIndex('investor_assessment_answers', $index))->toBeTrue();
});
