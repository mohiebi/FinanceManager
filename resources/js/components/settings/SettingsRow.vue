<script setup lang="ts">
/**
 * One labelled control, laid out in two columns on a wide screen.
 *
 * This is what makes a settings page use its width honestly. Capping the input
 * instead left a narrow control marooned in a very wide card — the empty space
 * simply moved inside the panel. Here the label and its help text take the left
 * third and the control takes the rest, so the row spans the card with nothing
 * stretched and nothing abandoned.
 *
 * Stacks below `md`, where two columns would leave neither one usable.
 */
const props = withDefaults(
    defineProps<{
        label?: string;
        /** Explains the field. Sits under the label, never as a placeholder. */
        help?: string;
        /** Id of the control this labels, so clicking the label focuses it. */
        controlId?: string;
        /** Drops the divider — use on the last row of a section. */
        last?: boolean;
    }>(),
    {
        label: undefined,
        help: undefined,
        controlId: undefined,
        last: false,
    },
);

const hasLabelColumn = (): boolean =>
    props.label !== undefined || props.help !== undefined;
</script>

<template>
    <div
        class="grid gap-2 py-4 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] md:gap-6"
        :class="last ? '' : 'border-b border-white/5'"
    >
        <div v-if="hasLabelColumn() || $slots.label" class="min-w-0">
            <label
                v-if="label && controlId"
                :for="controlId"
                class="cursor-pointer text-sm font-medium text-white"
            >
                {{ label }}
            </label>
            <p v-else-if="label" class="text-sm font-medium text-white">
                {{ label }}
            </p>
            <slot name="label" />
            <p v-if="help" class="mt-1 max-w-[52ch] text-xs text-[#989898]">
                {{ help }}
            </p>
        </div>

        <!-- Spans both columns when the row has no label, so a full-width
             control (a table, a list) is not stranded in the right column. -->
        <div
            class="min-w-0"
            :class="hasLabelColumn() || $slots.label ? '' : 'md:col-span-2'"
        >
            <slot />
        </div>
    </div>
</template>
