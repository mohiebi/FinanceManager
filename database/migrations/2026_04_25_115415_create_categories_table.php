<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'type', 'slug'], 'categories_user_type_slug_unique');
            $table->index(['type', 'is_default']);
        });

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql', 'sqlsrv'], true)) {
            DB::statement('CREATE UNIQUE INDEX categories_default_type_slug_unique ON categories (type, slug) WHERE user_id IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
