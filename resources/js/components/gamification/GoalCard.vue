<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
import type { GoalCard } from '@/types/gamification';

const props = defineProps<{
    goal: GoalCard;
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

function quantity(value: number): string {
    // Up to 8 decimals so a crypto goal is legible, but no trailing zeros on a
    // goal measured in whole grams.
    return n(value, { maximumFractionDigits: 8 });
}
</script>

<template>
    <article
        class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
    >
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
                        {{ props.goal.title || props.goal.asset.label }}
                    </p>
                    <p class="text-xs text-[#989898]">
                        {{ props.goal.target_date_display }}
                    </p>
                </div>
            </div>
            <span
                class="rounded-full px-2.5 py-1 text-xs"
                :class="
                    props.goal.on_track
                        ? 'bg-[#0d2e22] text-[#02CD86]'
                        : 'bg-white/5 text-[#989898]'
                "
            >
                {{
                    props.goal.on_track
                        ? t('gamification.goals.on_track')
                        : t('gamification.goals.behind_pace')
                }}
            </span>
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
        <p class="mt-3 text-xs text-[#989898]">
            {{
                props.goal.days_remaining > 0
                    ? t('gamification.goals.remaining', {
                          days: props.goal.days_remaining,
                          amount: quantity(props.goal.required_per_day),
                          unit: props.goal.asset.unit,
                      })
                    : t('gamification.goals.window_closed')
            }}
        </p>
    </article>
</template>
