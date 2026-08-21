<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The three things the Advisor promises, in the two shapes the design uses
 * them: a numbered row list on the paywall, and a three-column band under a
 * gold rule on the home screen. Same copy either way — it is the feature's
 * one-paragraph explanation and must not drift between the two surfaces.
 */
const props = withDefaults(defineProps<{ variant?: 'rows' | 'columns' }>(), {
    variant: 'columns',
});

const { t } = useI18n();

const pillars = computed(() => [
    {
        title: t('advisor.cashpilot_role'),
        body: t('advisor.cashpilot_role_description'),
    },
    { title: t('advisor.ai_role'), body: t('advisor.ai_role_description') },
    {
        title: t('advisor.vault_safe'),
        body: t('advisor.vault_safe_description'),
    },
]);

function numeral(index: number): string {
    return String(index + 1).padStart(2, '0');
}

const isColumns = computed(() => props.variant === 'columns');
</script>

<template>
    <div v-if="isColumns">
        <p
            class="advisor-mono pb-3.5 text-[10px] tracking-[0.22em] text-[#686868] uppercase"
        >
            {{ t('advisor.how_it_works') }}
        </p>
        <div class="advisor-rule grid md:grid-cols-3">
            <div
                v-for="(pillar, index) in pillars"
                :key="pillar.title"
                class="border-t border-white/7 py-[22px] first:border-t-0 md:border-e md:border-t-0 md:border-white/7 md:pb-6 md:last:border-e-0"
                :class="[
                    index === 0 ? 'md:pe-6' : '',
                    index === 1 ? 'md:px-6' : '',
                    index === 2 ? 'md:ps-6' : '',
                ]"
            >
                <span
                    class="advisor-mono advisor-figure text-[10.5px] text-[#d9c48f]"
                    >{{ numeral(index) }}</span
                >
                <h3 class="advisor-serif mt-3 text-[21px] leading-[1.2]">
                    {{ pillar.title }}
                </h3>
                <p class="mt-2.5 text-[13.5px] leading-[1.7] text-[#686868]">
                    {{ pillar.body }}
                </p>
            </div>
        </div>
    </div>

    <div v-else class="flex flex-col">
        <div
            v-for="(pillar, index) in pillars"
            :key="pillar.title"
            class="grid grid-cols-[26px_minmax(0,1fr)] gap-4 border-t border-white/7 py-[18px] last:border-b last:border-white/7"
        >
            <span
                class="advisor-mono advisor-figure pt-[3px] text-[11px] text-[#d9c48f]"
                >{{ numeral(index) }}</span
            >
            <div>
                <p class="text-[14.5px] font-medium">{{ pillar.title }}</p>
                <p class="mt-[5px] text-[13.5px] leading-[1.65] text-[#686868]">
                    {{ pillar.body }}
                </p>
            </div>
        </div>
    </div>
</template>
