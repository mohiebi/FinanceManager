import assert from 'node:assert/strict';
import { test } from 'node:test';

import { effectScope, nextTick, ref, watchEffect } from 'vue';

/**
 * Why `useVault` exposes `trackKey()`.
 *
 * The unwrapped key is a plain module variable, so an effect that decrypts has no
 * reactive dependency on it. Everything that decrypts therefore reads a version
 * counter first — and it has to be read *before* the first `await`, because Vue
 * stops collecting dependencies at that point.
 *
 * Getting this wrong produced a bug with a very specific shape: the first page
 * after arming the vault never filled in, no matter how long you waited or how
 * many tabs you opened, but navigating anywhere else fixed it — because the
 * navigation remounted the components against a key that was by then present.
 */

/** Stands in for the module-level `keyEpoch` ref in useVault. */
function makeVault() {
    const keyEpoch = ref(0);
    let key: string | null = null;

    return {
        trackKey: () => void keyEpoch.value,
        unlock: (value: string) => {
            key = value;
            keyEpoch.value += 1;
        },
        reveal: async (blob: string) =>
            key === null ? undefined : `${blob}:${key}`,
    };
}

test('an effect that tracks the key re-runs when the vault is unlocked', async () => {
    const vault = makeVault();
    const scope = effectScope();
    const resolved = ref<string | undefined>(undefined);

    scope.run(() => {
        watchEffect(async () => {
            vault.trackKey();
            resolved.value = await vault.reveal('amount');
        });
    });

    await nextTick();
    await Promise.resolve();

    // Mounted before the key existed, so nothing is readable yet.
    assert.equal(resolved.value, undefined);

    vault.unlock('dek');
    await nextTick();
    await Promise.resolve();

    assert.equal(resolved.value, 'amount:dek');

    scope.stop();
});

test('an effect that reads the key only after awaiting never re-runs', async () => {
    const vault = makeVault();
    const scope = effectScope();
    const resolved = ref<string | undefined>(undefined);

    scope.run(() => {
        watchEffect(async () => {
            // The trap: Vue has stopped collecting dependencies by the time this
            // runs, so the effect is inert for the rest of its life.
            resolved.value = await vault.reveal('amount');
            vault.trackKey();
        });
    });

    await nextTick();
    await Promise.resolve();

    vault.unlock('dek');
    await nextTick();
    await Promise.resolve();

    assert.equal(
        resolved.value,
        undefined,
        'reading the tracker after an await must not be mistaken for tracking it',
    );

    scope.stop();
});

test('locking re-runs the effect too, so stale plaintext cannot linger', async () => {
    const vault = makeVault();
    const scope = effectScope();
    const resolved = ref<string | undefined>(undefined);

    vault.unlock('dek');

    scope.run(() => {
        watchEffect(async () => {
            vault.trackKey();
            resolved.value = await vault.reveal('amount');
        });
    });

    await nextTick();
    await Promise.resolve();
    assert.equal(resolved.value, 'amount:dek');

    // `lock()` bumps the same counter, which is what clears the screen on idle.
    vault.unlock('other');
    await nextTick();
    await Promise.resolve();

    assert.equal(resolved.value, 'amount:other');

    scope.stop();
});
