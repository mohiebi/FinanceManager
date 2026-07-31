<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plane } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { store as storeNoSpendDay } from '@/routes/no-spend-days';
import type { Streak, StreakDayState } from '@/types/gamification';

const props = defineProps<{
    streak: Streak;
}>();

const emit = defineEmits<{
    (event: 'add-transaction'): void;
}>();

const { t } = useI18n();
const marking = ref(false);

/**
 * Colours carry the meaning here, so each state also gets a title attribute —
 * a chain of coloured squares is unreadable to anyone who cannot tell them apart.
 */
const dayClasses: Record<StreakDayState, string> = {
    logged: 'bg-[#02CD86]',
    no_spend: 'bg-[#02CD86]/20 ring-1 ring-inset ring-[#02CD86]',
    grace: 'bg-white/10 ring-1 ring-inset ring-white/25',
    missed: 'bg-white/5',
    open: 'bg-transparent ring-1 ring-inset ring-dashed ring-white/40',
};

const labelClasses: Record<StreakDayState, string> = {
    logged: 'text-[#0d2e22]',
    no_spend: 'text-[#02CD86]',
    grace: 'text-[#989898]',
    missed: 'text-[#5a5a5a]',
    open: 'text-white',
};

const runLabel = computed(() =>
    props.streak.current_run > 0
        ? t('gamification.run')
        : t('gamification.run_none'),
);

function markNoSpend(): void {
    marking.value = true;

    router.post(
        storeNoSpendDay.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                marking.value = false;
            },
        },
    );
}
</script>

<template>
    <article
        class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#0d2e22]"
                >
                    <Plane class="size-[18px] text-[#02CD86]" />
                </span>
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                >
                    {{ t('gamification.title') }}
                </p>
            </div>
            <span
                class="rounded-full px-2.5 py-1 text-xs"
                :class="
                    props.streak.grace_remaining > 0
                        ? 'bg-[#0d2e22] text-[#02CD86]'
                        : 'bg-white/5 text-[#989898]'
                "
            >
                {{
                    props.streak.grace_remaining > 0
                        ? t(
                              'gamification.grace_left',
                              { count: props.streak.grace_remaining },
                              props.streak.grace_remaining,
                          )
                        : t('gamification.grace_none')
                }}
            </span>
        </div>

        <div class="mt-4 flex items-baseline gap-3">
            <span class="text-[44px] leading-none font-bold tabular-nums">
                {{ props.streak.current_run }}
            </span>
            <div class="text-sm text-[#989898]">
                <p>{{ runLabel }}</p>
                <p v-if="props.streak.best_run > 0" class="text-xs">
                    {{
                        t('gamification.best_run', {
                            days: props.streak.best_run,
                        })
                    }}
                </p>
            </div>
        </div>

        <ul class="mt-4 flex gap-[5px]" :aria-label="t('gamification.title')">
            <li
                v-for="day in props.streak.days"
                :key="day.date"
                class="flex h-9 flex-1 items-end justify-center rounded-[3px] pb-1 text-[10px] tabular-nums"
                :class="[dayClasses[day.state], labelClasses[day.state]]"
                :title="`${day.date} — ${t(`gamification.states.${day.state}`)}`"
            >
                {{ day.label }}
            </li>
        </ul>

        <p class="mt-3 text-xs text-[#989898]">
            {{
                props.streak.today_would_set_record
                    ? t('gamification.record_within_reach')
                    : props.streak.logged_today
                      ? t('gamification.logged_today')
                      : t('gamification.open_today')
            }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2.5">
            <button
                type="button"
                class="rounded-xl bg-[#02CD86] px-4 py-2 text-sm font-semibold text-[#101010] transition hover:bg-[#08dd93]"
                @click="emit('add-transaction')"
            >
                {{ t('gamification.add_transaction') }}
            </button>
            <button
                v-if="!props.streak.logged_today"
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-medium text-white ring-1 ring-white/15 transition hover:bg-white/5 disabled:opacity-50"
                :disabled="marking"
                @click="markNoSpend"
            >
                {{ t('gamification.no_spend_action') }}
            </button>
        </div>
    </article>
</template>
