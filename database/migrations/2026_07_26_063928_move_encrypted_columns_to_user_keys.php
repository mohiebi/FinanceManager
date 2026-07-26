<?php

use App\Support\Encryption\UserCrypto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Moves the columns already encrypted under APP_KEY onto each user's own key.
 *
 * Content-only — every column here is already `text`, so there is no schema
 * change. The point is to leave exactly one encryption scheme in the codebase:
 * without this, half a user's financial data would follow their key into the
 * vault while the other half stayed readable by the server.
 */
return new class extends Migration
{
    /**
     * Columns to move, as table => [column, ...]. Every one of these tables has
     * a user_id.
     *
     * @var array<string, array<int, string>>
     */
    private const COLUMNS = [
        'bills' => ['amount'],
        'investments' => ['quantity', 'cost_basis', 'note'],
        'mcp_proposals' => ['payload', 'diff_summary'],
    ];

    /**
     * Raw data keys, memoized for the run.
     *
     * @var array<int, string>
     */
    private array $keys = [];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rewrap(
            // Already in the new format — skip, so a re-run is a no-op.
            skip: fn (string $value): bool => UserCrypto::looksEncrypted($value),
            convert: fn (string $value, string $dek, string $aad): string => UserCrypto::encrypt(
                Crypt::decryptString($value),
                $dek,
                $aad,
            ),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rewrap(
            skip: fn (string $value): bool => ! UserCrypto::looksEncrypted($value),
            convert: fn (string $value, string $dek, string $aad): string => Crypt::encryptString(
                UserCrypto::decrypt($value, $dek, $aad),
            ),
        );
    }

    private function rewrap(callable $skip, callable $convert): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns, $skip, $convert): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column};

                        if ($value === null || $value === '' || $skip($value)) {
                            continue;
                        }

                        $updates[$column] = $convert(
                            $value,
                            $this->dekFor((int) $row->user_id),
                            UserCrypto::aadFor($table, $column),
                        );
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }

    private function dekFor(int $userId): string
    {
        if (! array_key_exists($userId, $this->keys)) {
            $wrapped = DB::table('user_encryption_keys')
                ->where('user_id', $userId)
                ->value('wrapped_dek_server');

            if ($wrapped === null) {
                throw new RuntimeException("User [{$userId}] has no server-held data key; cannot migrate their rows.");
            }

            $this->keys[$userId] = base64_decode(Crypt::decryptString($wrapped), true);
        }

        return $this->keys[$userId];
    }
};
