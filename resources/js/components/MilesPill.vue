<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { CircleGauge, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { claim } from '@/routes/miles';
import type { MilesClaimed } from '@/types/miles';

defineProps<{ compact?: boolean }>();

const page = usePage();
const { t } = useI18n();
const processing = ref(false);
const miles = computed(() => page.props.miles);

function collect(): void {
    if (!miles.value?.claimable || processing.value) {
        return;
    }

    // Captured before the request: these are the figures the button was
    // showing when it was pressed, and the props are replaced by the time the
    // response lands.
    const step = (miles.value.claimStep % 7) + 1;
    const reward = miles.value.nextClaimReward;

    processing.value = true;
    router.post(
        claim.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['miles', 'overview'],
            onSuccess: () => {
                window.dispatchEvent(
                    new CustomEvent<MilesClaimed>('miles:claimed', {
                        detail: {
                            miles: reward,
                            step,
                            balance: page.props.miles?.balance ?? 0,
                            nextReward: page.props.miles?.nextClaimReward ?? 0,
                        },
                    }),
                );
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        v-if="miles"
        class="flex min-h-11 shrink-0 items-stretch overflow-hidden rounded-xl border border-[#02cd86]/25 bg-[#02cd86]/8 text-xs shadow-[0_0_24px_rgba(2,205,134,0.06)]"
    >
        <button
            v-if="miles.claimable"
            type="button"
            class="flex min-w-11 cursor-pointer items-center gap-1.5 border-e border-[#02cd86]/20 px-3 font-semibold text-[#5eeeb5] transition-colors duration-200 hover:bg-[#02cd86]/12 focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none focus-visible:ring-inset disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none"
            :disabled="processing"
            :aria-label="
                t('miles.pill.claim_aria', { miles: miles.nextClaimReward })
            "
            @click="collect"
        >
            <Sparkles class="size-3.5" aria-hidden="true" />
            <span v-if="!compact">{{ t('miles.pill.claim') }}</span>
            <span>+{{ miles.nextClaimReward }}</span>
        </button>
        <Link
            :href="miles.hubUrl"
            class="flex min-w-11 cursor-pointer items-center gap-1.5 px-3 font-semibold text-white transition-colors duration-200 hover:bg-white/5 focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none focus-visible:ring-inset motion-reduce:transition-none"
            :aria-label="t('miles.pill.hub_aria')"
        >
            <CircleGauge class="size-3.5 text-[#02cd86]" aria-hidden="true" />
            <span>{{ miles.balance }}</span>
            <span v-if="!compact" class="font-medium text-[#989898]">{{
                t('miles.unit')
            }}</span>
        </Link>
    </div>
</template>
