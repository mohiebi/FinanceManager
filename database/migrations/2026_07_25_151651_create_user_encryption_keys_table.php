<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user envelope keys.
 *
 * Every user owns a random 256-bit data key (DEK) that their financial columns are
 * encrypted under. The DEK itself is stored wrapped, never in the clear:
 *
 *   normal mode  DEK wrapped by APP_KEY                      -> server can read
 *   vault mode   DEK wrapped by a passphrase-derived KEK
 *                and by a recovery key, server copy destroyed -> server cannot read
 *
 * Because only the *wrapping* changes, switching a user between the two modes
 * rewrites one row and never re-encrypts their data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_encryption_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Bumped only by a future DEK rotation, never by a re-wrap.
            $table->unsignedInteger('version')->default(1);

            // The DEK wrapped by APP_KEY. NULL exactly when the vault is armed —
            // this column being NULL *is* the zero-knowledge state, so it must
            // stay nullable.
            $table->text('wrapped_dek_server')->nullable();

            // Opaque blobs produced by the browser; the server never unwraps these.
            $table->text('wrapped_dek_passphrase')->nullable();
            $table->text('wrapped_dek_recovery')->nullable();

            // KDF parameters kept as data, so moving off PBKDF2 later is a per-user
            // re-wrap and a value change rather than a schema migration.
            $table->string('kdf', 32)->default('pbkdf2-sha256');
            $table->unsignedInteger('kdf_iterations')->default(600000);
            $table->string('kdf_salt', 64)->nullable();
            $table->string('recovery_salt', 64)->nullable();

            // SHA-256 of the raw DEK. Safe to store: the preimage is 256 uniformly
            // random bits, so this is not brute-forceable. Lets the client prove a
            // correct unwrap, and lets the server verify a DEK handed back to it.
            $table->char('dek_fingerprint', 64);

            $table->timestamp('vault_enabled_at')->nullable();
            $table->timestamp('recovery_key_issued_at')->nullable();
            $table->timestamp('recovery_key_acknowledged_at')->nullable();

            $table->timestamps();
        });

        $this->backfillExistingUsers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_encryption_keys');
    }

    /**
     * Give every existing user a key. New users get one from
     * App\Observers\UserEncryptionKeyObserver.
     */
    private function backfillExistingUsers(): void
    {
        $now = now();

        DB::table('users')->select('id')->orderBy('id')->chunkById(200, function ($users) use ($now): void {
            $rows = [];

            foreach ($users as $user) {
                $dek = random_bytes(32);

                $rows[] = [
                    'user_id' => $user->id,
                    'version' => 1,
                    'wrapped_dek_server' => Crypt::encryptString(base64_encode($dek)),
                    'dek_fingerprint' => hash('sha256', $dek),
                    'kdf' => 'pbkdf2-sha256',
                    'kdf_iterations' => 600000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (function_exists('sodium_memzero')) {
                    sodium_memzero($dek);
                }
            }

            if ($rows !== []) {
                DB::table('user_encryption_keys')->insert($rows);
            }
        });
    }
};
