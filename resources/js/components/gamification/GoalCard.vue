<script setup lang="ts">
import { Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Ciphered from '@/components/Ciphered.vue';
import type { GoalCard } from '@/types/gamification';

const props = defineProps<{
    goal: GoalCard;
}>();

const emit = defineEmits<{
    edit: [goal: GoalCard];
    delete: [goal: GoalCard];
}>();

const { t, n } = useI18n();

/** Clamped for the bar only — the figures above it still show the real overshoot. */
const barWidth = computed(() => {
    const progress = props.goal.progress ?? 0;

    return `${Math.min(100, Math.max(0, progress * 100))}%`;
});

/** Nothing saved yet is its own state, distinct from merely behind pace — the
 *  mock's own three-tag system (on track / needs a push / not started), and
 *  the one case with a real next action ("make the first contribution"). */
const isEmpty = computed(
    () => props.goal.current_quantity === 0 && !props.goal.reached,
);

/**
 * Reached wins over pace, and an untouched goal reads as "not started"
 * rather than "needs a push" — there is nothing to push on yet.
 */
const statusLabel = computed(() => {
    if (props.goal.reached) {
        return t('gamification.goals.reached');
    }

    if (isEmpty.value) {
        return t('gamification.goals.not_started');
    }

    return props.goal.on_track
        ? t('gamification.goals.on_track')
        : t('gamification.goals.behind_pace');
});

const statusClass = computed(() => {
    if (props.goal.reached) {
        return 'goal-reached-badge bg-[#02CD86] font-medium text-[#08130f]';
    }

    if (isEmpty.value) {
        return 'bg-white/5 text-[#989898]';
    }

    return props.goal.on_track
        ? 'bg-[#0d2e22] text-[#02CD86]'
        : 'bg-[#2e2410] text-[#F59E0B]';
});

/**
 * Fixed rather than random, so a re-render never reshuffles the burst — and so
 * two reached goals on one page do not animate in lockstep.
 */
const confetti = computed(() =>
    [
        { left: 8, delay: 0, hue: '#02CD86', drift: -18 },
        { left: 22, delay: 120, hue: '#6C4EE9', drift: 12 },
        { left: 37, delay: 40, hue: '#FFC857', drift: -8 },
        { left: 52, delay: 200, hue: '#02CD86', drift: 20 },
        { left: 66, delay: 90, hue: '#E94E50', drift: -14 },
        { left: 79, delay: 260, hue: '#6C4EE9', drift: 10 },
        { left: 91, delay: 160, hue: '#FFC857', drift: -20 },
    ].map((piece, index) => ({
        id: index,
        style: {
            left: `${piece.left}%`,
            animationDelay: `${piece.delay}ms`,
            backgroundColor: piece.hue,
            '--goal-confetti-drift': `${piece.drift}px`,
        },
    })),
);

function quantity(value: number): string {
    // Up to 8 decimals so a crypto goal is legible, but no trailing zeros on a
    // goal measured in whole grams.
    return n(value, { maximumFractionDigits: 8 });
}
</script>

<template>
    <article
        class="relative flex flex-col overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white ring-1 ring-white/10"
        :class="props.goal.reached ? 'goal-reached ring-[#02CD86]/40' : ''"
    >
        <!-- Confetti. Purely decorative, so it is hidden from assistive tech —
             the badge already says the goal is reached. -->
        <div
            v-if="props.goal.reached"
            class="goal-confetti pointer-events-none absolute inset-0 overflow-hidden"
            aria-hidden="true"
        >
            <span
                v-for="piece in confetti"
                :key="piece.id"
                class="goal-confetti-piece"
                :style="piece.style"
            />
        </div>

        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="mb-1 text-[15px] font-medium text-white">
                    <!-- Encrypted under the vault, so it cannot go straight
                         into the template — that renders [object Object]. -->
                    <Ciphered
                        v-if="props.goal.title"
                        :value="props.goal.title"
                        table="savings_goals"
                        :fallback="props.goal.asset.label"
                    />
                    <template v-else>{{ props.goal.asset.label }}</template>
                </p>
                <p class="text-xs text-[#686868]">
                    {{
                        t('gamification.goals.card_target_date', {
                            date: props.goal.target_date_display,
                        })
                    }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                <span
                    class="flex items-center gap-1 rounded-full px-2.5 py-1 text-xs whitespace-nowrap"
                    :class="statusClass"
                >
                    {{ statusLabel }}
                </span>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg p-1.5 text-[#989898] transition-colors hover:bg-white/10 hover:text-white"
                    :aria-label="t('gamification.goals.edit')"
                    @click="emit('edit', props.goal)"
                >
                    <Pencil class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg p-1.5 text-[#989898] transition-colors hover:bg-[#E94E50]/10 hover:text-[#E94E50]"
                    :aria-label="t('gamification.goals.delete')"
                    @click="emit('delete', props.goal)"
                >
                    <Trash2 class="size-3.5" />
                </button>
            </div>
        </div>

        <div class="mb-3 h-2 overflow-hidden rounded-full bg-[#252525]">
            <div
                class="h-full rounded-full bg-[#02CD86]"
                :style="{ width: barWidth }"
            />
        </div>

        <div class="mb-3.5 flex items-baseline justify-between gap-3">
            <span class="text-[21px] leading-none font-bold tabular-nums">
                {{ quantity(props.goal.current_quantity) }}
            </span>
            <span class="text-[12.5px] text-[#686868] tabular-nums">
                {{
                    t('gamification.goals.of_target', {
                        target: quantity(props.goal.target_quantity),
                        unit: props.goal.asset.unit,
                    })
                }}
            </span>
        </div>

        <!-- Never "you will miss this": backdating a purchase raises the baseline
             and can worsen reported pace while the user is genuinely saving more. -->
        <p
            class="mt-auto border-t border-white/[0.06] pt-3.5 text-[12.5px] leading-[1.5]"
            :class="props.goal.reached ? 'text-[#02CD86]' : 'text-[#989898]'"
        >
            <!-- "0 a day to get there" is nonsense once there is nowhere left to
                 get to, so a reached goal says so instead. -->
            <template v-if="props.goal.reached">
                {{
                    t('gamification.goals.reached_body', {
                        amount: quantity(props.goal.target_quantity),
                        unit: props.goal.asset.unit,
                    })
                }}
            </template>
            <template v-else>
                {{
                    props.goal.days_remaining > 0
                        ? t('gamification.goals.remaining', {
                              days: props.goal.days_remaining,
                              amount: quantity(props.goal.required_per_day),
                              unit: props.goal.asset.unit,
                          })
                        : t('gamification.goals.window_closed')
                }}
            </template>
        </p>

        <button
            v-if="isEmpty"
            type="button"
            class="mt-3 w-fit cursor-pointer rounded-[10px] bg-[#02CD86] px-3.5 py-2 text-[13px] font-medium text-[#101010] transition hover:brightness-105"
            @click="emit('edit', props.goal)"
        >
            {{ t('gamification.goals.make_first_contribution') }}
        </button>
    </article>
</template>
