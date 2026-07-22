<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bot, Check, Copy, Unlink } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import { destroy as destroyConnection, edit } from '@/routes/ai-connections';

type Connection = {
    id: string;
    client_name: string;
    created_at: string | null;
    expires_at: string | null;
};

type HistoryEntry = {
    id: string;
    client_name: string | null;
    action: string;
    resource_type: string;
    status: 'pending' | 'confirmed' | 'rejected' | 'expired';
    diff: Record<string, unknown>;
    created_at: string;
};

const props = defineProps<{
    connections: Connection[];
    history: HistoryEntry[];
    mcpUrl: string;
}>();

const { t, locale } = useI18n();

const revokeTarget = ref<Connection | null>(null);
const copiedKey = ref<string | null>(null);

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value === 'fa' ? 'fa-IR' : locale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

function formatDate(value: string | null): string {
    return value ? dateFormatter.value.format(new Date(value)) : '—';
}

function copyText(text: string, key: string): void {
    navigator.clipboard.writeText(text).then(() => {
        copiedKey.value = key;
        setTimeout(() => {
            if (copiedKey.value === key) {
                copiedKey.value = null;
            }
        }, 2000);
    });
}

type ClientKey = 'claude' | 'codex' | 'cursor' | 'other';

type SetupStep = {
    text?: string;
    code?: string;
    link?: { href: string; label: string };
};

type SetupSection = {
    title?: string;
    steps: SetupStep[];
};

const selectedClient = ref<ClientKey | null>(null);

const clientOptions = computed<{ key: ClientKey; label: string }[]>(() => [
    { key: 'claude', label: t('settings.ai.connect.clients.claude') },
    { key: 'codex', label: t('settings.ai.connect.clients.codex') },
    { key: 'cursor', label: t('settings.ai.connect.clients.cursor') },
    { key: 'other', label: t('settings.ai.connect.clients.other') },
]);

const cursorDeepLink = computed(() => {
    const config = btoa(JSON.stringify({ url: props.mcpUrl }));

    return `cursor://anysphere.cursor-deeplink/mcp/install?name=cashpilot&config=${config}`;
});

const clientSections = computed<Record<ClientKey, SetupSection[]>>(() => ({
    claude: [
        {
            title: t('settings.ai.connect.claude.app_title'),
            steps: [
                { text: t('settings.ai.connect.claude.app_step1') },
                {
                    text: t('settings.ai.connect.claude.app_step2'),
                    code: props.mcpUrl,
                },
                { text: t('settings.ai.connect.claude.app_step3') },
            ],
        },
        {
            title: t('settings.ai.connect.claude.code_title'),
            steps: [
                {
                    text: t('settings.ai.connect.claude.code_step1'),
                    code: `claude mcp add --transport http cashpilot ${props.mcpUrl}`,
                },
                { text: t('settings.ai.connect.claude.code_step2') },
            ],
        },
    ],
    codex: [
        {
            title: t('settings.ai.connect.codex.app_title'),
            steps: [
                {
                    text: t('settings.ai.connect.codex.app_step1'),
                    code: props.mcpUrl,
                },
            ],
        },
        {
            title: t('settings.ai.connect.codex.cli_title'),
            steps: [
                {
                    text: t('settings.ai.connect.codex.cli_step1'),
                    code: `[mcp_servers.cashpilot]\nurl = "${props.mcpUrl}"`,
                },
                { text: t('settings.ai.connect.codex.cli_step2') },
            ],
        },
    ],
    cursor: [
        {
            steps: [
                {
                    text: t('settings.ai.connect.cursor.step1'),
                    link: {
                        href: cursorDeepLink.value,
                        label: t('settings.ai.connect.cursor.button'),
                    },
                },
                {
                    text: t('settings.ai.connect.cursor.step2'),
                    code: `{\n    "mcpServers": {\n        "cashpilot": { "url": "${props.mcpUrl}" }\n    }\n}`,
                },
                { text: t('settings.ai.connect.cursor.step3') },
            ],
        },
    ],
    other: [
        {
            steps: [
                {
                    text: t('settings.ai.connect.other.step1'),
                    code: props.mcpUrl,
                },
                { text: t('settings.ai.connect.other.step2') },
            ],
        },
    ],
}));

function confirmRevoke(): void {
    if (!revokeTarget.value) {
        return;
    }

    router.delete(destroyConnection.url(revokeTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            revokeTarget.value = null;
        },
    });
}

const statusClasses: Record<HistoryEntry['status'], string> = {
    pending: 'bg-[#F59E0B]/10 text-[#F59E0B]',
    confirmed: 'bg-[#02CD86]/10 text-[#02CD86]',
    rejected: 'bg-[#E94E50]/10 text-[#E94E50]',
    expired: 'bg-white/10 text-[#989898]',
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'AI connections', href: edit() }],
    },
});
</script>

