<?php

use App\Support\Encryption\UserCrypto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts the remaining plaintext financial fields under each user's own key.
 *
 * `amount` and `title` change type as well as content: a decimal column cannot
 * hold ciphertext, and a 255-character title base64s to well over varchar(255).
 * Both become text, following the column-swap pattern established by
 * 2026_06_30_113946_encrypt_investment_sensitive_fields.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->text('amount_encrypted')->nullable()->after('amount');
            $table->text('title_encrypted')->nullable()->after('title');
        });

        DB::table('transactions')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $dek = $this->dekFor((int) $row->user_id);

                DB::table('transactions')->where('id', $row->id)->update([
                    'amount_encrypted' => UserCrypto::encrypt(
                        number_format((float) $row->amount, 2, '.', ''),
                        $dek,
                        UserCrypto::aadFor('transactions', 'amount'),
                    ),
                    'title_encrypted' => UserCrypto::encrypt(
                        (string) $row->title,
                        $dek,
                        UserCrypto::aadFor('transactions', 'title'),
                    ),
                    // Already text, so it is encrypted in place. Re-running must not
                    // double-encrypt it.
                    'description' => $row->description === null || UserCrypto::looksEncrypted($row->description)
                        ? $row->description
                        : UserCrypto::encrypt(
                            (string) $row->description,
                            $dek,
                            UserCrypto::aadFor('transactions', 'description'),
                        ),
                ]);
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['amount', 'title']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('amount_encrypted', 'amount');
            $table->renameColumn('title_encrypted', 'title');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->text('title_encrypted')->nullable()->after('title');
        });

        DB::table('bills')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('bills')->where('id', $row->id)->update([
                    'title_encrypted' => UserCrypto::encrypt(
                        (string) $row->title,
                        $this->dekFor((int) $row->user_id),
                        UserCrypto::aadFor('bills', 'title'),
                    ),
                ]);
            }
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->renameColumn('title_encrypted', 'title');
        });

        $this->scrubNotificationBodies();
    }

    /**
     * Strip bill titles and amounts out of already-delivered notifications.
     *
     * `notifications.data` is not encrypted, so historical rows hold in cleartext
     * exactly what this migration just encrypted. Encrypting the source while
     * leaving these behind would be theatre.
     */
    private function scrubNotificationBodies(): void
    {
        DB::table('notifications')
            ->whereIn('type', ['App\\Notifications\\BillDueNotification'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $data = json_decode((string) $row->data, true);

                    if (! is_array($data) || ! isset($data['type'])) {
                        continue;
                    }

                    $locale = DB::table('users')->where('id', $row->notifiable_id)->value('locale') ?? 'en';

                    $data['body'] = trans("notifications.{$data['type']}.body_generic", [
                        'date' => $data['due_date'] ?? '',
                    ], $locale);

                    DB::table('notifications')->where('id', $row->id)->update([
                        'data' => json_encode($data),
                    ]);
                }
            }, 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount_plain', 15, 2)->nullable()->after('amount');
            $table->string('title_plain')->nullable()->after('title');
        });

        DB::table('transactions')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $dek = $this->dekFor((int) $row->user_id);

                DB::table('transactions')->where('id', $row->id)->update([
                    'amount_plain' => UserCrypto::decrypt($row->amount, $dek, UserCrypto::aadFor('transactions', 'amount')),
                    'title_plain' => UserCrypto::decrypt($row->title, $dek, UserCrypto::aadFor('transactions', 'title')),
                    'description' => $row->description === null
                        ? null
                        : UserCrypto::decrypt($row->description, $dek, UserCrypto::aadFor('transactions', 'description')),
                ]);
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['amount', 'title']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('amount_plain', 'amount');
            $table->renameColumn('title_plain', 'title');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->string('title_plain')->nullable()->after('title');
        });

        DB::table('bills')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('bills')->where('id', $row->id)->update([
                    'title_plain' => UserCrypto::decrypt(
                        $row->title,
                        $this->dekFor((int) $row->user_id),
                        UserCrypto::aadFor('bills', 'title'),
                    ),
                ]);
            }
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->renameColumn('title_plain', 'title');
        });
    }

    /**
     * Raw data keys, memoized for the run.
     *
     * @var array<int, string>
     */
    private array $keys = [];

    private function dekFor(int $userId): string
    {
        if (! array_key_exists($userId, $this->keys)) {
            $wrapped = DB::table('user_encryption_keys')
                ->where('user_id', $userId)
                ->value('wrapped_dek_server');

            if ($wrapped === null) {
                // Unreachable today — the vault does not exist yet, so every user
                // still has a server-held key. Loud rather than silently skipping,
                // because a skipped row would be left in plaintext.
                throw new RuntimeException("User [{$userId}] has no server-held data key; cannot migrate their rows.");
            }

            $this->keys[$userId] = base64_decode(Crypt::decryptString($wrapped), true);
        }

        return $this->keys[$userId];
    }
};
