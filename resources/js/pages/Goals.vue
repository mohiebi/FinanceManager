<template>
    <Head
        :title="navigationName('navigation.goals', 'navigation.goals_subtitle')"
    />

    <div
        class="flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <!-- ── Header ─────────────────────────────────────────────── -->
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0">
                    <p
                        class="text-xs font-semibold tracking-[0.35em] text-[#02CD86] uppercase"
                    >
                        {{ t('gamification.goals.title') }}
                    </p>
                    <h1
                        class="mt-1 text-2xl font-semibold tracking-tight text-white"
                    >
                        {{
                            navigationName(
                                'navigation.goals',
                                'navigation.goals_subtitle',
                            )
                        }}
                    </h1>
                    <p class="mt-1 max-w-lg text-sm text-[#989898]">
                        {{ t('gamification.goals.page_description') }}
                    </p>
                </div>

                <Button
                    class="h-11 w-max shrink-0 rounded-full bg-[linear-gradient(90deg,#02CD86_0%,#00a36e_100%)] px-5 text-[#101010] shadow-[0_10px_20px_rgba(2,205,134,0.22)] hover:brightness-105"
                    @click="openGoalDialog(null)"
                >
                    <Plus class="size-4" />
                    {{ t('gamification.goals.new') }}
                </Button>
            </div>

            <!-- Search over the goal's own name and the asset it is kept in.
                 Both are what a user would type looking for one. -->
            <div class="relative mt-4 max-w-md">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#6f6f6f]"
                />
                <input
                    v-model="search"
                    type="search"
                    class="h-10 w-full rounded-xl border border-white/10 bg-[#252525] ps-9 pe-3 text-sm text-white placeholder:text-[#6f6f6f] focus:border-[#02CD86] focus:outline-none"
                    :placeholder="t('gamification.goals.search_placeholder')"
                />
            </div>
        </section>

        <!-- ── Loading ────────────────────────────────────────────── -->
        <div
            v-if="loading"
            class="mx-[18px] my-[18px] grid gap-[18px] md:grid-cols-2"
            aria-busy="true"
        >
            <div
                v-for="index in 2"
                :key="index"
                class="h-[210px] animate-pulse rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10"
            />
        </div>

        <template v-else>
            <!-- ── Active ─────────────────────────────────────────── -->
            <section class="mx-[18px] mt-[18px]">
                <h2
                    class="mb-3 text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                >
                    {{ t('gamification.goals.section_active') }}
                    <span class="text-[#6f6f6f]">({{ active.length }})</span>
                </h2>

                <div
                    v-if="active.length > 0"
                    class="grid gap-[18px] md:grid-cols-2"
                >
                    <GoalCard
                        v-for="goal in active"
                        :key="goal.id"
                        :goal="goal"
                        @edit="openGoalDialog"
                        @delete="deleteTargetGoal = $event"
                    />
                </div>

                <p
                    v-else
                    class="rounded-[22px] bg-[#1a1a1a] px-6 py-8 text-center text-sm text-[#989898] ring-1 ring-white/10"
                >
                    {{
                        hasSearch
                            ? t('gamification.goals.no_matches')
                            : t('gamification.goals.empty')
                    }}
                </p>
            </section>

            <!-- ── Achieved ───────────────────────────────────────── -->
            <section v-if="achieved.length > 0" class="mx-[18px] my-[18px]">
                <h2
                    class="mb-3 text-xs font-medium tracking-[0.2em] text-[#02CD86] uppercase"
                >
                    {{ t('gamification.goals.section_achieved') }}
                    <span class="text-[#02CD86]/60"
                        >({{ achieved.length }})</span
                    >
                </h2>

                <div class="grid gap-[18px] md:grid-cols-2">
                    <GoalCard
                        v-for="goal in achieved"
                        :key="goal.id"
                        :goal="goal"
                        @edit="openGoalDialog"
                        @delete="deleteTargetGoal = $event"
                    />
                </div>
            </section>

            <div v-else class="mb-[18px]" />
        </template>

        <GoalDialog
            v-model:open="goalDialogOpen"
            :asset-options="props.assetOptions ?? []"
            :goal="editingGoal"
        />

        <ConfirmDeleteModal
            :open="deleteTargetGoal !== null"
            :title="t('gamification.goals.delete_title')"
            :description="t('gamification.goals.delete_description')"
            @update:open="deleteTargetGoal = null"
            @confirm="confirmDeleteGoal"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import GoalCard from '@/components/gamification/GoalCard.vue';
