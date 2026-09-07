import type { Encrypted } from '@/types/vault';

export type StreakDayState =
    | 'logged'
    | 'no_spend'
    | 'grace'
    | 'missed'
    | 'open';

export type StreakDay = {
    /** Gregorian ISO date — the stable key. */
    date: string;
    /** Day number in the user's own calendar, rendered server-side. */
    label: string;
    state: StreakDayState;
};

/** An asset a goal may be denominated in — the same set the investment form offers. */
export type AssetOption = {
    id: number;
    label: string;
    unit: string;
};

/**
 * Everything about a goal that both paths ship identically.
 *
 * All of it is plaintext except the title, which is a UserEncrypted column and
 * so arrives as ciphertext whenever the viewer's vault is armed — render it
 * through <Ciphered>, never straight into the template.
 */
export type GoalPresentation = {
    id: number;
    title: Encrypted<string> | null;
    asset: {
        id: number;
        key: string;
        label: string;
        icon: string | null;
        icon_svg: string | null;
        color: string | null;
        unit: string;
    };
    target_date: string;
    /** Already rendered in the user's calendar — no jalali maths in the browser. */
    target_date_display: string;
    /**
     * The day the target was first met, or null if it never has been.
     *
     * Plaintext on both paths, which is what lets the portfolio apply its
     * three-month window without reading a single quantity.
     */
    achieved_on: string | null;
    elapsed_days: number;
    total_days: number;
};

export type GoalCard = GoalPresentation & {
    current_quantity: number;
    target_quantity: number;
    /** Null for a zero target: "0% of nothing" is not a fact about the user. */
    progress: number | null;
    expected_quantity: number;
    pace_delta: number;
    on_track: boolean;
    /** The target is met. Distinct from on_track, which is only about schedule. */
    reached: boolean;
    required_per_day: number;
    days_remaining: number;
};

export type Logbook = {
    /** Month name in the user's own calendar. */
    month: string;
    day_of_month: number;
    days_in_month: number;
    days_covered: number;
    days_elapsed: number;
    percent: number;
    uncategorised: number;
    /** Null when the bills module is off — the row is dropped rather than shown as 0 of 0. */
    bills_paid: number | null;
    bills_due: number | null;
    /** Earned by days recorded, never by balance. */
    rank: PilotRank;
    days_logged: number;
    /** Null at the top rank. */
    days_to_next_rank: number | null;
};

export type PilotRank = 'cadet' | 'pilot' | 'captain';

export type Streak = {
    current_run: number;
    best_run: number;
    logged_today: boolean;
    grace_remaining: number;
    today_would_set_record: boolean;
    /** The tail of the chain, oldest first, ending on today. */
    days: StreakDay[];
};

export type ActivitySummary = {
    streak: Streak;
    logbook: Logbook;
    ranks: {
        key: PilotRank;
        threshold: number;
        state: 'passed' | 'current' | 'upcoming';
        days_away: number | null;
    }[];
    moments: {
        key: string;
        achieved_at: string | null;
        missed: { month: string; days: number } | null;
    }[];
};
