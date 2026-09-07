export type MilesShared = {
    balance: number;
    todayClaimed: boolean;
    todayActivityAwarded: boolean;
    claimable: boolean;
    nextClaimReward: number;
    claimStep: number;
    claimProgress: number;
    freezesHeld: number;
    freezesMaximum: number;
    hubUrl: string;
};

export type MilesShortfall = {
    error: 'insufficient_miles';
    available: number;
    cost: number;
    shortfall: number;
    action: string;
};

export type MilesOverview = MilesShared & {
    lifetimeEarned: number;
    lifetimeSpent: number;
    referralCode: string;
    referralUrl: string;
    completedCycles: number;
    badgeTier: string | null;
    streak: {
        current_run: number;
        best_run: number;
        grace_remaining: number;
    };
    claimCycle: {
        step: number;
        reward: number;
        collected: boolean;
        next: boolean;
    }[];
    protections: {
        freezePrice: number;
        repairPrice: number;
        repairsPerMonth: number;
        repairsRemaining: number;
        repairWindowDays: number;
        repairableDates: { date: string; daysAgo: number }[];
    };
    modules: {
        key: string;
        label: string;
        price: number;
        unlocked: boolean;
    }[];
    milestones: {
        key: string;
        miles: number;
        achievedAt: string | null;
    }[];
    cosmetics: {
        key: string;
        type: string;
        label: string;
        price: number;
        owned: boolean;
        selected: boolean;
    }[];
    cosmeticsEnabled: boolean;
    giftingEnabled: boolean;
    referrals: { id: number; userId: number; name: string }[];
};

export type MilesClaimed = {
    miles: number;
    step: number;
    balance: number;
    nextReward: number;
};
