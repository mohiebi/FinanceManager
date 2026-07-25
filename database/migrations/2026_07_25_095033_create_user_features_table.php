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
        Schema::create('user_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // An App\Enums\Feature value. Rows are sparse OVERRIDES: a missing row means
            // "use Feature::enabledByDefault()", so shipping a new module never requires
            // backfilling every user, and both signup paths (Fortify and Google) work
            // without a hook that grants default rows.
            $table->string('feature', 40);

            $table->boolean('enabled')->default(false);

            // Only meaningful while enabled = false, where it renders the module greyed
            // out with a "+" in the nav. An enabled module is always visible, so the
            // "enabled but hidden" state is deliberately unrepresentable.
            $table->boolean('show_promo')->default(true);

            $table->timestamps();

            // Also serves as the user_id lookup index via the leftmost prefix.
            $table->unique(['user_id', 'feature']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_features');
    }
};
