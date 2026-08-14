<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Bot,
    CheckCircle2,
    HelpCircle,
    LockKeyhole,
    Send,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed, reactive, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useVault } from '@/composables/useVault';
import { clarify, consult, seal } from '@/routes/advisor/recommendations';
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
const { revealAsync, sealForSubmit, trackKey } = useVault();
const payload = ref<AdvisorRecommendationPayload | null>(null);
const conversation = ref<ConversationMessage[]>([]);
const clarificationAnswers = reactive<Record<string, string | boolean>>({});
const acceptedAssetKeys = ref<string[]>([]);
const actionError = ref('');
const integrityError = ref(false);
const chatMessage = ref('');

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
        const response = await clarificationForm.post(
            clarify(props.recommendation.id).url,
        );

        if (!response.payload || response.status === 'failed') {
            actionError.value = t('advisor.recommendation.failed');

            return;
        }

        await sealGeneratedResponse(response);
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

async function sendMessage(): Promise<void> {
    const text = chatMessage.value.trim();

    if (!text || !payload.value) {
        return;
    }

    actionError.value = '';
    consultationForm.message = text;
    consultationForm.recommendation_context = props.vaultArmed
        ? payload.value
        : null;
    consultationForm.history = conversation.value.map((message) => ({
        role: message.role,
        content: message.payload.content ?? message.payload.answer ?? '',
    }));

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
        conversation.value.push(
            { role: 'user', payload: { content: text } },
            { role: 'assistant', payload: response.payload },
        );
        chatMessage.value = '';

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
    } catch (error) {
        actionError.value =
            error instanceof Error
                ? error.message
                : t('advisor.validation.provider_failure');
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
                    {{ props.profile.persona.replaceAll('_', ' ') }}
                </h1>
                <p class="mt-2 text-sm text-white/40">
                    {{ t('advisor.recommendation.model_only') }}
                </p>
            </div>
            <div
                class="mt-4 flex items-center gap-2 rounded-full border border-[#02CD86]/20 bg-[#02CD86]/8 px-4 py-2 text-xs text-[#8ff0cd] md:mt-0"
            >
                <ShieldCheck class="size-4" />CashPilot validated
            </div>
        </header>

        <p
            v-if="integrityError"
            class="mx-auto mt-4 max-w-6xl rounded-xl bg-red-400/10 px-4 py-3 text-sm text-red-200"
        >
            <AlertTriangle class="me-2 inline size-4" />The decrypted
            recommendation failed its integrity check.
        </p>
        <p
            v-if="actionError"
            class="mx-auto mt-4 max-w-6xl rounded-xl bg-red-400/10 px-4 py-3 text-sm text-red-200"
            role="alert"
        >
            {{ actionError }}
        </p>

        <section
            v-if="!payload && props.recommendation.status !== 'failed'"
            class="mx-auto mt-[18px] grid min-h-72 max-w-6xl place-items-center rounded-[24px] border border-white/10 bg-[#171a19] text-sm text-white/40"
        >
            <div class="text-center">
                <Sparkles class="mx-auto size-6 animate-pulse text-[#a78bfa]" />
                <p class="mt-3">{{ t('advisor.recommendation.generating') }}</p>
                <p v-if="props.vaultArmed" class="mt-1 text-xs">
                    Unlock your Vault to view the encrypted result.
                </p>
            </div>
        </section>

        <section
            v-else-if="
                props.recommendation.status === 'failed' ||
                payload?.status === 'cannot_recommend'
            "
            class="mx-auto mt-[18px] max-w-6xl rounded-[24px] border border-red-400/15 bg-[#171a19] p-8 text-center"
        >
            <AlertTriangle class="mx-auto size-7 text-red-300" />
            <h2 class="mt-4 text-lg font-semibold">
                {{ t('advisor.recommendation.failed') }}
            </h2>
            <p class="mt-2 text-sm text-white/40">
                {{
                    payload?.cannot_recommend_reason ??
                    props.recommendation.failure_code
                }}
            </p>
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
                                {{ overlay.strategy.replaceAll('_', ' ') }}
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
                <div class="mt-5 max-h-96 space-y-3 overflow-y-auto">
                    <div
                        v-for="(message, index) in conversation"
                        :key="message.id ?? index"
                        class="max-w-[88%] rounded-2xl px-4 py-3 text-sm leading-6"
                        :class="
                            message.role === 'user'
                                ? 'ms-auto bg-[#02CD86] text-[#07130f]'
                                : 'border border-white/8 bg-white/[0.035] text-white/65'
                        "
                    >
                        {{ message.payload.content ?? message.payload.answer }}
                    </div>
                </div>
                <form class="mt-4 flex gap-2" @submit.prevent="sendMessage">
                    <input
                        v-model="chatMessage"
                        maxlength="1500"
                        class="h-12 min-w-0 flex-1 rounded-full border border-white/10 bg-[#222625] px-5 text-sm outline-none placeholder:text-white/25 focus:border-[#a78bfa]/50"
                        :placeholder="
                            t('advisor.recommendation.ask_placeholder')
                        "
                    /><Button
                        type="submit"
                        class="size-12 rounded-full bg-[#a78bfa] p-0 text-[#140c25] hover:bg-[#b99dfd]"
                        :disabled="consultationForm.processing"
                        ><Send class="size-4" /><span class="sr-only">{{
                            t('advisor.recommendation.send')
                        }}</span></Button
                    >
                </form>
                <p
                    v-if="props.vaultArmed"
                    class="mt-3 flex items-center gap-2 text-xs text-white/30"
                >
                    <LockKeyhole class="size-3.5 text-[#60a5fa]" />Consultation
                    history is encrypted in your browser.
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
