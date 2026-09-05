<script setup lang="ts">
import { Head, router, useHttp, usePage } from '@inertiajs/vue3';
import { Check, LoaderCircle, Lock, TriangleAlert } from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import AdvisorRing from '@/components/advisor/AdvisorRing.vue';
import { useAmountMask } from '@/composables/useAmountMask';
import { usePageSubtitle } from '@/composables/usePageSubtitle';
import { useVault } from '@/composables/useVault';
import { documentNumber, sealDate, sealDateTime } from '@/lib/advisor/format';
import {
    advisorGenerationErrorKey,
    advisorRecommendationFailureKey,
} from '@/lib/advisor/http-errors';
import { useAdvisorLabels } from '@/lib/advisor/labels';
import { seriesColor } from '@/lib/advisor/series';
import { dispatchMilesShortfall } from '@/lib/miles';
import {
    claim as claimPayload,
    clarify,
    consult,
    seal,
} from '@/routes/advisor/recommendations';
import { seal as sealMessage } from '@/routes/advisor/recommendations/messages';
import type {
    AdvisorRecommendationPayload,
    PortfolioPlan,
    RecommendationProp,
    RecommendationResponse,
} from '@/types/advisor';
import type { Encrypted } from '@/types/vault';

type ConversationPayload = {
    content?: string;
    answer?: string;
    requires_recommendation_revision?: boolean;
};
type ConversationMessage = {
    id?: string;
    role: 'user' | 'assistant';
    payload: ConversationPayload;
    /** Set when the question never reached the Advisor, so it can be sent again. */
    failed?: boolean;
};
/** The whole derived profile is sent; these are the parts this page reads. */
type ProfileProp = {
    persona: string;
    maximum_tolerated_drawdown?: number;
    scores?: Record<string, number>;
    selected_assets: {
        asset_key: string;
        name: string;
        liquidity?: string;
    }[];
};
type PlanVariant = 'primary' | 'safer' | 'higher';

const props = defineProps<{
    recommendation: RecommendationProp;
    profile: ProfileProp;
    messages: {
        id: string;
        role: 'user' | 'assistant';
        payload: Encrypted<ConversationPayload>;
        created_at: string;
    }[];
    vaultArmed: boolean;
    consultationPricing: {
        miles: number;
        charging: boolean;
    };
}>();

const { t } = useI18n();
const { label } = useAdvisorLabels();
const { revealAsync, sealForSubmit, trackKey } = useVault();
const { masked } = useAmountMask();
const page = usePage();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);
const calendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);
const payload = ref<AdvisorRecommendationPayload | null>(null);
const conversation = ref<ConversationMessage[]>([]);
const clarificationAnswers = reactive<Record<string, string | boolean>>({});
const acceptedAssetKeys = ref<string[]>([]);
const actionError = ref('');
const integrityError = ref(false);
const chatMessage = ref('');
const isConsulting = ref(false);
const milesBalance = computed(() => page.props.miles?.balance ?? 0);
const canConsult = computed(
    () =>
        !props.consultationPricing.charging ||
        milesBalance.value >= props.consultationPricing.miles,
);
const isGuidance = computed(
    () =>
        payload.value?.status === 'guidance_only' ||
        payload.value?.status === 'cannot_recommend',
);
const hasOmittedAlternative = computed(() =>
    (payload.value?.response_warnings ?? []).some((warning) =>
        [
            'safer_alternative_omitted',
            'higher_risk_alternative_omitted',
        ].includes(warning),
    ),
);

const clarificationForm = useHttp<
    {
        answers: Record<string, string | boolean>;
        accepted_assets: {
            asset_key: string;
            name: string;
            category: string;
        }[];
    },
    RecommendationResponse
>({ answers: {}, accepted_assets: [] });
const recommendationSealer = useHttp<
    {
        recommendation_payload: AdvisorRecommendationPayload | string | null;
    },
    { status: string }
>({ recommendation_payload: null });
const consultationForm = useHttp<
    {
        message: string;
        recommendation_context: AdvisorRecommendationPayload | null;
        history: { role: string; content: string }[];
        sealed_message: string | null;
    },
    { payload: ConversationPayload; vault_seal_required: boolean }
>({
    message: '',
    recommendation_context: null,
    history: [],
    sealed_message: null,
});
const messageSealer = useHttp<
    { payload: ConversationPayload | string | null; role: 'assistant' },
    { id: string }
>({ payload: null, role: 'assistant' });

const assetNames = computed(() =>
    Object.fromEntries(
        props.profile.selected_assets.map((asset) => [
            asset.asset_key,
            asset.name,
        ]),
    ),
);

/**
 * One colour per holding, keyed by asset rather than by row position.
 *
 * The order comes from the approved asset list, not from an allocation, so a
 * colour means the same asset in the ring, the rows, the transition table and
 * every one of the three plan tabs. Sorting the table by weight would otherwise
 * repaint the whole page each time a tab moved an asset up or down.
 */
const assetColors = computed<Record<string, string>>(() =>
    Object.fromEntries(
        props.profile.selected_assets.map((asset, index) => [
            asset.asset_key,
            seriesColor(index),
        ]),
    ),
);

function allocationColor(assetKey: string): string {
    return assetColors.value[assetKey] ?? seriesColor(0);
}

function allocationName(assetKey: string): string {
    return assetNames.value[assetKey] ?? assetKey;
}

/* ── Plan variants ─────────────────────────────────────────────────────── */

const activePlan = ref<PlanVariant>('primary');

/**
 * Only the variants that actually came back.
 *
 * A safer or higher-risk plan is omitted whenever it failed validation or would
 * have sat outside the assessed capacity, so the segmented control is built
 * from what exists rather than from the three the schema allows.
 */
const planTabs = computed(() =>
    (
        [
            { key: 'primary' as const, plan: payload.value?.primary ?? null },
            {
                key: 'safer' as const,
                plan: payload.value?.safer_alternative ?? null,
            },
            {
                key: 'higher' as const,
                plan: payload.value?.higher_risk_alternative?.available
                    ? payload.value.higher_risk_alternative
                    : null,
            },
        ] satisfies { key: PlanVariant; plan: PortfolioPlan | null }[]
    )
        .filter((tab) => tab.plan !== null)
        .map((tab) => ({
            key: tab.key,
            label: t(`advisor.recommendation.${tab.key}_tab`),
        })),
);

const selectedPlan = computed<PortfolioPlan | null>(() => {
    if (activePlan.value === 'safer') {
        return payload.value?.safer_alternative ?? null;
    }

    if (activePlan.value === 'higher') {
        return payload.value?.higher_risk_alternative?.available
            ? payload.value.higher_risk_alternative
            : null;
    }

    return payload.value?.primary ?? null;
});

/** A tab that stops existing after a reload must not leave the page empty. */
watchEffect(() => {
    if (
        payload.value !== null &&
        !planTabs.value.some((tab) => tab.key === activePlan.value)
    ) {
        activePlan.value = 'primary';
    }
});

/** Descending by weight, as the design's table is ordered. */
const planRows = computed(() =>
    [...(selectedPlan.value?.allocations ?? [])]
        .sort((left, right) => right.target_percent - left.target_percent)
        .map((allocation) => ({
            ...allocation,
            name: allocationName(allocation.asset_key),
            color: allocationColor(allocation.asset_key),
        })),
);

/** The ring follows the table, so a segment and its row read left to right. */
const ringSegments = computed(() =>
    planRows.value.map((row) => ({
        percent: row.target_percent,
        color: row.color,
    })),
);

const ringLabel = computed(() =>
    planRows.value
        .map((row) => `${row.name} ${row.target_percent}%`)
        .join(', '),
);

const planTotal = computed(() =>
    planRows.value.reduce((sum, row) => sum + row.target_percent, 0),
);

/**
 * How much of the book could be sold inside a week.
 *
 * Derived here from the liquidity the assessment already recorded against each
 * approved asset. It belongs on the plan object the server returns — see the
 * handoff's backend note — but nothing about it needs the server to be true.
 */