import GoalDialog from '@/components/gamification/GoalDialog.vue';
import { Button } from '@/components/ui/button';
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import { useVault } from '@/composables/useVault';
import { useVaultGoals } from '@/composables/useVaultGoals';
import type { VaultGoalsPayload } from '@/composables/useVaultGoals';
import { dashboard, goals as goalsRoute } from '@/routes';
import { destroy as destroyGoal } from '@/routes/savings-goals';
import type {
    AssetOption,
    GoalCard as GoalCardData,
} from '@/types/gamification';

const props = defineProps<{
    /** Null under the vault, where `vaultGoals` carries them still sealed. */
    goals: GoalCardData[] | null;
    vaultGoals: VaultGoalsPayload | null;
    assetOptions: AssetOption[];
}>();

const { t } = useI18n();
const { navigationName } = useNavigationNaming();
const { revealAsync, trackKey } = useVault();

// No holdings to pass: this page has no portfolio breakdown of its own, and the
// composable treats an absent list as "nothing decrypted yet" rather than zero.
const { goals: vaultGoalCards } = useVaultGoals(
    () => props.vaultGoals,
    () => null,
);

const allGoals = computed<GoalCardData[]>(
    () => vaultGoalCards.value ?? props.goals ?? [],
);

/**
 * Null means "not ready" rather than "none": under the vault the cards stay
 * sealed until the key arrives, and an empty list would read as "no goals".
 */
const loading = computed(
    () => props.goals === null && vaultGoalCards.value === null,
);

const search = ref('');
const hasSearch = computed(() => search.value.trim() !== '');

/**
 * Titles resolved for searching.
 *
 * The cards render their titles through <Ciphered>, which never hands the
 * plaintext back — so matching a search term against one means opening it here.
 */
const titles = ref<Map<number, string>>(new Map());

watchEffect(async () => {
    // Tracked before any await, so unlocking makes search work in place.
    trackKey();

    const resolved = new Map<number, string>();

    for (const goal of allGoals.value) {
        const title = await revealAsync<string>(goal.title, 'savings_goals');

        resolved.set(goal.id, title ?? '');
    }

    titles.value = resolved;
});

const matching = computed<GoalCardData[]>(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return allGoals.value;
    }

    return allGoals.value.filter((goal) => {
        const title = (titles.value.get(goal.id) ?? '').toLowerCase();

        return (
            title.includes(term) ||
            goal.asset.label.toLowerCase().includes(term)
        );
    });
});

// Reached goals move to their own section rather than being hidden: a met goal
// is a record worth keeping, and it still needs editing and deleting.
const active = computed(() => matching.value.filter((goal) => !goal.reached));
const achieved = computed(() => matching.value.filter((goal) => goal.reached));

const goalDialogOpen = ref(false);
const editingGoal = ref<GoalCardData | null>(null);
const deleteTargetGoal = ref<GoalCardData | null>(null);

/** Null opens the dialog for a new goal; a card opens it prefilled for editing. */
function openGoalDialog(goal: GoalCardData | null): void {
    editingGoal.value = goal;
    goalDialogOpen.value = true;
}

// The reload after a save hands back fresh goal objects, so the one held here
// is stale the moment it is written. Dropped on close rather than kept, or
// re-opening the editor would prefill from the values that were just replaced.
watch(goalDialogOpen, (open) => {
    if (!open) {
        editingGoal.value = null;
    }
});

function confirmDeleteGoal(): void {
    const goal = deleteTargetGoal.value;

    if (goal === null) {
        return;
    }

    router.delete(destroyGoal.url(goal.id), {
        preserveScroll: true,
        // Same reason as the dialog's: the controller redirects `back()`, so the
        // goal props have to be asked for by name to actually come back.
        onSuccess: () => router.reload({ only: ['goals', 'vaultGoals'] }),
        onFinish: () => {
            deleteTargetGoal.value = null;
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Goals', href: goalsRoute() },
        ],
    },
});
</script>
