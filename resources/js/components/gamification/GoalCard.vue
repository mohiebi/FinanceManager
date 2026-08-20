<script setup lang="ts">
import { PartyPopper, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
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

const percentLabel = computed(() =>
    props.goal.progress === null
        ? '—'
        : `${Math.round(props.goal.progress * 100)}%`,
);

/**
 * Reached wins over pace.
 *
 * Someone at 103% is not "on track" — they are done, and the pace reading is no
 * longer the interesting fact about their goal.
 */
const statusLabel = computed(() => {
    if (props.goal.reached) {
        return t('gamification.goals.reached');
    }

    return props.goal.on_track
        ? t('gamification.goals.on_track')
        : t('gamification.goals.behind_pace');
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
        class="relative overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
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

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <AssetIcon
                    :icon="props.goal.asset.icon"
                    :icon-svg="props.goal.asset.icon_svg"
                    :color="props.goal.asset.color"
                    :label="props.goal.asset.label"
                    class="size-9 shrink-0"
                />
                <div>
                    <p class="text-sm font-medium text-white">
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
                    <p class="text-xs text-[#989898]">
                        {{ props.goal.target_date_display }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1.5">
                <span
                    class="flex items-center gap-1 rounded-full px-2.5 py-1 text-xs"
                    :class="
                        props.goal.reached
                            ? 'goal-reached-badge bg-[#02CD86] font-medium text-[#08130f]'
                            : props.goal.on_track
                              ? 'bg-[#0d2e22] text-[#02CD86]'
                              : 'bg-white/5 text-[#989898]'
                    "
                >
                    <PartyPopper v-if="props.goal.reached" class="size-3.5" />
                    {{ statusLabel }}
                </span>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-[#989898] transition-colors hover:bg-white/10 hover:text-white"
                    :aria-label="t('gamification.goals.edit')"
                    @click="emit('edit', props.goal)"
                >
                    <Pencil class="size-4" />
                </button>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-[#989898] transition-colors hover:bg-[#E94E50]/10 hover:text-[#E94E50]"
                    :aria-label="t('gamification.goals.delete')"
                    @click="emit('delete', props.goal)"
                >
                    <Trash2 class="size-4" />
                </button>
            </div>
        </div>

        <div class="mt-4 flex items-baseline gap-2">
            <span class="text-[32px] leading-none font-bold tabular-nums">
                {{ quantity(props.goal.current_quantity) }}
            </span>
            <span class="text-sm text-[#989898]">
                {{
                    t('gamification.goals.of_target', {
                        target: quantity(props.goal.target_quantity),
                        unit: props.goal.asset.unit,
                    })
                }}
            </span>
        </div>

        <div
            class="mt-3 h-2 overflow-hidden rounded-full bg-white/5 ring-1 ring-white/10 ring-inset"
        >
            <div
                class="h-full rounded-full bg-[#02CD86]"
                :style="{ width: barWidth }"
            />
        </div>
        <div
            class="mt-1.5 flex justify-between text-[11px] text-[#989898] tabular-nums"
        >
            <span>0</span>
            <span>{{ percentLabel }}</span>
            <span>
                {{ quantity(props.goal.target_quantity) }}
                {{ props.goal.asset.unit }}
            </span>
        </div>

        <!-- Never "you will miss this": backdating a purchase raises the baseline
             and can worsen reported pace while the user is genuinely saving more. -->
        <p
            class="mt-3 text-xs"
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
    </article>
</template>