<template>
    <Head :title="t('settings.ai.title')" />

    <h1 class="sr-only">{{ t('settings.ai.title') }}</h1>

    <div class="space-y-8">
        <!-- Header -->
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
            >
                {{ t('settings.ai.eyebrow') }}
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                {{ t('settings.ai.description') }}
            </p>
        </div>

        <!-- Connect your assistant -->
        <div class="rounded-[22px] bg-[#252525] p-5 ring-1 ring-white/10">
            <p class="font-medium text-white">
                {{ t('settings.ai.connect.title') }}
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                {{ t('settings.ai.connect.subtitle') }}
            </p>

            <!-- Client picker -->
            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="option in clientOptions"
                    :key="option.key"
                    type="button"
                    class="cursor-pointer rounded-xl px-4 py-2.5 text-sm font-medium transition"
                    :class="
                        selectedClient === option.key
                            ? 'bg-[#02CD86]/10 text-[#02CD86] ring-1 ring-[#02CD86]/30'
                            : 'bg-[#101010] text-[#989898] ring-1 ring-white/10 hover:bg-white/5 hover:text-white'
                    "
                    @click="
                        selectedClient =
                            selectedClient === option.key ? null : option.key
                    "
                >
                    {{ option.label }}
                </button>
            </div>

            <!-- Setup steps for the selected client -->
            <div
                v-if="selectedClient"
                class="mt-4 space-y-5 rounded-xl bg-[#101010] p-4 ring-1 ring-white/5"
            >
                <div
                    v-for="(section, sectionIndex) in clientSections[
                        selectedClient
                    ]"
                    :key="sectionIndex"
                    class="space-y-3"
                >
                    <p
                        v-if="section.title"
                        class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                    >
                        {{ section.title }}
                    </p>

                    <ol class="space-y-3">
                        <li
                            v-for="(step, stepIndex) in section.steps"
                            :key="stepIndex"
                            class="flex gap-3"
                        >
                            <span
                                class="grid size-5 shrink-0 place-items-center rounded-full bg-[#02CD86]/10 text-[11px] font-semibold text-[#02CD86]"
                            >
                                {{ stepIndex + 1 }}
                            </span>
                            <div class="min-w-0 flex-1 space-y-2">
                                <p
                                    v-if="step.text"
                                    class="text-sm text-[#989898]"
                                >
                                    {{ step.text }}
                                </p>
                                <div
                                    v-if="step.code"
                                    class="flex items-start gap-2"
                                >
                                    <pre
                                        class="min-w-0 flex-1 overflow-x-auto rounded-xl bg-[#252525] px-4 py-2.5 font-mono text-xs leading-relaxed whitespace-pre text-[#02CD86]"
                                        dir="ltr"
                                        >{{ step.code }}</pre
                                    >
                                    <button
                                        type="button"
                                        class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-xl bg-white/10 text-white transition hover:bg-white/15"
                                        @click="
                                            copyText(
                                                step.code,
                                                `${selectedClient}-${sectionIndex}-${stepIndex}`,
                                            )
                                        "
                                    >
                                        <Check
                                            v-if="
                                                copiedKey ===
                                                `${selectedClient}-${sectionIndex}-${stepIndex}`
                                            "
                                            class="size-4 text-[#02CD86]"
                                        />
                                        <Copy v-else class="size-4" />
                                    </button>
                                </div>
                                <a
                                    v-if="step.link"
                                    :href="step.link.href"
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#02CD86] px-4 py-2.5 text-sm font-medium text-[#101010] transition hover:brightness-110"
                                >
                                    <Bot class="size-4" />
                                    {{ step.link.label }}
                                </a>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Connected assistants -->
        <div>
            <p
                class="mb-4 text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
            >
                {{ t('settings.ai.connected_apps') }}
            </p>

            <p v-if="connections.length === 0" class="text-sm text-[#989898]">
                {{ t('settings.ai.no_connections') }}
            </p>

            <ul v-else class="space-y-2">
                <li
                    v-for="connection in connections"
                    :key="connection.id"
                    class="flex items-center gap-3 rounded-xl bg-[#252525] px-4 py-3"
                >
                    <div
                        class="grid size-9 shrink-0 place-items-center rounded-full bg-[#02CD86]/10"
                    >
                        <Bot class="size-4 text-[#02CD86]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">
                            {{ connection.client_name }}
                        </p>
                        <p class="text-xs text-[#989898]">
                            {{ t('settings.ai.connected_at') }}:
                            {{ formatDate(connection.created_at) }}
                            <span class="mx-1 text-white/20">·</span>
                            {{ t('settings.ai.expires_at') }}:
                            {{ formatDate(connection.expires_at) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-[#E94E50]/10 px-3 py-2 text-xs font-medium text-[#E94E50] transition hover:bg-[#E94E50]/20"
                        @click="revokeTarget = connection"
                    >
                        <Unlink class="size-3.5" />
                        {{ t('settings.ai.revoke') }}
                    </button>
                </li>
            </ul>
        </div>

        <!-- Divider -->
        <div class="border-t border-white/5" />

        <!-- Change history -->
        <div>
            <p
                class="mb-4 text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
            >
                {{ t('settings.ai.history') }}
            </p>

            <p v-if="history.length === 0" class="text-sm text-[#989898]">
                {{ t('settings.ai.history_empty') }}
            </p>

            <ul v-else class="space-y-2">
                <li
                    v-for="entry in history"
                    :key="entry.id"
                    class="rounded-xl bg-[#252525] px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-white">
                            {{ entry.action }} {{ entry.resource_type }}
                        </span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="statusClasses[entry.status]"
                        >
                            {{ t(`settings.ai.statuses.${entry.status}`) }}
                        </span>
                        <span class="ml-auto text-xs text-[#989898]">
                            {{ formatDate(entry.created_at) }}
                        </span>
                    </div>
                    <p
                        v-if="entry.client_name"
                        class="mt-1 text-xs text-[#989898]"
                    >
                        {{ entry.client_name }}
                    </p>
                </li>
            </ul>
        </div>

        <ConfirmDeleteModal
            :open="revokeTarget !== null"
            :title="t('settings.ai.revoke')"
            :description="t('settings.ai.revoke_confirm')"
            @update:open="revokeTarget = null"
            @confirm="confirmRevoke"
        />
    </div>
</template>
