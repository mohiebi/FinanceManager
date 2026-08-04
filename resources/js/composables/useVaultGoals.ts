import { router } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import type { ComputedRef } from 'vue';
import { useVault } from '@/composables/useVault';
import type { DecryptedEntry } from '@/composables/useVaultPortfolio';
import { computePace } from '@/lib/goals';
import { achieved as markGoalAchieved } from '@/routes/savings-goals';
import type { GoalCard, GoalPresentation } from '@/types/gamification';
import type { Encrypted } from '@/types/vault';

/**
 * What BuildGoalProgress::clientPayload sends when the vault is armed.
 *
 * Every date is already resolved — target dates are never encrypted, so there is
 * no reason to duplicate calendar maths in the browser. Only the target quantity
 * is sealed.
 */
export type VaultGoalsPayload = {
    goals: (GoalPresentation & {
        target_quantity: Encrypted<string | number>;
        elapsed: number;
        total: number;
        started_on: string;
    })[];
    /** Server-supplied: a Tehran user at 01:00 and a UTC server disagree by a day. */
    today: string;
};

export type UseVaultGoalsReturn = {
    goals: ComputedRef<GoalCard[] | null>;
    /**
     * True while the targets are still sealed.
     *
     * Distinct from `goals === null`, which is also what an account with no goals
     * produces — telling those apart is the difference between a skeleton that
     * resolves and one that never does.
     */
    decrypting: ComputedRef<boolean>;
};

/**
 * Decrypt the targets and compute the same progress the server computes when it
 * can read them.
 *
 * Holdings come from the breakdown the portfolio composable already decrypted,
 * so the quantities are summed once rather than twice.
 */
export function useVaultGoals(
    payload: () => VaultGoalsPayload | null | undefined,
    entries: () => DecryptedEntry[] | null,
): UseVaultGoalsReturn {
    const { revealAsync, trackKey } = useVault();

    const targets = ref<(number | undefined)[] | null>(null);

    watchEffect(async () => {
        // Tracked before any await, so unlocking fills the cards in place rather
        // than leaving them pulsing until the next navigation.
        trackKey();

        const current = payload();

        if (current === null || current === undefined) {
            targets.value = null;

            return;
        }

        const revealed = await Promise.all(
            current.goals.map(async (goal) => {
                const target = await revealAsync<string | number>(
                    goal.target_quantity,
                    'savings_goals',
                    'quantity',
                );

                return target === undefined ? undefined : Number(target) || 0;
            }),
        );

        // A locked vault yields nothing rather than a pile of zeroes — a goal
        // showing 0% is a far worse lie than a skeleton.
        targets.value = revealed.some((target) => target === undefined)
            ? null
            : revealed;
    });

    const goals = computed<GoalCard[] | null>(() => {
        const current = payload();

        if (
            current === null ||
            current === undefined ||
            targets.value === null
        ) {
            return null;
        }

        const decrypted = entries() ?? [];

        return current.goals.map((goal, index) => {
            const target = targets.value?.[index] ?? 0;
            const forAsset = decrypted.filter(
                (entry) => entry.investment_asset_id === goal.asset.id,
            );

            // A plain sum: disposals are stored with a negative quantity, so
            // holdings are already net of sales. An asset with no entries sums
            // to zero, which is the correct answer and the first thing a new
            // user sees — never "unavailable".
            const currentQuantity = sum(forAsset);
            // Mirrors BuildGoalProgress::baselineFor. Without it a user who
            // already held gold when they set the goal is told they are ahead on
            // day one and stay ahead forever.
            const baseline = sum(
                forAsset.filter((entry) => entry.occurred_at < goal.started_on),
            );

            return {
                ...goal,
                current_quantity: currentQuantity,
                target_quantity: target,
                ...computePace(
                    baseline,
                    currentQuantity,
                    target,
                    goal.elapsed,
                    goal.total,
                ),
            };
        });
    });

    /**
     * Tell the server about a goal that has just been met.
     *
     * With the vault armed the server cannot see that a target was reached, so
     * without this the portfolio has no date to apply its recency window to and
     * every finished goal would sit there forever.
     *
     * Fires once per goal: the endpoint writes only when the date is still null,
     * and `stamped` stops a re-render sending it twice in the same session.
     */
    const stamped = new Set<number>();

    watchEffect(() => {
        for (const goal of goals.value ?? []) {
            if (!goal.reached || goal.achieved_on !== null) {
                continue;
            }

            if (stamped.has(goal.id)) {
                continue;
            }

            stamped.add(goal.id);

            router.post(
                markGoalAchieved.url(goal.id),
                {},
                { preserveScroll: true, preserveState: true, only: [] },
            );
        }
    });

    return {
        goals,
        decrypting: computed(() => {
            const current = payload();

            return (
                current !== null &&
                current !== undefined &&
                goals.value === null
            );
        }),
    };
}

function sum(entries: DecryptedEntry[]): number {
    return entries.reduce(
        (total, entry) => total + (Number(entry.quantity) || 0),
        0,
    );
}
