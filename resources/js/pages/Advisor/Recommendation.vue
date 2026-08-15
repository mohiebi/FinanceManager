<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Bot,
    CheckCircle2,
    HelpCircle,
    LoaderCircle,
    LockKeyhole,
    Send,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useVault } from '@/composables/useVault';
import {
    advisorGenerationErrorKey,
    advisorRecommendationFailureKey,
} from '@/lib/advisor/http-errors';
import { useAdvisorLabels } from '@/lib/advisor/labels';
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

const props = defineProps<{
    recommendation: RecommendationProp;
    profile: {
        persona: string;
        selected_assets: { asset_key: string; name: string }[];
    };
    messages: {
        id: string;
        role: 'user' | 'assistant';
        payload: Encrypted<ConversationPayload>;
        created_at: string;
    }[];
    vaultArmed: boolean;
}>();

const { t } = useI18n();
const { label } = useAdvisorLabels();
const { revealAsync, sealForSubmit, trackKey } = useVault();
const payload = ref<AdvisorRecommendationPayload | null>(null);
const conversation = ref<ConversationMessage[]>([]);
const clarificationAnswers = reactive<Record<string, string | boolean>>({});
const acceptedAssetKeys = ref<string[]>([]);
const actionError = ref('');
const integrityError = ref(false);
const chatMessage = ref('');
const isConsulting = ref(false);
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
 * Watching a queued job rather than holding a request open.
 *
 * Generation takes minutes, so it runs on the queue and this page follows it.
 * Everything below exists to make that wait legible: what stage it is at, how
 * long it has taken, and — for a Vault-armed browser — collecting the result the
 * job could not store on its behalf.
 */
const isGenerating = computed(
    () => props.recommendation.status === 'generating',
);
const awaitsClaim = computed(
    () => props.recommendation.status === 'awaiting_vault_seal',
);
const elapsedSeconds = ref(0);
const claiming = ref(false);
/** Deliberately not reactive — reading it must not re-trigger the effect below. */
const attemptedClaims = new Set<string>();
let pollTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

const payloadClaimer = useHttp<
    Record<string, never>,
    RecommendationResponse
>({});

/**
 * Named from the two counters the job moves as it works, so the stage shown is
 * the stage actually reached — never a timer pretending to be progress.
 */
const generationStage = computed(() => {
    if (props.recommendation.provider_calls === 0) {
        return t('advisor.recommendation.stage_reading');
    }

    return props.recommendation.repair_attempts > 0
        ? t('advisor.recommendation.stage_checking')
        : t('advisor.recommendation.stage_designing');
});

const elapsedLabel = computed(() => {
    const minutes = Math.floor(elapsedSeconds.value / 60);
    const seconds = elapsedSeconds.value % 60;

    return minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
});