const liquidWithinWeek = computed(() => {
    const liquidities = Object.fromEntries(
        props.profile.selected_assets.map((asset) => [
            asset.asset_key,
            asset.liquidity,
        ]),
    );

    return planRows.value
        .filter((row) =>
            ['same_day', 'within_week'].includes(
                liquidities[row.asset_key] ?? '',
            ),
        )
        .reduce((sum, row) => sum + row.target_percent, 0);
});

const combinedCapital = computed(() => {
    const plan = payload.value?.transition_plan;

    if (!plan || plan.combined_capital === null) {
        return null;
    }

    return `${plan.combined_capital.toLocaleString()} ${plan.base_currency.toUpperCase()}`;
});

/**
 * The plan summary heads the right column.
 *
 * Only the primary plan carries one — the alternatives are named but not
 * narrated — so those fall back to the plan's own name rather than repeating
 * the primary's summary under a different set of weights.
 */
const planSummary = computed(() =>
    activePlan.value === 'primary'
        ? (payload.value?.summary ?? selectedPlan.value?.name ?? '')
        : (selectedPlan.value?.name ?? ''),
);

/**
 * Which rationales are open.
 *
 * Every allocation used to show its reasoning permanently, which put four
 * paragraphs of body copy between the reader and the numbers they came for. The
 * reasoning still matters — it is why the plan is defensible — so it stays one
 * click away rather than being cut. The largest holding opens by default,
 * because that is the weight a reader questions first.
 */
const openRationales = ref<string[]>([]);
const seededRationale = ref<string | null>(null);

watchEffect(() => {
    const largest = planRows.value[0]?.asset_key;

    if (largest !== undefined && seededRationale.value !== largest) {
        seededRationale.value = largest;
        openRationales.value = [largest];
    }
});

function toggleRationale(assetKey: string): void {
    openRationales.value = openRationales.value.includes(assetKey)
        ? openRationales.value.filter((key) => key !== assetKey)
        : [...openRationales.value, assetKey];
}

/* ── Generation ────────────────────────────────────────────────────────── */

/**
 * Watching a queued job rather than holding a request open.
 *
 * Generation takes minutes, so it runs on the queue and this page follows it.
 * Everything below exists to make that wait legible: which of the four stages
 * it has actually reached, and — for a Vault-armed browser — collecting the
 * result the job could not store on its behalf.
 */
const isGenerating = computed(
    () => props.recommendation.status === 'generating',
);
const awaitsClaim = computed(
    () => props.recommendation.status === 'awaiting_vault_seal',
);
const claiming = ref(false);
/** Deliberately not reactive — reading it must not re-trigger the effect below. */
const attemptedClaims = new Set<string>();
let pollTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

const payloadClaimer = useHttp<Record<string, never>, RecommendationResponse>(
    {},
);

/**
 * The four stages, read from the two counters the job moves as it works.
 *
 * Both counters move before the work they describe, so the stage shown is the
 * stage actually reached — never a timer pretending to be progress.
 */
const stageIndex = computed(() => {
    if (awaitsClaim.value) {
        return 3;
    }

    if (props.recommendation.provider_calls === 0) {
        return 0;
    }

    return props.recommendation.repair_attempts > 0 ? 2 : 1;
});

/**
 * The ring, interpolated between stage boundaries.
 *
 * The job reports transitions, not a percentage, so the sweep eases across the
 * quarter belonging to the current stage and stops one point short of the next
 * boundary. It can only cross a boundary when the job actually says so, which
 * is the difference between a progress ring and a spinner that lies.
 */
const ringPercent = ref(0);

/**
 * What the ring paints while the plan is still being written.
 *
 * The weights do not exist yet, so the segments are the approved assets in
 * series order at equal width. The colours are the ones the finished plan will
 * use, which is what makes the sweep read as the portfolio assembling rather
 * than as a loading bar bent into a circle.
 */
const pendingRingSegments = computed(() => {
    const assets = props.profile.selected_assets;

    if (assets.length === 0) {
        return [{ percent: 100, color: seriesColor(0) }];
    }

    return assets.map((asset, index) => ({
        percent: 100 / assets.length,
        color: allocationColor(asset.asset_key) || seriesColor(index),
    }));
});
const ritualComplete = computed(
    () => !isGenerating.value && !awaitsClaim.value && payload.value !== null,
);

function advanceRing(): void {
    if (ritualComplete.value) {
        ringPercent.value = 100;

        return;
    }

    const floor = stageIndex.value * 25;
    ringPercent.value = Math.min(
        floor + 24,
        Math.max(floor, ringPercent.value + 1),
    );
}

const stages = computed(() =>
    (
        [
            'stage_reading',
            'stage_designing',
            'stage_checking',
            'stage_sealing',
        ] as const
    ).map((key, index) => {
        const done = ritualComplete.value || index < stageIndex.value;
        const active = !ritualComplete.value && index === stageIndex.value;

        return {
            key,
            label: t(`advisor.recommendation.${key}`),
            mark: done ? '✓' : active ? '›' : '·',
            markColor: done ? '#02cd86' : active ? '#d9c48f' : '#3a3a3a',
            labelColor: done ? '#989898' : active ? '#ffffff' : '#5a5a5a',
            meta: done
                ? t('advisor.recommendation.stage_done')
                : active
                  ? t('advisor.recommendation.stage_working')
                  : '',
        };
    }),
);

/**
 * The ritual only ends when the reader says so.
 *
 * Someone who watched the ring fill should get to see it finish rather than
 * have the page swap under them the instant the poll lands. Arriving at an
 * already-finished recommendation from the history rail skips it entirely.
 */
const watchedGeneration = ref(
    props.recommendation.status === 'generating' ||
        props.recommendation.status === 'awaiting_vault_seal',
);
const openedPlan = ref(false);
const showsRitual = computed(
    () =>
        isGenerating.value ||
        (awaitsClaim.value && props.vaultArmed) ||
        (watchedGeneration.value && !openedPlan.value && ritualComplete.value),
);

/**
 * Take the payload the job parked, seal it, and hand back the ciphertext.
 *
 * Only Vault-armed browsers get here: the server holds the key to nothing, so
 * this is the one moment the recommendation can be made permanent.
 */
async function claimAndSeal(): Promise<void> {
    if (claiming.value || attemptedClaims.has(props.recommendation.id)) {
        return;
    }

    /*
     * Marked before the request rather than after it, and never cleared. The
     * status only changes once the reload lands, so releasing this on either
     * success or failure would let the effect below fire again against a
     * recommendation that is already sealed — or retry a failing claim forever.
     */
    attemptedClaims.add(props.recommendation.id);
    claiming.value = true;
    actionError.value = '';

    try {
        const claimed = await payloadClaimer.post(
            claimPayload(props.recommendation.id).url,
        );

        if (!claimed.payload) {
            return;
        }

        await sealGeneratedResponse({ ...claimed, vault_seal_required: true });
        router.reload({ only: ['recommendation', 'messages'] });
    } catch (error) {
        actionError.value = t(advisorGenerationErrorKey(error));
    } finally {
        claiming.value = false;
    }
}

function syncWatchers(): void {
    const shouldPoll = isGenerating.value;

    if (shouldPoll && pollTimer === undefined) {
        pollTimer = setInterval(
            () => router.reload({ only: ['recommendation', 'messages'] }),
            3000,
        );
    }

    if (!shouldPoll && pollTimer !== undefined) {
        clearInterval(pollTimer);
        pollTimer = undefined;
    }

    const shouldTick = showsRitual.value;

    if (shouldTick && clockTimer === undefined) {
        advanceRing();
        clockTimer = setInterval(advanceRing, 1000);
    }

    if (!shouldTick && clockTimer !== undefined) {
        clearInterval(clockTimer);
        clockTimer = undefined;
    }
}

