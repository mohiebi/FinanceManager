<?php

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Contracts\HasEncryptionOwner;
use App\Exceptions\DecryptionFailed;
use App\Exceptions\EncryptionOwnerUnresolved;
use App\Exceptions\VaultLocked;
use App\Models\User;
use App\Support\Encryption\EncryptedValue;
use App\Support\Encryption\UserCrypto;
use App\Support\Encryption\UserKeyRing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Exercises the cast against a real table before any production model is cast,
 * so Stage 0 stays a genuine no-op for application behaviour.
 */
class EncryptionProbe extends Model implements HasEncryptionOwner
{
    use OwnsEncryptedAttributes;

    protected $table = 'transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['description' => UserEncrypted::class];
    }
}

/**
 * @return array<string, mixed>
 */
function probeAttributes(User $user): array
{
    return [
        'user_id' => $user->id,
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'Probe',
        'occurred_at' => '2026-07-25',
    ];
}

/**
 * Pest declares test-file functions globally, so this is named for its probe to
 * leave `armVault` free for the real vault tests later.
 */
function armProbeVault(User $user): void
{
    $user->encryptionKey()->update(['wrapped_dek_server' => null]);
    app(UserKeyRing::class)->forget($user->id);
}

test('every new user is given a data key', function () {
    $user = User::factory()->create();

    expect($user->encryptionKey()->exists())->toBeTrue()
        ->and($user->encryptionKey->wrapped_dek_server)->not->toBeNull()
        ->and($user->encryptionKey->dek_fingerprint)->toHaveLength(64);
});

test('the key fingerprint matches the key the ring hands out', function () {
    $user = User::factory()->create();

    $dek = app(UserKeyRing::class)->for($user->id);

    expect(hash('sha256', $dek))->toBe($user->encryptionKey->dek_fingerprint);
});

test('ensuring the key is idempotent', function () {
    $user = User::factory()->create();
    $original = $user->encryptionKey->dek_fingerprint;

    $user->ensureEncryptionKey();

    expect($user->encryptionKey()->count())->toBe(1)
        ->and($user->fresh()->encryptionKey->dek_fingerprint)->toBe($original);
});

test('many rows for one user cost a single key lookup', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    foreach (range(1, 5) as $i) {
        EncryptionProbe::create([...probeAttributes($user), 'description' => "note {$i}"]);
    }

    app(UserKeyRing::class)->flush();

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'user_encryption_keys')) {
            $queries++;
        }
    });

    EncryptionProbe::query()->get()->each(fn (EncryptionProbe $probe) => $probe->description);

    expect($queries)->toBe(1);
});

test('one user cannot read another user rows', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);
    $probe = EncryptionProbe::create([...probeAttributes($alice), 'description' => 'alice secret']);

    $stored = DB::table('transactions')->where('id', $probe->id)->value('description');
    $bobKey = app(UserKeyRing::class)->for($bob->id);

    expect(fn () => UserCrypto::decrypt($stored, $bobKey, UserCrypto::aadFor('transactions', 'description')))
        ->toThrow(DecryptionFailed::class);
});

test('the stored value is ciphertext, not the plaintext', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $probe = EncryptionProbe::create([...probeAttributes($user), 'description' => 'rent for july']);

    $stored = DB::table('transactions')->where('id', $probe->id)->value('description');

    expect($stored)->not->toContain('rent for july')
        ->and(UserCrypto::looksEncrypted($stored))->toBeTrue()
        ->and($probe->fresh()->description)->toBe('rent for july');
});

test('a relation create encrypts under the owner even though the foreign key lands late', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // HasMany::create() fills attributes — running the cast — before it sets
    // user_id, so this is the path that would silently use the wrong key.
    $probe = EncryptionProbe::create([
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'Probe',
        'occurred_at' => '2026-07-25',
        'description' => 'resolved via auth',
        'user_id' => $user->id,
    ]);

    expect($probe->fresh()->description)->toBe('resolved via auth');
});

test('saving a row with no owner is refused', function () {
    $probe = new EncryptionProbe([
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'Probe',
        'occurred_at' => '2026-07-25',
        'description' => 'orphan',
    ]);

    // Assigning is fine — encryption is deferred to save, which is the first
    // moment the owner is knowable.
    expect(fn () => $probe->save())->toThrow(EncryptionOwnerUnresolved::class);
});

test('a row is keyed to its final owner, not to whoever was authenticated', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Alice is the acting user, but the row ends up owned by Bob. Encrypting at
    // save time means it is keyed to Bob — the only reading that is recoverable.
    $this->actingAs($alice);

    $probe = new EncryptionProbe(probeAttributes($alice));
    $probe->description = 'belongs to bob';
    $probe->user_id = $bob->id;
    $probe->save();

    $stored = DB::table('transactions')->where('id', $probe->id)->value('description');
    $bobKey = app(UserKeyRing::class)->for($bob->id);

    expect(UserCrypto::decrypt($stored, $bobKey, UserCrypto::aadFor('transactions', 'description')))
        ->toBe('belongs to bob');
});

test('reading back an unsaved attribute returns what was assigned', function () {
    $user = User::factory()->create();

    $probe = new EncryptionProbe(probeAttributes($user));
    $probe->description = 'not saved yet';

    expect($probe->description)->toBe('not saved yet');
});

test('an armed vault yields ciphertext on read instead of throwing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $probe = EncryptionProbe::create([...probeAttributes($user), 'description' => 'private note']);

    armProbeVault($user);

    $value = $probe->fresh()->description;

    expect($value)->toBeInstanceOf(EncryptedValue::class)
        ->and($value->field())->toBe('description')
        ->and((string) $value)->not->toContain('private note')
        ->and(json_decode(json_encode($value), true))
        ->toMatchArray(['__enc' => 1, 'f' => 'description']);
});

test('an armed vault refuses writes rather than storing plaintext', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    armProbeVault($user);

    expect(fn () => EncryptionProbe::create([...probeAttributes($user), 'description' => 'nope']))
        ->toThrow(VaultLocked::class);
});

test('a client-encrypted value is stored verbatim', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $dek = app(UserKeyRing::class)->for($user->id);
    $blob = UserCrypto::encrypt('wrapped by the browser', $dek, UserCrypto::aadFor('transactions', 'description'));

    armProbeVault($user);

    $probe = EncryptionProbe::create([
        ...probeAttributes($user),
        'description' => new EncryptedValue($blob, 'description'),
    ]);

    expect(DB::table('transactions')->where('id', $probe->id)->value('description'))->toBe($blob);
});

test('the key ring never serializes', function () {
    expect(fn () => serialize(app(UserKeyRing::class)))->toThrow(LogicException::class);
});

test('the key ring redacts keys from dumps', function () {
    $user = User::factory()->create();
    $ring = app(UserKeyRing::class);
    $ring->for($user->id);

    expect(print_r($ring, true))->toContain('[redacted]')
        ->and(print_r($ring, true))->not->toContain($user->encryptionKey->dek_fingerprint);
});

test('preloading warms many users in one query', function () {
    $users = User::factory()->count(3)->create();
    $ring = app(UserKeyRing::class);

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'user_encryption_keys')) {
            $queries++;
        }
    });

    $ring->preload($users->pluck('id')->all());
    $users->each(fn (User $user) => $ring->for($user->id));

    expect($queries)->toBe(1);
});