function tickClock(): void {
    elapsedSeconds.value = Math.max(
        0,
        Math.round(
            (Date.now() - new Date(props.recommendation.created_at).getTime()) /
                1000,
        ),
    );
}

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

    const shouldTick = shouldPoll || awaitsClaim.value;

    if (shouldTick && clockTimer === undefined) {
        tickClock();
        clockTimer = setInterval(tickClock, 1000);
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

/**
 * Openers offered when the thread is empty.
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

    if (!text || !payload.value || isConsulting.value) {
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
    } catch {
        // Marked rather than removed: the question stays on screen with a way to
        // send it again, instead of becoming a page-level error and lost text.
        pending.failed = true;
    } finally {
        isConsulting.value = false;
    }
}

function allocationName(assetKey: string): string {
    return assetNames.value[assetKey] ?? assetKey;
}

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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Advisor', href: '/advisor' },
            { title: 'Recommendation', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="t('advisor.recommendation.primary')" />

    <div
        class="min-h-[calc(100vh-92px)] bg-[#0d0f0f] px-[18px] py-5 text-white"
    >
        <header
            class="mx-auto max-w-6xl rounded-[24px] border border-white/10 bg-[#171a19] p-6 md:flex md:items-center md:justify-between md:gap-8"
        >
            <div>
                <p
                    class="text-[11px] font-semibold tracking-[0.28em] text-[#02CD86] uppercase"
                >
                    {{
                        props.recommendation.mode === 'target_only'
                            ? t('advisor.target_only')
                            : t('advisor.rebalance')
                    }}
                </p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                    {{ label('personas', props.profile.persona) }}
                </h1>
                <p class="mt-2 text-sm text-white/40">
                    {{ t('advisor.recommendation.model_only') }}
                </p>
            </div>
            <div
                class="mt-4 flex items-center gap-2 rounded-full border px-4 py-2 text-xs md:mt-0"
                :class="
                    isGuidance
                        ? 'border-amber-300/20 bg-amber-300/8 text-amber-100'
                        : 'border-[#02CD86]/20 bg-[#02CD86]/8 text-[#8ff0cd]'
                "
            >
                <AlertTriangle v-if="isGuidance" class="size-4" />
                <ShieldCheck v-else class="size-4" />
                {{
                    isGuidance
                        ? t('advisor.recommendation.guidance_badge')
                        : t('advisor.recommendation.validated_badge')
                }}
            </div>
        </header>

        <p
            v-if="integrityError"
            class="mx-auto mt-4 max-w-6xl rounded-xl bg-red-400/10 px-4 py-3 text-sm text-red-200"
        >
            <AlertTriangle class="me-2 inline size-4" />{{
                t('advisor.recommendation.integrity_failed')
            }}
        </p>
        <p
            v-if="actionError"
            class="mx-auto mt-4 max-w-6xl rounded-xl bg-red-400/10 px-4 py-3 text-sm text-red-200"
            role="alert"
        >
            {{ actionError }}
        </p>

        <!-- The wait is minutes long, so it has to look like work rather than a
             hang: the stage is read from what the job has actually reached, and
             the reassurance about leaving is now true. -->
        <section
            v-if="isGenerating || (awaitsClaim && props.vaultArmed)"
            class="mx-auto mt-[18px] grid min-h-72 max-w-6xl place-items-center rounded-[24px] border border-white/10 bg-[#171a19] px-6 py-10"
            role="status"
            aria-live="polite"
        >
            <div class="w-full max-w-md text-center">
                <LoaderCircle
                    aria-hidden="true"
                    class="mx-auto size-6 animate-spin text-[#a78bfa] motion-reduce:animate-none"
                />
                <p class="mt-4 text-base font-medium text-white">
                    {{ t('advisor.recommendation.generating') }}
                </p>
                <p class="mt-1 text-sm text-white/45">
                    {{
                        awaitsClaim
                            ? t('advisor.recommendation.stage_sealing')
                            : generationStage
                    }}
                </p>

                <!-- Elapsed time rather than a progress bar: nothing here knows
                     how long the provider will take, and a bar that drifts
                     without knowing is just a spinner that lies. -->
                <p
                    class="mt-6 font-mono text-xs tracking-wide text-white/30"
                    dir="ltr"
                >
                    {{ elapsedLabel }}
                </p>
                <p class="mt-5 text-xs leading-5 text-white/40">
                    {{ t('advisor.recommendation.leave_safe') }}
                </p>
            </div>
        </section>

        <section
            v-else-if="!payload && props.recommendation.status !== 'failed'"
            class="mx-auto mt-[18px] grid min-h-72 max-w-6xl place-items-center rounded-[24px] border border-white/10 bg-[#171a19] text-sm text-white/40"
        >
            <div class="text-center">
                <Sparkles class="mx-auto size-6 animate-pulse text-[#a78bfa]" />
                <p class="mt-3">
                    {{ t('advisor.recommendation.locked') }}
                </p>
            </div>
        </section>

        <section
            v-else-if="props.recommendation.status === 'failed'"
            class="mx-auto mt-[18px] max-w-6xl rounded-[24px] border border-red-400/15 bg-[#171a19] p-8 text-center"
        >
            <AlertTriangle class="mx-auto size-7 text-red-300" />
            <h2 class="mt-4 text-lg font-semibold">
                {{ t('advisor.recommendation.failed') }}
            </h2>
            <p class="mt-2 text-sm text-white/40">
                {{
                    t(
                        advisorRecommendationFailureKey(
                            props.recommendation.failure_code ?? undefined,
                        ),
                    )
                }}
            </p>
        </section>

        <section
            v-else-if="isGuidance && payload"
            class="mx-auto mt-[18px] max-w-4xl rounded-[24px] border border-amber-300/20 bg-[linear-gradient(135deg,rgba(245,158,11,0.1),rgba(23,26,25,1)_48%)] p-6 md:p-8"
            role="status"
        >
            <div class="flex items-start gap-4">
                <span
                    class="grid size-11 shrink-0 place-items-center rounded-2xl border border-amber-300/20 bg-amber-300/10 text-amber-200"
                >
                    <AlertTriangle class="size-5" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-[11px] font-semibold tracking-[0.24em] text-amber-200/75 uppercase"
                    >
                        {{ t('advisor.recommendation.guidance_badge') }}
                    </p>
                    <h2 class="mt-2 text-xl leading-tight font-semibold">
                        {{
                            payload.summary ??
                            t('advisor.recommendation.guidance_title')
                        }}
                    </h2>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-white/55">
                        {{
                            payload.fit_warning ??
                            payload.cannot_recommend_reason ??
                            t('advisor.recommendation.guidance_body')
                        }}
                    </p>
                </div>
            </div>

            <div class="mt-7 rounded-2xl border border-white/8 bg-black/15 p-5">
                <h3 class="text-sm font-semibold">
                    {{ t('advisor.recommendation.next_steps') }}
                </h3>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-white/55">
                    <li
                        v-for="step in payload.next_steps ?? []"
                        :key="step"
                        class="flex gap-3"
                    >
                        <CheckCircle2
                            class="mt-1 size-4 shrink-0 text-amber-200/80"
                        />
                        <span>{{ step }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <section
            v-else-if="payload?.status === 'needs_clarification'"
            class="mx-auto mt-[18px] max-w-3xl rounded-[24px] border border-[#a78bfa]/20 bg-[#171a19] p-6 md:p-8"
        >
            <HelpCircle class="size-6 text-[#a78bfa]" />
            <h2 class="mt-4 text-xl font-semibold">
                {{ t('advisor.recommendation.clarification') }}
            </h2>
            <div class="mt-6 space-y-5">
                <label
                    v-for="question in payload.questions"
                    :key="question.key"
                    class="block"
                    ><span class="text-sm font-medium">{{
                        question.question
                    }}</span
                    ><small class="mt-1 block text-white/35">{{
                        question.reason
                    }}</small>
                    <select
                        v-if="question.input_type === 'single_choice'"
                        v-model="clarificationAnswers[question.key]"
                        class="mt-3 h-11 w-full rounded-xl border border-white/10 bg-[#222625] px-3 text-sm"
                    >
                        <option
                            v-for="option in question.options"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </option>
                    </select>
                    <div
                        v-else-if="question.input_type === 'boolean'"
                        class="mt-3 flex gap-2"
                    >
                        <button
                            v-for="choice in [true, false]"
                            :key="String(choice)"
                            type="button"
                            class="cursor-pointer rounded-full border px-4 py-2 text-sm"
                            :class="
                                clarificationAnswers[question.key] === choice
                                    ? 'border-[#02CD86]/50 bg-[#02CD86]/10'
                                    : 'border-white/10'
                            "
                            @click="clarificationAnswers[question.key] = choice"
                        >
                            {{ choice ? 'Yes' : 'No' }}
                        </button>
                    </div>
                    <input
                        v-else
                        v-model="clarificationAnswers[question.key] as string"
                        maxlength="500"
                        class="mt-3 h-11 w-full rounded-xl border border-white/10 bg-[#222625] px-3 text-sm outline-none focus:border-[#02CD86]/50"
                    />
                </label>
            </div>
            <div
                v-if="payload.suggested_additional_assets.length"
                class="mt-7 border-t border-white/8 pt-5"
            >
                <p class="text-sm font-medium">Allow a missing diversifier?</p>
                <button
                    v-for="asset in payload.suggested_additional_assets"
                    :key="asset.key"
                    type="button"
                    class="mt-3 flex w-full cursor-pointer items-start gap-3 rounded-2xl border p-4 text-start"
                    :class="
                        acceptedAssetKeys.includes(asset.key)
                            ? 'border-[#02CD86]/45 bg-[#02CD86]/8'
                            : 'border-white/10'
                    "
                    @click="toggleAcceptedAsset(asset.key)"
                >
                    <span
                        class="mt-0.5 grid size-5 shrink-0 place-items-center rounded border"
                        :class="
                            acceptedAssetKeys.includes(asset.key)
                                ? 'border-[#02CD86] bg-[#02CD86] text-black'
                                : 'border-white/20'
                        "
                        ><CheckCircle2
                            v-if="acceptedAssetKeys.includes(asset.key)"
                            class="size-3" /></span
                    ><span
                        ><strong class="text-sm">{{ asset.name }}</strong
                        ><small class="mt-1 block text-white/35">{{
                            asset.reason
                        }}</small></span
                    >
                </button>
            </div>
            <Button
                class="mt-7 rounded-full bg-[#02CD86] px-6 font-semibold text-[#07130f] hover:bg-[#19d897]"
                :disabled="clarificationForm.processing"
                @click="submitClarifications"
                >{{ t('advisor.recommendation.submit_answers') }}</Button
            >
        </section>

        <template
            v-else-if="
                payload?.status === 'recommendation_ready' && payload.primary
            "
        >
            <section
                v-if="payload.fit_status === 'closest_fit'"
                class="mx-auto mt-[18px] max-w-6xl rounded-[22px] border border-amber-300/20 bg-amber-300/[0.07] p-5"
                role="status"
            >
                <div class="flex items-start gap-3">
                    <AlertTriangle
                        class="mt-0.5 size-5 shrink-0 text-amber-200"
                    />
                    <div>
                        <h2 class="font-semibold text-amber-50">
                            {{ t('advisor.recommendation.closest_fit_title') }}
                        </h2>
                        <p
                            class="mt-2 max-w-4xl text-sm leading-6 text-amber-50/65"
                        >
                            {{
                                payload.fit_warning ??
                                t('advisor.recommendation.closest_fit_body')
                            }}
                        </p>
                        <ul
                            v-if="payload.next_steps?.length"
                            class="mt-3 grid gap-2 text-xs leading-5 text-amber-50/55 md:grid-cols-2"
                        >
                            <li
                                v-for="step in payload.next_steps"
                                :key="step"
                                class="flex gap-2"
                            >
                                <CheckCircle2
                                    class="mt-0.5 size-3.5 shrink-0"
                                />
                                <span>{{ step }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <p
                v-if="hasOmittedAlternative"
                class="mx-auto mt-[18px] max-w-6xl rounded-xl border border-white/8 bg-white/[0.035] px-4 py-3 text-sm leading-6 text-white/55"
                role="status"
            >
                <ShieldCheck class="me-2 inline size-4 text-[#02CD86]" />
                {{ t('advisor.recommendation.alternative_omitted') }}
            </p>

            <section
                class="mx-auto mt-[18px] max-w-6xl rounded-[26px] border border-white/10 bg-[#171a19] p-6 md:p-8"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.25em] text-[#02CD86] uppercase"
                >
                    {{ t('advisor.recommendation.primary') }}
                </p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                    {{ payload.primary.name }}
                </h2>
                <p class="mt-3 max-w-4xl text-sm leading-6 text-white/48">
                    {{ payload.summary }}
                </p>
                <div class="mt-7 grid gap-8 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="space-y-4">
                        <div
                            v-for="allocation in payload.primary.allocations"
                            :key="allocation.asset_key"
                        >
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-medium">
                                        {{
                                            allocationName(allocation.asset_key)
                                        }}
                                    </h3>
                                    <p class="mt-1 text-xs text-white/35">
                                        {{ allocation.role }}
                                    </p>
                                </div>
                                <strong class="text-xl text-[#02CD86]"
                                    >{{ allocation.target_percent }}%</strong
                                >
                            </div>
                            <div
                                class="mt-2 h-2 overflow-hidden rounded-full bg-white/8"
                            >
                                <div
                                    class="h-full rounded-full bg-[#02CD86]"
                                    :style="{
                                        width: `${allocation.target_percent}%`,
                                    }"
                                />
                            </div>
                            <p class="mt-2 text-xs leading-5 text-white/36">
                                {{ allocation.rationale }}
                            </p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <article
                            v-for="overlay in payload.primary.options_overlays"
                            :key="overlay.strategy"
                            class="rounded-2xl border border-[#a78bfa]/18 bg-[#a78bfa]/7 p-4"
                        >
                            <p
                                class="text-xs tracking-wide text-[#c4b5fd] uppercase"
                            >
                                {{ t('advisor.recommendation.overlay') }}
                            </p>
                            <h3 class="mt-2 font-semibold">
                                {{ label('option_strategies', overlay.strategy) }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-white/45">
                                {{ overlay.purpose }}
                            </p>
                            <div class="mt-3 flex gap-4 text-xs text-white/50">
                                <span
                                    >Coverage
                                    {{ overlay.coverage_percent }}%</span
                                ><span
                                    >Risk budget
                                    {{
                                        overlay.maximum_risk_budget_percent
                                    }}%</span
                                >
                            </div>
                        </article>
                        <div
                            class="rounded-2xl border border-white/8 bg-black/15 p-4"
                        >
                            <h3 class="text-sm font-semibold">
                                {{ t('advisor.recommendation.risks') }}
                            </h3>
                            <ul
                                class="mt-3 space-y-2 text-xs leading-5 text-white/42"
                            >
                                <li
                                    v-for="risk in payload.primary.risks"
                                    :key="risk"
                                >
                                    • {{ risk }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="payload.transition_plan"
                class="mx-auto mt-[18px] max-w-6xl rounded-[24px] border border-white/10 bg-[#171a19] p-6"
            >
                <h2 class="font-semibold">
                    {{ t('advisor.recommendation.transition') }}
                </h2>
                <p
                    v-if="!payload.transition_plan.exact_amounts_available"
                    class="mt-2 text-xs text-amber-200/60"
                >
                    Some assets have no current price, so exact amounts are
                    unavailable.
                </p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[650px] text-sm">
                        <thead class="text-start text-xs text-white/30">
                            <tr>
                                <th class="py-3 text-start">Asset</th>
                                <th class="py-3 text-end">Current</th>
                                <th class="py-3 text-end">Target</th>
                                <th class="py-3 text-end">Difference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8">
                            <tr
                                v-for="row in payload.transition_plan.rows"
                                :key="row.asset_key"
                            >
                                <td class="py-4 font-medium">
                                    {{ allocationName(row.asset_key) }}
                                </td>
                                <td class="py-4 text-end text-white/50">
                                    {{
                                        row.current_percent === null
                                            ? '—'
                                            : `${row.current_percent}%`
                                    }}
                                </td>
                                <td class="py-4 text-end">
                                    {{ row.target_percent }}%
                                </td>
                                <td
                                    class="py-4 text-end"
                                    :class="
                                        row.difference === null
                                            ? 'text-white/35'
                                            : row.difference >= 0
                                              ? 'text-[#02CD86]'
                                              : 'text-amber-300'
                                    "
                                >
                                    <template v-if="row.difference !== null"
                                        ><ArrowUp
                                            v-if="row.difference >= 0"
                                            class="me-1 inline size-3"
                                        /><ArrowDown
                                            v-else
                                            class="me-1 inline size-3"
                                        />{{
                                            Math.abs(
                                                row.difference,
                                            ).toLocaleString()
                                        }}
                                        {{
                                            payload.transition_plan.base_currency.toUpperCase()
                                        }}</template
                                    ><span v-else>Pricing required</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="mx-auto mt-[18px] grid max-w-6xl gap-[18px] lg:grid-cols-2"
            >
                <article
                    v-for="alternative in [
                        payload.safer_alternative,
                        payload.higher_risk_alternative?.available
                            ? payload.higher_risk_alternative
                            : null,
                    ].filter(Boolean) as PortfolioPlan[]"
                    :key="alternative.name"
                    class="rounded-[22px] border border-white/10 bg-[#171a19] p-6"
                >
                    <p class="text-xs text-white/35">
                        {{
                            alternative === payload.safer_alternative
                                ? t('advisor.recommendation.safer')
                                : t('advisor.recommendation.higher')
                        }}
                    </p>
                    <h2 class="mt-2 text-lg font-semibold">
                        {{ alternative.name }}
                    </h2>
                    <div
                        class="mt-5 flex h-3 overflow-hidden rounded-full bg-white/8"
                    >
                        <span
                            v-for="(
                                allocation, index
                            ) in alternative.allocations"
                            :key="allocation.asset_key"
                            class="h-full"
                            :class="
                                [
                                    'bg-[#02CD86]',
                                    'bg-[#60a5fa]',
                                    'bg-[#a78bfa]',
                                    'bg-[#f59e0b]',
                                    'bg-[#f87171]',
                                ][index % 5]
                            "
                            :style="{ width: `${allocation.target_percent}%` }"
                        />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="allocation in alternative.allocations"
                            :key="allocation.asset_key"
                            class="rounded-full bg-white/5 px-3 py-1.5 text-xs text-white/55"
                            >{{ allocationName(allocation.asset_key) }}
                            {{ allocation.target_percent }}%</span
                        >
                    </div>
                </article>
            </section>

            <section
                class="mx-auto mt-[18px] max-w-6xl rounded-[24px] border border-[#a78bfa]/18 bg-[#171a19] p-6"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <Bot class="size-5 text-[#a78bfa]" />{{
                        t('advisor.recommendation.ask')
                    }}
                </h2>
                <div
                    class="mt-5 max-h-96 space-y-3 overflow-y-auto"
                    :aria-busy="isConsulting"
                >
                    <!-- Suggested openers stand in for an empty state. These
                         were previously a placeholder nobody could click. -->
                    <div
                        v-if="conversation.length === 0 && !isConsulting"
                        class="space-y-3"
                    >
                        <p class="text-sm text-white/40">
                            {{ t('advisor.recommendation.ask_empty') }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="suggestion in chatSuggestions"
                                :key="suggestion"
                                type="button"
                                class="cursor-pointer rounded-full border border-[#a78bfa]/25 bg-[#a78bfa]/[0.07] px-4 py-2 text-start text-xs text-white/70 transition hover:border-[#a78bfa]/50 hover:text-white focus-visible:ring-2 focus-visible:ring-[#a78bfa] focus-visible:outline-none"
                                @click="useSuggestion(suggestion)"
                            >
                                {{ suggestion }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-for="(message, index) in conversation"
                        :key="message.id ?? index"
                        class="max-w-[88%]"
                        :class="message.role === 'user' ? 'ms-auto' : ''"
                    >
                        <div
                            class="rounded-2xl px-4 py-3 text-sm leading-6 whitespace-pre-wrap"
                            :class="
                                message.role === 'user'
                                    ? message.failed
                                        ? 'bg-[#02CD86]/25 text-white/70'
                                        : 'bg-[#02CD86] text-[#07130f]'
                                    : 'border border-white/8 bg-white/[0.035] text-white/65'
                            "
                        >
                            {{
                                message.payload.content ??
                                message.payload.answer
                            }}
                        </div>
                        <p
                            v-if="message.failed"
                            class="mt-1.5 flex items-center justify-end gap-2 text-xs text-red-300"
                        >
                            {{ t('advisor.recommendation.send_failed') }}
                            <button
                                type="button"
                                class="cursor-pointer rounded-full px-2 py-0.5 font-medium text-[#a78bfa] underline-offset-2 transition hover:underline focus-visible:ring-2 focus-visible:ring-[#a78bfa] focus-visible:outline-none"
                                :disabled="isConsulting"
                                @click="retryMessage(index)"
                            >
                                {{ t('advisor.recommendation.retry') }}
                            </button>
                        </p>
                    </div>
                    <div
                        v-if="isConsulting"
                        role="status"
                        aria-live="polite"
                        class="flex max-w-[88%] items-center gap-3 rounded-2xl border border-[#a78bfa]/20 bg-[#a78bfa]/[0.06] px-4 py-3 text-sm text-white/65"
                    >
                        <LoaderCircle
                            aria-hidden="true"
                            class="size-4 shrink-0 animate-spin text-[#a78bfa] motion-reduce:animate-none"
                        />
                        <span>{{
                            t('advisor.recommendation.consulting')
                        }}</span>
                    </div>
                </div>
                <!-- A textarea rather than a one-line field: 1,500 characters
                     of question used to scroll out of sight as it was typed. -->
                <form
                    class="mt-4 flex items-end gap-2"
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
                        class="max-h-40 min-h-12 min-w-0 flex-1 resize-none rounded-3xl border border-white/10 bg-[#222625] px-5 py-3.5 text-sm leading-6 outline-none placeholder:text-white/25 focus:border-[#a78bfa]/50"
                        :disabled="isConsulting"
                        :placeholder="
                            t('advisor.recommendation.ask_placeholder')
                        "
                        @input="growTextarea"
                        @keydown="onChatKeydown"
                    /><Button
                        type="submit"
                        class="size-12 shrink-0 rounded-full bg-[#a78bfa] p-0 text-[#140c25] hover:bg-[#b99dfd]"
                        :disabled="isConsulting || !chatMessage.trim()"
                        ><LoaderCircle
                            v-if="isConsulting"
                            aria-hidden="true"
                            class="size-4 animate-spin motion-reduce:animate-none"
                        /><Send v-else class="size-4" /><span class="sr-only">{{
                            isConsulting
                                ? t('advisor.recommendation.consulting')
                                : t('advisor.recommendation.send')
                        }}</span></Button
                    >
                </form>
                <p class="mt-2 text-xs text-white/25">
                    {{ t('advisor.recommendation.send_hint') }}
                </p>
                <p
                    v-if="props.vaultArmed"
                    class="mt-3 flex items-center gap-2 text-xs text-white/30"
                >
                    <LockKeyhole class="size-3.5 text-[#60a5fa]" />{{
                        t('advisor.recommendation.chat_encrypted')
                    }}
                </p>
            </section>
        </template>

        <p
            class="mx-auto mt-5 max-w-3xl text-center text-xs leading-5 text-white/28"
        >
            {{ t('advisor.disclosure') }}
        </p>
    </div>
</template>