watchEffect(() => {
    syncWatchers();

    // A Vault browser that lands on a finished job collects it straight away —
    // the user should never have to press a button to finish their own request.
    if (awaitsClaim.value && props.vaultArmed && !claiming.value) {
        void claimAndSeal();
    }
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    clearInterval(clockTimer);
});

watchEffect(async () => {
    trackKey();
    const revealed = await revealAsync(
        props.recommendation.payload,
        'advisor_recommendations',
        'json',
    );

    if (revealed !== undefined) {
        payload.value = revealed;

        if (props.recommendation.output_hash) {
            integrityError.value =
                (await hashPayload(revealed)) !==
                props.recommendation.output_hash;
        }
    }

    const resolved: ConversationMessage[] = [];

    for (const message of props.messages) {
        const messagePayload = await revealAsync(
            message.payload,
            'advisor_messages',
            'json',
        );

        if (messagePayload !== undefined) {
            resolved.push({
                id: message.id,
                role: message.role,
                payload: messagePayload,
            });
        }
    }

    conversation.value = resolved;
});

function toggleAcceptedAsset(key: string): void {
    acceptedAssetKeys.value = acceptedAssetKeys.value.includes(key)
        ? acceptedAssetKeys.value.filter((item) => item !== key)
        : [...acceptedAssetKeys.value, key];
}

async function submitClarifications(): Promise<void> {
    if (!payload.value) {
        return;
    }

    actionError.value = '';
    clarificationForm.answers = { ...clarificationAnswers };
    clarificationForm.accepted_assets =
        payload.value.suggested_additional_assets
            .filter((asset) => acceptedAssetKeys.value.includes(asset.key))
            .map((asset) => ({
                asset_key: asset.key,
                name: asset.name,
                category: asset.category,
            }));

    try {
        // Queued like the first round, so the page goes back to watching rather
        // than holding another multi-minute request open.
        await clarificationForm.post(clarify(props.recommendation.id).url);
        router.reload({ only: ['recommendation', 'messages'] });
    } catch (error) {
        actionError.value =
            error instanceof Error
                ? error.message
                : t('advisor.recommendation.failed');
    }
}

async function sealGeneratedResponse(
    response: RecommendationResponse,
): Promise<void> {
    if (!response.vault_seal_required || !response.payload) {
        return;
    }

    const sealed = await sealForSubmit(
        { recommendation_payload: response.payload },
        'advisor_recommendations',
        { recommendation_payload: 'json' },
    );
    recommendationSealer.recommendation_payload =
        sealed.recommendation_payload as unknown as string;
    await recommendationSealer.patch(seal(response.recommendation_id).url);
}

/* ── Consultation ──────────────────────────────────────────────────────── */

/**
 * The consultation is its own screen rather than a panel under the plan, as the
 * design has it. It is not its own route: the thread, the plan and the vault key
 * all belong to this page, and splitting them would mean decrypting twice.
 */
const showsConsultation = ref(false);

function openConsultation(prefill?: string): void {
    if (prefill !== undefined) {
        chatMessage.value = prefill;
    }

    showsConsultation.value = true;
}

/**
 * Openers offered in the composer.
 *
 * These were sitting in a placeholder at 25% opacity, which is the least useful
 * place for the questions most worth asking. As chips they double as the
 * feature's only onboarding.
 */
const chatSuggestions = computed(() => [
    t('advisor.recommendation.suggest_fit'),
    t('advisor.recommendation.suggest_risk'),
    t('advisor.recommendation.suggest_start'),
]);

function useSuggestion(suggestion: string): void {
    chatMessage.value = suggestion;
    void sendMessage();
}

/**
 * The citation chip under an advisor reply.
 *
 * Shown only when the reply actually leans on a derived value — the text is
 * searched for the figures the profile holds. A chip on every message would
 * stop meaning anything; a chip on the ones that cite your drawdown cap back at
 * you is the whole point of it.
 */
function citationFor(message: ConversationMessage): string {
    if (message.role !== 'assistant') {
        return '';
    }

    const text = message.payload.answer ?? message.payload.content ?? '';
    const risk = props.profile.scores?.effective_risk;
    const drawdown = props.profile.maximum_tolerated_drawdown;
    const parts: string[] = [];

    if (risk !== undefined && text.includes(String(risk))) {
        parts.push(`${t('advisor.profile.risk_score')} ${risk}`);
    }

    if (drawdown !== undefined && text.includes(String(drawdown))) {
        parts.push(`${t('advisor.recommendation.drawdown_cap')} ${drawdown}%`);
    }

    return parts.join(' · ');
}

/** Enter sends; Shift+Enter is a newline, as every chat surface behaves. */
function onChatKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
        return;
    }

    event.preventDefault();
    void sendMessage();
}

function growTextarea(event: Event): void {
    const field = event.target as HTMLTextAreaElement;
    field.style.height = 'auto';
    field.style.height = `${Math.min(field.scrollHeight, 160)}px`;
}

/**
 * Put a failed question back where the user can act on it.
 *
 * A page-level error meant retyping a question they had already written; this
 * keeps the text and offers to send it again.
 */
function retryMessage(index: number): void {
    const failed = conversation.value[index];

    if (failed === undefined || isConsulting.value) {
        return;
    }

    conversation.value.splice(index, 1);
    chatMessage.value = failed.payload.content ?? '';
    void sendMessage();
}

async function sendMessage(): Promise<void> {
    const text = chatMessage.value.trim();

    if (!text || !payload.value || isConsulting.value || !canConsult.value) {
        return;
    }

    isConsulting.value = true;
    actionError.value = '';
    consultationForm.message = text;
    consultationForm.recommendation_context = props.vaultArmed
        ? payload.value
        : null;
    consultationForm.history = conversation.value.map((message) => ({
        role: message.role,
        content: message.payload.content ?? message.payload.answer ?? '',
    }));

    // Show the question immediately and empty the box. A question that sits in
    // the field until the answer returns reads as though nothing was sent.
    const pending: ConversationMessage = {
        role: 'user',
        payload: { content: text },
    };
    conversation.value.push(pending);
    chatMessage.value = '';

    try {
        if (props.vaultArmed) {
            const sealedUser = await sealForSubmit(
                { payload: { content: text } },
                'advisor_messages',
                { payload: 'json' },
            );
            consultationForm.sealed_message =
                sealedUser.payload as unknown as string;
        }

        const response = await consultationForm.post(
            consult(props.recommendation.id).url,
        );
        conversation.value.push({
            role: 'assistant',
            payload: response.payload,
        });

        if (response.vault_seal_required) {
            const sealedAssistant = await sealForSubmit(
                { payload: response.payload },
                'advisor_messages',
                { payload: 'json' },
            );
            messageSealer.payload =
                sealedAssistant.payload as unknown as string;
            await messageSealer.post(sealMessage(props.recommendation.id).url);
        }

        router.reload({ only: ['miles'] });
    } catch (error) {
        dispatchMilesShortfall(error);
        // Marked rather than removed: the question stays on screen with a way to
        // send it again, instead of becoming a page-level error and lost text.
        pending.failed = true;
    } finally {
        isConsulting.value = false;
    }
}

/* ── Integrity ─────────────────────────────────────────────────────────── */

function sortedObject(value: unknown): unknown {
    if (Array.isArray(value)) {
        return value.map(sortedObject);
    }

    if (typeof value !== 'object' || value === null) {
        return value;
    }

    return Object.fromEntries(
        Object.entries(value)
            .sort(([left], [right]) => left.localeCompare(right))
            .map(([key, item]) => [key, sortedObject(item)]),
    );
}

async function hashPayload(value: unknown): Promise<string> {
    const bytes = new TextEncoder().encode(JSON.stringify(sortedObject(value)));
    const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', bytes));

    return [...digest]
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}

