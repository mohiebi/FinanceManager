<script setup lang="ts">
import { ref, watchEffect } from 'vue';
import { useVault } from '@/composables/useVault';
import type { EncryptedFieldType } from '@/lib/vault/codec';
import type { Encrypted } from '@/types/vault';

/**
 * Renders a value that may or may not be encrypted.
 *
 * Plaintext passes straight through with no skeleton and no async tick, which is
 * what lets this be adopted across the app while the vault is still switched off —
 * the passthrough path is exercised in production long before anyone arms one.
 */
const props = withDefaults(
    defineProps<{
        value: Encrypted<string> | null | undefined;
        /** Table the value came from — needed to rebuild the AAD. */
        table: string;
        type?: EncryptedFieldType;
        /** Shown when the value is null, or when the vault is locked. */
        fallback?: string;
    }>(),
    { type: 'string', fallback: '' },
);

const { reveal, revealAsync } = useVault();

const resolved = ref<string | undefined>(reveal<string>(props.value));

watchEffect(async () => {
    const immediate = reveal<string>(props.value);

    if (immediate !== undefined) {
        resolved.value = immediate;

        return;
    }

    resolved.value = undefined;
    resolved.value = await revealAsync<string>(
        props.value,
        props.table,
        props.type,
    );
});
</script>

<template>
    <span v-if="resolved !== undefined">{{ resolved }}</span>
    <span
        v-else-if="props.value === null || props.value === undefined"
        class="text-[#6f6f6f]"
        >{{ props.fallback }}</span
    >
    <span
        v-else
        aria-hidden="true"
        class="inline-block h-[1em] w-16 animate-pulse rounded bg-white/10 align-middle"
    />
</template>
