<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('color', 7)->nullable()->after('slug');
            // Scoped per user+type, same as budget_lines' sort_order: the
            // client owns the ordering, the server just records it.
            $table->unsignedSmallInteger('sort_order')->default(0)->after('color');

            $table->index(['user_id', 'type', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'type', 'sort_order']);
            $table->dropColumn(['color', 'sort_order']);
        });
    }
};
