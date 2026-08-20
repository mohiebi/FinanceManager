<template>
    <Head
        :title="navigationName('navigation.goals', 'navigation.goals_subtitle')"
    />

    <div
        class="flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <!-- ── New-goal trigger — the mock's own screen has no header of
             its own (the shell already owns the page title), but goals
             have no other page to manage them from, so this stays. ──── -->
        <div class="mx-[18px] mt-5 flex justify-end">
            <Button
                class="h-11 w-max shrink-0 rounded-full bg-[linear-gradient(90deg,#02CD86_0%,#00a36e_100%)] px-5 text-[#101010] shadow-[0_10px_20px_rgba(2,205,134,0.22)] hover:brightness-105"
                @click="openGoalDialog(null)"
            >
                <Plus class="size-4" />
                {{ t('gamification.goals.new') }}
            </Button>
        </div>

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
            <!-- ── Goals ──────────────────────────────────────────── -->
            <section class="mx-[18px] my-[18px]">
                <div
                    v-if="allGoals.length > 0"
                    class="grid gap-[18px] md:grid-cols-2"
                >
                    <GoalCard
                        v-for="goal in allGoals"
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
                    {{ t('gamification.goals.empty') }}
                </p>
            </section>
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
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import GoalCard from '@/components/gamification/GoalCard.vue';
import GoalDialog from '@/components/gamification/GoalDialog.vue';
import { Button } from '@/components/ui/button';
import { useNavigationNaming } from '@/composables/useNavigationNaming';
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
