<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Spinner } from '@/components/ui/spinner';

/**
 * The save control for a settings form.
 *
 * Four pages had each grown their own: two hand-rolled `<button>` elements
 * carrying the same three hundred characters of utility classes, one `Button`
 * tinted green by hand, and three copies of the same "Saved" transition. They
 * had already drifted — different padding, different disabled treatment — so
 * the answer to "did that save?" looked different depending on which page you
 * were on. One component, one answer.
 *
 * `sticky` is for forms taller than the viewport. There the button cannot live
 * at the bottom and be trusted: someone who changes the first field has no
 * reason to believe a control two screens down belongs to it. Sticky keeps the
 * bar — and the fact that something is unsaved — on screen the whole way.
 */
const props = withDefaults(
    defineProps<{
        processing: boolean;
        recentlySuccessful: boolean;
        /**
         * Whether the form differs from what is stored.
         *
         * Omit it when the form cannot tell (Inertia's `<Form>` component does
         * not track it) — the bar then behaves as it always did and stays put.
         */
        dirty?: boolean;
        /** Overrides the default "Save" wording. */
        label?: string;
        /** Rides the bottom of the viewport instead of sitting in the flow. */
        sticky?: boolean;
    }>(),
    {
        dirty: undefined,
        label: undefined,
        sticky: false,
    },
);

const emit = defineEmits<{ discard: [] }>();

const { t } = useI18n();

const tracksDirtyState = computed(() => props.dirty !== undefined);

// Nothing to save and nothing to report: a sticky bar with a dead button in it
// is just a strip of chrome covering the last row of the form.
const isHidden = computed(
    () =>
        props.sticky &&
        tracksDirtyState.value &&
        props.dirty === false &&
        !props.recentlySuccessful,
);

const isDisabled = computed(
    () => props.processing || (tracksDirtyState.value && props.dirty === false),
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="!isHidden"
            :class="
                sticky
                    ? 'sticky bottom-4 z-20 flex flex-wrap items-center gap-3 rounded-[18px] bg-[#1a1a1a]/95 px-4 py-3 shadow-[0_18px_45px_rgba(0,0,0,0.45)] ring-1 ring-white/10 backdrop-blur'
                    : 'flex flex-wrap items-center gap-3'
            "
        >
            <!-- Says which state the form is in before the button says what to
                 do about it, so "Save" is never the only thing on screen
                 hinting that something is outstanding. -->
            <p
                v-if="sticky && dirty"
                class="me-auto text-sm text-[#c8c8c8]"
                aria-live="polite"
            >
                {{ t('settings.save_bar.unsaved') }}
            </p>

            <button
                v-if="sticky && dirty"
                type="button"
                class="cursor-pointer rounded-xl px-4 py-2.5 text-sm font-medium text-[#989898] transition-colors duration-200 hover:bg-white/5 hover:text-white focus-visible:ring-2 focus-visible:ring-white/40 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none"
                @click="emit('discard')"
            >
                {{ t('settings.save_bar.discard') }}
            </button>

            <button
                type="submit"
                :disabled="isDisabled"
                data-test="settings-save-button"
                class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl bg-[#02CD86] px-5 text-sm font-medium text-[#101010] transition-[filter,opacity] duration-200 hover:brightness-110 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            >
                <Spinner v-if="processing" class="size-4" />
                {{ label ?? t('common.save') }}
            </button>

            <!-- Polite rather than assertive: confirmation should reach a screen
                 reader without cutting off whatever it is already saying. -->
            <Transition
                enter-active-class="transition ease-in-out motion-reduce:transition-none"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out motion-reduce:transition-none"
                leave-to-class="opacity-0"
            >
                <p
                    v-show="recentlySuccessful"
                    class="inline-flex items-center gap-1.5 text-sm text-[#02CD86]"
                    role="status"
                    aria-live="polite"
                >
                    <Check class="size-4" aria-hidden="true" />
                    {{ t('common.saved') }}
                </p>
            </Transition>

            <slot />
        </div>
    </Transition>
</template>