/** `HASH 4f2a·9c71·0e88` — enough of the digest to compare, not to read. */
const planHash = computed(() => {
    const hash = props.recommendation.output_hash;

    if (!hash) {
        return '';
    }

    return `${hash.slice(0, 4)}·${hash.slice(4, 8)}·${hash.slice(8, 12)}`;
});

usePageSubtitle(() =>
    showsConsultation.value
        ? t('advisor.recommendation.consultation')
        : showsRitual.value
          ? t('advisor.recommendation.designing')
          : `${t('advisor.recommendation.primary')} · No. ${documentNumber(
                props.recommendation.id.slice(-4),
            )}`,
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Advisor', href: '/advisor' },
            { title: 'Advisor', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="t('advisor.recommendation.primary')" />

    <div
        data-app-flush-bottom
        class="min-h-[calc(100svh-72px)] bg-background px-3.5 pt-3.5 pb-[120px] text-white lg:min-h-[calc(100svh-92px)] lg:px-7 lg:pt-[22px] lg:pb-[140px]"
    >
        <p
            v-if="integrityError"
            class="mb-4 rounded-[12px] border border-[#e9756f]/25 bg-[#e9756f]/8 px-4 py-3 text-sm text-[#f2b2ae]"
            role="alert"
        >
            <TriangleAlert class="me-2 inline size-4" aria-hidden="true" />{{
                t('advisor.recommendation.integrity_failed')
            }}
        </p>
        <p
            v-if="actionError"
            class="mb-4 rounded-[12px] border border-[#e9756f]/25 bg-[#e9756f]/8 px-4 py-3 text-sm text-[#f2b2ae]"
            role="alert"
        >
            {{ actionError }}
        </p>

        <!-- ══ THE RITUAL ═══════════════════════════════════════════════ -->
        <section
            v-if="showsRitual"
            class="advisor-rise grid min-h-[68vh] place-items-center"
        >
            <div class="w-full max-w-[760px] text-center">
                <p
                    class="advisor-mono text-[10px] tracking-[0.26em] text-[#d9c48f] uppercase"
                >
                    {{ t('advisor.recommendation.generating') }}
                </p>

                <!-- Decorative: the stage list below is the live region, so a
                     percentage that ticks every second is not announced. -->
                <AdvisorRing
                    class="mt-11"
                    aria-hidden="true"
                    filling
                    :segments="pendingRingSegments"
                    :filled="ringPercent"
                >
                    <span
                        class="advisor-mono advisor-figure block text-[42px] font-medium tracking-[-0.03em] text-white"
                        >{{ ringPercent }}%</span
                    >
                    <span
                        class="advisor-mono mt-1.5 block text-[9.5px] tracking-[0.2em] text-[#686868] uppercase"
                        >{{ t('advisor.recommendation.allocated') }}</span
                    >
                </AdvisorRing>

                <ol
                    class="advisor-rule mx-auto mt-11 max-w-[430px] text-start"
                    role="status"
                    aria-live="polite"
                >
                    <li
                        v-for="stage in stages"
                        :key="stage.key"
                        class="flex items-center gap-3.5 border-b border-white/7 py-3.5"
                    >
                        <span
                            class="advisor-mono w-3 shrink-0 text-xs"
                            :style="{ color: stage.markColor }"
                            aria-hidden="true"
                            >{{ stage.mark }}</span
                        >
                        <span
                            class="text-[13.5px]"
                            :style="{ color: stage.labelColor }"
                            >{{ stage.label }}</span
                        >
                        <span
                            class="advisor-mono ms-auto text-[10px] text-[#5a5a5a] uppercase"
                            >{{ stage.meta }}</span
                        >
                    </li>
                </ol>

                <button
                    v-if="ritualComplete"
                    type="button"
                    class="advisor-rise mt-[34px] cursor-pointer rounded-[10px] bg-[#02cd86] px-7 py-3.5 text-sm font-semibold text-[#101010] transition-colors hover:bg-[#16e19a]"
                    @click="openedPlan = true"
                >
                    {{
                        payload?.status === 'recommendation_ready'
                            ? t('advisor.recommendation.open_portfolio')
                            : t('advisor.recommendation.open_result')
                    }}
                    <span class="advisor-mono ms-1 text-[13px] rtl:rotate-180"
                        >→</span
                    >
                </button>
                <p
                    v-else
                    class="mx-auto mt-[34px] max-w-[44ch] text-[13px] leading-[1.7] text-[#5a5a5a]"
                >
                    {{ t('advisor.recommendation.leave_safe') }}
                </p>
            </div>
        </section>

        <!-- ══ VAULT LOCKED ═════════════════════════════════════════════ -->
        <section
            v-else-if="!payload && props.recommendation.status !== 'failed'"
            class="grid min-h-[50vh] place-items-center text-center"
        >
            <div>
                <Lock
                    class="mx-auto size-6 text-[#d9c48f]"
                    :stroke-width="1.4"
                    aria-hidden="true"
                />
                <p class="mt-4 text-[13.5px] text-[#686868]">
                    {{ t('advisor.recommendation.locked') }}
                </p>
            </div>
        </section>

        <!-- ══ FAILED ═══════════════════════════════════════════════════ -->
        <section
            v-else-if="props.recommendation.status === 'failed'"
            class="advisor-rise mx-auto max-w-[660px] pt-10 text-center"
        >
            <TriangleAlert
                class="mx-auto size-7 text-[#e9756f]"
                :stroke-width="1.4"
                aria-hidden="true"
            />
            <h1 class="advisor-serif mt-5 text-[30px] leading-[1.15]">
                {{ t('advisor.recommendation.failed') }}
            </h1>
            <p class="mt-3 text-[14px] leading-[1.75] text-[#686868]">
                {{
                    t(
                        advisorRecommendationFailureKey(
                            props.recommendation.failure_code ?? undefined,
                        ),
                    )
                }}
            </p>
        </section>

        <!-- ══ GUIDANCE ONLY ════════════════════════════════════════════ -->
        <section
            v-else-if="isGuidance && payload"
            class="advisor-rise mx-auto max-w-[720px]"
            role="status"
        >
            <div class="advisor-rule-hero" />
            <p
                class="advisor-mono mt-9 text-[10px] tracking-[0.26em] text-[#d9c48f] uppercase"
            >
                {{ t('advisor.recommendation.guidance_badge') }}
            </p>
            <h1
                class="advisor-serif mt-[18px] text-[28px] leading-[1.15] md:text-[36px]"
            >
                {{
                    payload.summary ??
                    t('advisor.recommendation.guidance_title')
                }}
            </h1>
            <p class="mt-5 text-[15px] leading-[1.75] text-[#989898]">
                {{
                    payload.fit_warning ??
                    payload.cannot_recommend_reason ??
                    t('advisor.recommendation.guidance_body')
                }}
            </p>

            <div class="mt-10">
                <p
                    class="advisor-mono pb-3.5 text-[10px] tracking-[0.2em] text-[#686868] uppercase"
                >
                    {{ t('advisor.recommendation.next_steps') }}
                </p>
                <ul class="advisor-rule">
                    <li
                        v-for="step in payload.next_steps ?? []"
                        :key="step"
                        class="border-b border-white/7 py-4 text-[13.5px] leading-[1.75] text-[#989898]"
                    >
                        {{ step }}
                    </li>
                </ul>
            </div>
        </section>

        <!-- ══ CLARIFICATION ════════════════════════════════════════════ -->
        <section
            v-else-if="payload?.status === 'needs_clarification'"
            class="advisor-rise mx-auto max-w-[660px]"
        >
            <div class="advisor-rule-hero" />
            <p
                class="advisor-mono mt-9 text-[10px] tracking-[0.22em] text-[#d9c48f] uppercase"
            >
                {{ t('advisor.recommendation.clarification_eyebrow') }}
            </p>
            <h1
                class="advisor-serif mt-4 text-[28px] leading-[1.15] md:text-[36px]"
            >
                {{ t('advisor.recommendation.clarification') }}
            </h1>

            <div class="mt-10">
                <div
                    v-for="question in payload.questions"
                    :key="question.key"
                    class="border-t border-white/8 pt-8 pb-9"
                >
                    <h2 class="advisor-serif text-[20px] leading-[1.3]">
                        {{ question.question }}
                    </h2>
                    <p
                        class="mt-2.5 text-[12.5px] leading-[1.7] text-[#5a5a5a]"
                    >
                        {{ question.reason }}
                    </p>

                    <div
                        v-if="question.input_type === 'single_choice'"
                        class="mt-5 flex flex-wrap gap-[7px]"
                    >
                        <button
                            v-for="option in question.options"
                            :key="option"
                            type="button"
                            class="flex cursor-pointer items-center gap-[11px] rounded-[10px] border px-4 py-3 text-start text-[13.5px] transition-[border-color,background-color] duration-150"
                            :class="
                                clarificationAnswers[question.key] === option
                                    ? 'border-[#d9c48f]/50 bg-[#d9c48f]/9 text-white'
                                    : 'border-white/10 text-[#989898] hover:border-[#d9c48f]/45 hover:text-white'
                            "
                            :aria-pressed="
                                clarificationAnswers[question.key] === option
                            "
                            @click="clarificationAnswers[question.key] = option"
                        >
                            <span
                                class="size-[7px] shrink-0 rounded-full"
                                :class="
                                    clarificationAnswers[question.key] ===
                                    option
                                        ? 'bg-[#d9c48f]'
                                        : 'bg-white/16'
                                "
                                aria-hidden="true"
                            />
                            {{ option }}
                        </button>
                    </div>
                    <div
                        v-else-if="question.input_type === 'boolean'"
                        class="mt-5 flex flex-wrap gap-[7px]"
                    >
                        <button
                            v-for="choice in [true, false]"
                            :key="String(choice)"
                            type="button"
                            class="flex cursor-pointer items-center gap-[11px] rounded-[10px] border px-4 py-3 text-[13.5px] transition-[border-color,background-color] duration-150"
                            :class="
                                clarificationAnswers[question.key] === choice
                                    ? 'border-[#d9c48f]/50 bg-[#d9c48f]/9 text-white'
                                    : 'border-white/10 text-[#989898] hover:border-[#d9c48f]/45 hover:text-white'
                            "
                            :aria-pressed="
                                clarificationAnswers[question.key] === choice
                            "
                            @click="clarificationAnswers[question.key] = choice"
                        >
                            <span
                                class="size-[7px] shrink-0 rounded-full"
                                :class="
                                    clarificationAnswers[question.key] ===
                                    choice
                                        ? 'bg-[#d9c48f]'
                                        : 'bg-white/16'
                                "
                                aria-hidden="true"
                            />
                            {{
                                choice
                                    ? t('advisor.options_section.yes')
                                    : t('advisor.options_section.no')
                            }}
                        </button>
                    </div>
                    <input
                        v-else
                        v-model="clarificationAnswers[question.key] as string"
                        maxlength="500"
                        class="mt-5 h-11 w-full rounded-[10px] border border-white/10 bg-[#252525] px-3 text-sm text-white outline-none focus:border-[#d9c48f]/45"
                    />
                </div>
            </div>

            <div
                v-if="payload.suggested_additional_assets.length"
                class="border-t border-white/8 pt-8"
            >
                <p
                    class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                >
                    {{ t('advisor.recommendation.allow_diversifier') }}
                </p>
                <button
                    v-for="asset in payload.suggested_additional_assets"
                    :key="asset.key"
                    type="button"
                    class="mt-3 flex w-full cursor-pointer items-start gap-3 rounded-[12px] border p-4 text-start transition-[border-color,background-color] duration-150"
                    :class="
                        acceptedAssetKeys.includes(asset.key)
                            ? 'border-[#d9c48f]/50 bg-[#d9c48f]/9'
                            : 'border-white/10 hover:border-[#d9c48f]/45'
                    "
                    :aria-pressed="acceptedAssetKeys.includes(asset.key)"
                    @click="toggleAcceptedAsset(asset.key)"
                >
                    <span
                        class="mt-1.5 size-[7px] shrink-0 rounded-full"
                        :class="
                            acceptedAssetKeys.includes(asset.key)
                                ? 'bg-[#d9c48f]'
                                : 'bg-white/16'
                        "
                        aria-hidden="true"
                    />
                    <span class="min-w-0">
                        <span class="block text-sm text-white">{{
                            asset.name
                        }}</span>
                        <span
                            class="mt-1 block text-[12.5px] leading-[1.7] text-[#686868]"
                            >{{ asset.reason }}</span
                        >
                    </span>
                </button>
            </div>

            <div class="advisor-rule mt-9 flex justify-end pt-[26px]">
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-2.5 rounded-[10px] bg-[#02cd86] px-[26px] py-[13px] text-sm font-semibold text-[#101010] transition-colors hover:bg-[#16e19a] disabled:cursor-not-allowed disabled:opacity-45"
                    :disabled="clarificationForm.processing"
                    @click="submitClarifications"
                >
                    {{ t('advisor.recommendation.submit_answers') }}
                    <span class="advisor-mono text-[13px] rtl:rotate-180"
                        >→</span
                    >
                </button>
            </div>
        </section>

        <!-- ══ CONSULTATION ═════════════════════════════════════════════ -->
        <section
            v-else-if="showsConsultation && payload?.primary"
            class="advisor-rise mx-auto max-w-[720px]"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3.5 border-b border-[#d9c48f]/28 pb-[18px]"
            >
                <div>
                    <p
                        class="advisor-mono text-[10px] tracking-[0.22em] text-[#d9c48f] uppercase"
                    >
                        {{ t('advisor.recommendation.consultation') }}
                    </p>
                    <h1
                        class="advisor-serif mt-3 text-[24px] leading-[1.15] md:text-[30px]"
                    >
                        {{ selectedPlan?.name }} ·
                        {{
                            sealDate(
                                props.recommendation.generated_at,
                                calendar,
                            )
                        }}
                    </h1>
                </div>
                <button
                    type="button"
                    class="flex shrink-0 cursor-pointer items-center gap-2 rounded-[10px] border border-white/13 px-[18px] py-2.5 text-[13px] text-[#989898] transition-colors hover:border-white/28 hover:text-white"
                    @click="showsConsultation = false"
                >
                    <span class="advisor-mono text-[13px] rtl:rotate-180"
                        >←</span
                    >
                    {{ t('advisor.recommendation.back_to_plan') }}
                </button>
            </div>

            <!-- Not bubbles. A rule beside the block is enough to tell the two
                 voices apart, and it lets the advisor's replies be set as prose
                 rather than squeezed into a chat balloon. -->
            <div class="flex flex-col gap-10 pt-10" :aria-busy="isConsulting">
                <div
                    v-for="(message, index) in conversation"
                    :key="message.id ?? index"
                    class="border-s ps-5"
                    :class="
                        message.role === 'user'
                            ? 'border-s-white/12'
                            : 'border-s-[#d9c48f]/40'
                    "
                >
                    <p
                        class="advisor-mono pb-3 text-[9.5px] tracking-[0.2em] uppercase"
                        :class="
                            message.role === 'user'
                                ? 'text-[#686868]'
                                : 'text-[#d9c48f]'
                        "
                    >
                        {{
                            message.role === 'user'
                                ? t('advisor.recommendation.tag_you')
                                : t('advisor.recommendation.tag_advisor')
                        }}
                    </p>
                    <p
                        class="whitespace-pre-wrap"
                        :class="
                            message.role === 'user'
                                ? 'text-[15px] leading-[1.75] text-[#e5e5e5]'
                                : 'advisor-serif text-[17px] leading-[1.6] text-white md:text-[19px]'
                        "
                    >
                        {{ message.payload.content ?? message.payload.answer }}
                    </p>
                    <p
                        v-if="citationFor(message)"
                        class="advisor-mono advisor-figure mt-4 inline-flex items-center gap-2 rounded-full border border-[#d9c48f]/28 px-3 py-1.5 text-[10px] tracking-[0.12em] text-[#d9c48f] uppercase"
                    >
                        {{ citationFor(message) }}
                    </p>
                    <p
                        v-if="message.failed"
                        class="mt-3 flex items-center gap-2 text-xs text-[#e9756f]"
                    >
                        {{ t('advisor.recommendation.send_failed') }}
                        <button
                            type="button"
                            class="cursor-pointer font-medium text-[#d9c48f] underline-offset-2 transition hover:underline focus-visible:ring-2 focus-visible:ring-[#d9c48f] focus-visible:outline-none"
                            :disabled="isConsulting"
                            @click="retryMessage(index)"
                        >
                            {{ t('advisor.recommendation.retry') }}
                        </button>
                    </p>
                </div>

                <div v-if="isConsulting" role="status" aria-live="polite">
                    <p
                        class="advisor-mono pb-3 text-[9.5px] tracking-[0.2em] text-[#d9c48f] uppercase"
                    >
                        {{ t('advisor.recommendation.tag_advisor') }}
                    </p>
                    <p class="flex items-center gap-2.5 text-[#686868]">
                        <span class="advisor-serif-italic text-[17px]">{{
                            t('advisor.recommendation.consulting')
                        }}</span>
                        <span
                            class="advisor-blink inline-block h-3.5 w-[5px] bg-[#d9c48f]"
                            aria-hidden="true"
                        />
                    </p>
                </div>
            </div>

            <div class="mt-[52px] border-t border-white/8 pt-[22px]">
                <div class="flex flex-wrap gap-2 pb-4">
                    <button
                        v-for="suggestion in chatSuggestions"
                        :key="suggestion"
                        type="button"
                        class="cursor-pointer rounded-[10px] border border-white/11 px-[15px] py-2.5 text-start text-[12.5px] text-[#989898] transition-colors hover:border-[#d9c48f]/45 hover:text-white disabled:opacity-45"
                        :disabled="isConsulting || !canConsult"
                        @click="useSuggestion(suggestion)"
                    >
                        {{ suggestion }}
                    </button>
                </div>
                <form
                    class="advisor-composer flex items-end gap-3 rounded-[16px] border border-white/11 bg-[#1a1a1a] px-4 py-3.5"
                    @submit.prevent="sendMessage"
                >
                    <label class="sr-only" for="advisor-chat">{{
                        t('advisor.recommendation.ask')
                    }}</label>
                    <textarea
                        id="advisor-chat"
                        v-model="chatMessage"
                        maxlength="1500"
                        rows="1"
                        class="max-h-40 min-w-0 flex-1 resize-none border-0 bg-transparent py-1 text-[14.5px] leading-[1.6] text-white outline-none placeholder:text-[#686868]"
                        :disabled="isConsulting"
                        :placeholder="
                            t('advisor.recommendation.ask_placeholder')
                        "
                        @input="growTextarea"
                        @keydown="onChatKeydown"
                    />
                    <button
                        type="submit"
                        class="shrink-0 cursor-pointer rounded-[10px] bg-[#02cd86] px-5 py-2.5 text-[13.5px] font-semibold text-[#101010] transition-colors hover:bg-[#16e19a] disabled:cursor-not-allowed disabled:opacity-45"
                        :disabled="
                            isConsulting || !chatMessage.trim() || !canConsult
                        "
                    >
                        <LoaderCircle
                            v-if="isConsulting"
                            aria-hidden="true"
                            class="size-4 animate-spin motion-reduce:animate-none"
                        />
                        <template v-else>{{
                            t('advisor.recommendation.send')
                        }}</template>
                        <span class="sr-only">{{
                            isConsulting
                                ? t('advisor.recommendation.consulting')
                                : t('advisor.recommendation.send')
                        }}</span>
                    </button>
                </form>
                <div
                    class="advisor-mono mt-3 flex flex-wrap items-center justify-between gap-3.5 text-[9.5px] tracking-[0.1em] text-[#5a5a5a] uppercase"
                >
                    <span>
                        {{ t('advisor.recommendation.send_hint') }} ·
                        {{
                            t('advisor.miles.consultation_price', {
                                miles: props.consultationPricing.miles,
                            })
                        }}
                        <template v-if="!props.consultationPricing.charging">
                            · {{ t('advisor.miles.shadow') }}
                        </template>
                        <template v-else-if="!canConsult">
                            ·
                            {{
                                t('advisor.miles.shortfall', {
                                    miles:
                                        props.consultationPricing.miles -
                                        milesBalance,
                                })
                            }}
                        </template>
                    </span>
                    <span
                        v-if="props.vaultArmed"
                        class="inline-flex items-center gap-[7px]"
                    >
                        <Lock
                            class="size-3 text-[#02cd86]"
                            :stroke-width="1.7"
                            aria-hidden="true"
                        />
                        {{ t('advisor.recommendation.chat_encrypted') }}
                    </span>
                </div>
            </div>
        </section>

        <!-- ══ THE PLAN ═════════════════════════════════════════════════ -->
        <template
            v-else-if="
                payload?.status === 'recommendation_ready' &&
                payload.primary &&
                selectedPlan
            "
        >
            <div class="advisor-rise">
                <div
                    class="flex flex-wrap items-center justify-between gap-[18px] border-b border-[#d9c48f]/28 pb-4"
                >
                    <div class="flex flex-wrap items-center gap-3.5">
                        <span
                            class="advisor-mono inline-flex items-center gap-[7px] rounded-full border border-[#d9c48f]/35 px-3 py-1.5 text-[10px] tracking-[0.16em] text-[#d9c48f] uppercase"
                        >
                            <Check
                                class="size-3"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('advisor.recommendation.validated_badge') }}
                        </span>
                        <span
                            class="advisor-mono advisor-figure text-[10px] tracking-[0.1em] text-[#5a5a5a] uppercase"
                            >{{
                                planHash
                                    ? `${t('advisor.recommendation.hash')} ${planHash} · `
                                    : ''
                            }}{{
                                sealDateTime(
                                    props.recommendation.generated_at,
                                    calendar,
                                )
                            }}</span
                        >
                    </div>
                    <div
                        v-if="planTabs.length > 1"
                        class="flex gap-0.5 rounded-[9px] border border-white/8 bg-[#1a1a1a] p-[3px]"
                        role="tablist"
                    >
                        <button
                            v-for="tab in planTabs"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            :aria-selected="activePlan === tab.key"
                            class="advisor-mono cursor-pointer rounded-[7px] px-3.5 py-2 text-[10px] tracking-[0.12em] whitespace-nowrap uppercase transition-colors"
                            :class="
                                activePlan === tab.key
                                    ? 'bg-[#d9c48f]/16 text-[#d9c48f]'
                                    : 'text-[#686868] hover:text-white'
                            "
                            @click="activePlan = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <p
                    v-if="payload.fit_status === 'closest_fit'"
                    class="mt-6 flex gap-3 rounded-[12px] border border-[#d9c48f]/24 bg-[#d9c48f]/5 px-4 py-3.5 text-[13px] leading-[1.65] text-[#cfc4a6]"
                    role="status"
                >
                    <TriangleAlert
                        class="mt-0.5 size-[15px] shrink-0"
                        :stroke-width="1.6"
                        aria-hidden="true"
                    />
                    <span>{{
                        payload.fit_warning ??
                        t('advisor.recommendation.closest_fit_body')
                    }}</span>
                </p>
                <p
                    v-if="hasOmittedAlternative"
                    class="advisor-mono mt-4 text-[9.5px] leading-[1.8] tracking-[0.1em] text-[#5a5a5a] uppercase"
                    role="status"
                >
                    {{ t('advisor.recommendation.alternative_omitted') }}
                </p>

                <div
                    class="grid gap-[52px] pt-10 xl:grid-cols-[minmax(268px,330px)_minmax(420px,1fr)]"
                >
                    <div class="min-w-0">
                        <AdvisorRing
                            :segments="ringSegments"
                            :label="ringLabel"
                        >
                            <span
                                class="advisor-mono block text-[9.5px] tracking-[0.2em] text-[#686868] uppercase"
                                >{{
                                    t('advisor.recommendation.holdings')
                                }}</span
                            >
                            <span
                                class="advisor-mono advisor-figure mt-1 block text-[44px] leading-[1.05] font-medium tracking-[-0.04em]"
                                >{{ planRows.length }}</span
                            >
                            <span
                                class="advisor-serif mt-1.5 block text-[17px] text-[#d9c48f]"
                                >{{ selectedPlan.name }}</span
                            >
                        </AdvisorRing>

                        <dl class="mt-8 border-t border-white/8">
                            <div
                                class="flex items-baseline justify-between gap-2.5 border-b border-white/7 py-[13px]"
                            >
                                <dt
                                    class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                                >
                                    {{ t('advisor.recommendation.largest') }}
                                </dt>
                                <dd
                                    class="advisor-mono advisor-figure text-[12.5px]"
                                >
                                    {{
                                        (
                                            planRows[0]?.target_percent ?? 0
                                        ).toFixed(1)
                                    }}%
                                </dd>
                            </div>
                            <div
                                class="flex items-baseline justify-between gap-2.5 border-b border-white/7 py-[13px]"
                            >
                                <dt
                                    class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                                >
                                    {{ t('advisor.recommendation.liquid') }}
                                </dt>
                                <dd
                                    class="advisor-mono advisor-figure text-[12.5px]"
                                >
                                    {{ liquidWithinWeek.toFixed(1) }}%
                                </dd>
                            </div>
                            <div
                                v-if="combinedCapital"
                                class="flex items-baseline justify-between gap-2.5 py-[13px]"
                            >
                                <dt
                                    class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                                >
                                    {{ t('advisor.recommendation.capital') }}
                                </dt>
                                <dd
                                    class="advisor-mono advisor-figure text-[12.5px]"
                                    :class="maskClass"
                                >
                                    {{ combinedCapital }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="min-w-0">
                        <h1
                            class="advisor-serif max-w-[34ch] text-[24px] leading-[1.18] md:text-[32px]"
                        >
                            {{ planSummary }}
                        </h1>

                        <div class="mt-[30px]">
                            <div
                                class="advisor-mono grid grid-cols-[12px_minmax(0,1fr)_78px_22px] items-center gap-3.5 border-b border-[#d9c48f]/24 pb-[11px] text-[9.5px] tracking-[0.14em] text-[#5a5a5a] uppercase sm:grid-cols-[12px_minmax(0,1fr)_minmax(0,110px)_78px_22px]"
                            >
                                <span />
                                <span>{{
                                    t('advisor.recommendation.asset')
                                }}</span>
                                <span class="hidden sm:block">{{
                                    t('advisor.recommendation.role')
                                }}</span>
                                <span class="text-end">{{
                                    t('advisor.recommendation.target')
                                }}</span>
                                <span />
                            </div>

                            <div
                                v-for="row in planRows"
                                :key="row.asset_key"
                                class="border-b border-white/7"
                            >
                                <button
                                    type="button"
                                    class="grid w-full cursor-pointer grid-cols-[12px_minmax(0,1fr)_78px_22px] items-center gap-3.5 py-[15px] text-start transition-colors hover:bg-white/[0.02] focus-visible:ring-2 focus-visible:ring-[#d9c48f] focus-visible:outline-none sm:grid-cols-[12px_minmax(0,1fr)_minmax(0,110px)_78px_22px]"
                                    :aria-expanded="
                                        openRationales.includes(row.asset_key)
                                    "
                                    @click="toggleRationale(row.asset_key)"
                                >
                                    <span
                                        class="size-2 rounded-[2px]"
                                        :style="{ backgroundColor: row.color }"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate text-sm text-white">{{
                                        row.name
                                    }}</span>
                                    <span
                                        class="hidden truncate text-xs text-[#989898] sm:block"
                                        >{{ row.role }}</span
                                    >
                                    <span
                                        class="advisor-mono advisor-figure text-end text-sm"
                                        >{{
                                            row.target_percent.toFixed(1)
                                        }}%</span
                                    >
                                    <span
                                        class="advisor-mono text-end text-[11px] text-[#686868]"
                                        aria-hidden="true"
                                        >{{
                                            openRationales.includes(
                                                row.asset_key,
                                            )
                                                ? '−'
                                                : '+'
                                        }}</span
                                    >
                                </button>
                                <div
                                    v-if="
                                        openRationales.includes(row.asset_key)
                                    "
                                    class="advisor-drift max-w-[62ch] ps-[26px] pb-5"
                                >
                                    <p
                                        class="advisor-mono pb-2 text-[9.5px] tracking-[0.16em] text-[#d9c48f] uppercase"
                                    >
                                        {{ t('advisor.recommendation.why') }}
                                    </p>
                                    <p
                                        class="text-[13.5px] leading-[1.8] text-[#989898]"
                                    >
                                        {{ row.rationale }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-[12px_minmax(0,1fr)_78px_22px] items-center gap-3.5 pt-3.5 sm:grid-cols-[12px_minmax(0,1fr)_minmax(0,110px)_78px_22px]"
                            >
                                <span />
                                <span
                                    class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                                    >{{
                                        t('advisor.recommendation.total')
                                    }}</span
                                >
                                <span class="hidden sm:block" />
                                <span
                                    class="advisor-mono advisor-figure text-end text-sm text-[#d9c48f]"
                                    >{{ planTotal.toFixed(1) }}%</span
                                >
                                <span />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purple, because an overlay is not part of the 100%. It is a
                     different kind of thing and reads as one. -->
                <section
                    v-for="overlay in selectedPlan.options_overlays"
                    :key="overlay.strategy"
                    class="mt-[34px] rounded-[16px] border border-[#947bff]/20 bg-[linear-gradient(120deg,rgba(148,123,255,0.06),#1a1a1a_55%)] px-[26px] py-6"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-4"
                    >
                        <div class="min-w-0">
                            <p
                                class="advisor-mono text-[10px] tracking-[0.2em] text-[#947bff] uppercase"
                            >
                                {{ t('advisor.recommendation.overlay') }}
                            </p>
                            <h2
                                class="advisor-serif mt-3 text-[22px] leading-[1.2] md:text-[24px]"
                            >
                                {{
                                    label('option_strategies', overlay.strategy)
                                }}
                            </h2>
                            <p
                                class="mt-2.5 max-w-[62ch] text-[13.5px] leading-[1.75] text-[#989898]"
                            >
                                {{ overlay.purpose }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-[26px]">
                            <div>
                                <p
                                    class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                                >
                                    {{ t('advisor.recommendation.coverage') }}
                                </p>
                                <p
                                    class="advisor-mono advisor-figure mt-[7px] text-[22px]"
                                >
                                    {{ overlay.coverage_percent }}%
                                </p>
                            </div>
                            <div>
                                <p
                                    class="advisor-mono text-[9.5px] tracking-[0.16em] text-[#686868] uppercase"
                                >
                                    {{
                                        t('advisor.recommendation.risk_budget')
                                    }}
                                </p>
                                <p
                                    class="advisor-mono advisor-figure mt-[7px] text-[22px]"
                                >
                                    {{ overlay.maximum_risk_budget_percent }}%
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- The transition is computed against the primary plan alone —
                     the server prices one move, not three — so it is shown on
                     the tab it actually describes rather than under weights it
                     was never calculated from. -->
                <section
                    v-if="payload.transition_plan && activePlan === 'primary'"
                    class="mt-[34px]"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-3 pb-3.5"
                    >
                        <span
                            class="advisor-mono text-[10px] tracking-[0.2em] text-[#686868] uppercase"
                            >{{ t('advisor.recommendation.transition') }}</span
                        >
                        <span
                            class="advisor-mono advisor-figure text-[9.5px] tracking-[0.1em] text-[#5a5a5a] uppercase"
                            :class="maskClass"
                            >{{ t('advisor.recommendation.base') }}:
                            {{
                                payload.transition_plan.base_currency.toUpperCase()
                            }}<template v-if="combinedCapital">
                                · {{ combinedCapital }}</template
                            ></span
                        >
                    </div>
                    <p
                        v-if="!payload.transition_plan.exact_amounts_available"
                        class="advisor-mono pb-3 text-[9.5px] tracking-[0.1em] text-[#cfc4a6] uppercase"
                    >
                        {{ t('advisor.recommendation.prices_missing') }}
                    </p>
                    <!-- Kept side by side inside a scroller rather than
                         restacked: the columns only mean anything as a row. -->
                    <div class="advisor-rule overflow-x-auto">
                        <table
                            class="w-full min-w-[720px] border-collapse text-start"
                        >
                            <thead>
                                <tr
                                    class="advisor-mono text-[9.5px] tracking-[0.14em] text-[#5a5a5a] uppercase"
                                >
                                    <th
                                        class="border-b border-white/7 py-[11px] text-start font-normal"
                                    >
                                        {{ t('advisor.recommendation.asset') }}
                                    </th>
                                    <th
                                        class="w-[84px] border-b border-white/7 py-[11px] text-end font-normal"
                                    >
                                        {{
                                            t('advisor.recommendation.current')
                                        }}
                                    </th>
                                    <th
                                        class="w-[84px] border-b border-white/7 py-[11px] text-end font-normal"
                                    >
                                        {{ t('advisor.recommendation.target') }}
                                    </th>
                                    <th
                                        class="w-24 border-b border-white/7 py-[11px] text-end font-normal"
                                    >
                                        {{ t('advisor.recommendation.move') }}
                                    </th>
                                    <th
                                        class="border-b border-white/7 py-[11px] text-end font-normal"
                                    >
                                        {{
                                            t(
                                                'advisor.recommendation.difference',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in payload.transition_plan.rows"
                                    :key="row.asset_key"
                                >
                                    <td
                                        class="truncate border-b border-white/7 py-[13px] text-[13.5px] text-white"
                                    >
                                        {{ allocationName(row.asset_key) }}
                                    </td>
                                    <td
                                        class="advisor-mono advisor-figure border-b border-white/7 py-[13px] text-end text-[12.5px] text-[#989898]"
                                    >
                                        {{
                                            row.current_percent === null
                                                ? '—'
                                                : `${row.current_percent.toFixed(1)}%`
                                        }}
                                    </td>
                                    <td
                                        class="advisor-mono advisor-figure border-b border-white/7 py-[13px] text-end text-[12.5px]"
                                    >
                                        {{ row.target_percent.toFixed(1) }}%
                                    </td>
                                    <td
                                        class="advisor-mono advisor-figure border-b border-white/7 py-[13px] text-end text-[12.5px]"
                                        :style="{
                                            color:
                                                (row.percentage_point_difference ??
                                                    0) > 0
                                                    ? '#02cd86'
                                                    : (row.percentage_point_difference ??
                                                            0) < 0
                                                      ? '#e9756f'
                                                      : '#686868',
                                        }"
                                    >
                                        {{
                                            row.percentage_point_difference ===
                                            null
                                                ? '—'
                                                : `${(row.percentage_point_difference ?? 0) > 0 ? '+' : (row.percentage_point_difference ?? 0) < 0 ? '−' : ''}${Math.abs(row.percentage_point_difference).toFixed(1)}pp`
                                        }}
                                    </td>
                                    <td
                                        class="advisor-mono advisor-figure border-b border-white/7 py-[13px] text-end text-[12.5px]"
                                        :style="{
                                            color:
                                                row.difference === null
                                                    ? '#686868'
                                                    : row.difference > 0
                                                      ? '#02cd86'
                                                      : row.difference < 0
                                                        ? '#e9756f'
                                                        : '#686868',
                                        }"
                                    >
                                        <span
                                            v-if="row.difference !== null"
                                            :class="maskClass"
                                            >{{
                                                row.difference > 0
                                                    ? '+'
                                                    : row.difference < 0
                                                      ? '−'
                                                      : ''
                                            }}{{
                                                Math.abs(
                                                    row.difference,
                                                ).toLocaleString()
                                            }}
                                            {{
                                                payload.transition_plan.base_currency.toUpperCase()
                                            }}</span
                                        >
                                        <span v-else class="text-[#686868]">{{
                                            t(
                                                'advisor.recommendation.pricing_required',
                                            )
                                        }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="advisor-rule mt-[34px] grid md:grid-cols-3">
                    <div
                        class="border-b border-white/7 py-[22px] md:border-e md:border-b-0 md:pe-[26px] md:pb-6"
                    >
                        <p
                            class="advisor-mono text-[10px] tracking-[0.18em] text-[#e9944e] uppercase"
                        >
                            {{ t('advisor.recommendation.risks') }}
                        </p>
                        <p
                            v-for="risk in selectedPlan.risks"
                            :key="risk"
                            class="mt-3.5 text-[13px] leading-[1.75] text-[#989898]"
                        >
                            {{ risk }}
                        </p>
                    </div>
                    <div
                        class="border-b border-white/7 py-[22px] md:border-e md:border-b-0 md:px-[26px] md:pb-6"
                    >
                        <p
                            class="advisor-mono text-[10px] tracking-[0.18em] text-[#686868] uppercase"
                        >
                            {{ t('advisor.recommendation.tradeoffs') }}
                        </p>
                        <p
                            v-for="tradeoff in selectedPlan.tradeoffs"
                            :key="tradeoff"
                            class="mt-3.5 text-[13px] leading-[1.75] text-[#989898]"
                        >
                            {{ tradeoff }}
                        </p>
                    </div>
                    <div class="py-[22px] md:ps-[26px] md:pb-6">
                        <p
                            class="advisor-mono text-[10px] tracking-[0.18em] text-[#02cd86] uppercase"
                        >
                            {{ t('advisor.recommendation.change') }}
                        </p>
                        <p
                            v-for="item in selectedPlan.what_would_change_this_plan"
                            :key="item"
                            class="mt-3.5 text-[13px] leading-[1.75] text-[#989898]"
                        >
                            {{ item }}
                        </p>
                    </div>
                </section>

                <button
                    type="button"
                    class="mt-[34px] flex w-full cursor-pointer items-center justify-between gap-[18px] rounded-[16px] border border-white/9 bg-[#1a1a1a] px-6 py-5 text-start transition-colors hover:border-[#d9c48f]/40 hover:bg-[#252525]"
                    @click="
                        openConsultation(
                            t('advisor.recommendation.suggest_fit'),
                        )
                    "
                >
                    <span class="min-w-0">
                        <span
                            class="advisor-serif block text-[21px] text-white"
                            >{{ t('advisor.recommendation.ask') }}</span
                        >
                        <span class="mt-1.5 block text-[13px] text-[#686868]">{{
                            t('advisor.recommendation.suggest_fit')
                        }}</span>
                    </span>
                    <span
                        class="advisor-mono shrink-0 text-[13px] text-[#d9c48f] rtl:rotate-180"
                        aria-hidden="true"
                        >→</span
                    >
                </button>
            </div>
        </template>

        <p
            class="mx-auto mt-11 max-w-[68ch] text-center text-xs leading-[1.75] text-[#5a5a5a]"
        >
            {{ t('advisor.recommendation.model_only') }}
            {{ t('advisor.disclosure') }}
        </p>
    </div>
</template>

<style scoped>
@reference '../../../css/app.css';

/* Focus lifts the whole composer, not just the textarea inside it. */
.advisor-composer:focus-within {
    border-color: rgb(217 196 143 / 45%);
}
</style>
