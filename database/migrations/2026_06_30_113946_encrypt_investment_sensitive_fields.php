<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->text('quantity_encrypted')->nullable()->after('quantity');
            $table->text('cost_basis_encrypted')->nullable()->after('cost_basis');
        });

        DB::table('investments')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('investments')->where('id', $row->id)->update([
                    'quantity_encrypted' => Crypt::encryptString((string) $row->quantity),
                    'cost_basis_encrypted' => $row->cost_basis !== null
                        ? Crypt::encryptString((string) $row->cost_basis)
                        : null,
                    'note' => $row->note !== null
                        ? Crypt::encryptString((string) $row->note)
                        : null,
                ]);
            }
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'cost_basis']);
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->renameColumn('quantity_encrypted', 'quantity');
            $table->renameColumn('cost_basis_encrypted', 'cost_basis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->decimal('quantity_plain', 16, 8)->nullable()->after('quantity');
            $table->decimal('cost_basis_plain', 16, 4)->nullable()->after('cost_basis');
        });

        DB::table('investments')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('investments')->where('id', $row->id)->update([
                    'quantity_plain' => Crypt::decryptString($row->quantity),
                    'cost_basis_plain' => $row->cost_basis !== null
                        ? Crypt::decryptString($row->cost_basis)
                        : null,
                    'note' => $row->note !== null
                        ? Crypt::decryptString($row->note)
                        : null,
                ]);
            }
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'cost_basis']);
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->renameColumn('quantity_plain', 'quantity');
            $table->renameColumn('cost_basis_plain', 'cost_basis');
        });
    }
};
