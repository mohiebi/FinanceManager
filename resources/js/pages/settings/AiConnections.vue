<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Bot,
    Check,
    ChevronDown,
    ChevronUp,
    Copy,
    History,
    Plug,
    ShieldCheck,
    Sparkles,
    Unlink,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { destroy as destroyConnection, edit } from '@/routes/ai-connections';

type Connection = {
    id: string;
    client_name: string;
    created_at: string | null;
    expires_at: string | null;
    active_sessions: number;
};

type Change = {
    old: unknown;
    new: unknown;
};

type HistoryEntry = {
    id: string;
    client_name: string | null;
    action: string;
    resource_type: string;
    status: 'pending' | 'confirmed' | 'rejected' | 'expired';
    diff: Record<string, Change>;
    created_at: string;
    consumed_at: string | null;
};

const props = defineProps<{
    connections: Connection[];
    history: HistoryEntry[];
    mcpUrl: string;
}>();

const { t, locale } = useI18n();

const revokeTarget = ref<Connection | null>(null);
const copiedKey = ref<string | null>(null);
const expandedHistoryId = ref<string | null>(null);

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(
            locale.value === 'fa' ? 'fa-IR' : locale.value,
            {
                dateStyle: 'medium',
                timeStyle: 'short',
            },
        ),
);

function formatDate(value: string | null): string {
    return value ? dateFormatter.value.format(new Date(value)) : '\u2014';
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

function actionLabel(action: string): string {
    return t(`settings.ai.actions.${action}`);
}

function resourceLabel(resourceType: string): string {
    return t(`settings.ai.resources.${resourceType}`);
}

function fieldLabel(field: string): string {
    const key = `settings.ai.fields.${field}`;
    const translated = t(key);

    return translated === key ? field.replaceAll('_', ' ') : translated;
}

function formatChangeValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '\u2014';
    }

    if (typeof value === 'boolean') {
        return value ? t('settings.ai.yes') : t('settings.ai.no');
    }

    if (Array.isArray(value)) {
        return value.map(formatChangeValue).join(', ');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

type ClientKey = 'claude' | 'codex' | 'other';

type SetupStep = {
    text?: string;
    code?: string;
};

type SetupSection = {
    title?: string;
    steps: SetupStep[];
};

const selectedClient = ref<ClientKey | null>(null);

const clientOptions = computed<{ key: ClientKey; label: string }[]>(() => [
    { key: 'claude', label: t('settings.ai.connect.clients.claude') },
    { key: 'codex', label: t('settings.ai.connect.clients.codex') },
    { key: 'other', label: t('settings.ai.connect.clients.other') },
]);

const installCommands = computed<Partial<Record<ClientKey, string>>>(() => ({
    claude: `claude mcp add --transport http cashpilot ${props.mcpUrl}`,
    codex: `codex mcp add cashpilot --url ${props.mcpUrl}`,
}));

const selectedInstallCommand = computed(() => {
    if (selectedClient.value === null) {
        return null;
    }

    return installCommands.value[selectedClient.value] ?? null;
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
                    code: installCommands.value.claude,
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
                    code: installCommands.value.codex,
                },
                { text: t('settings.ai.connect.codex.cli_step2') },
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

    <div class="flex flex-col gap-[18px]">
        <SettingsSection
            :icon="Sparkles"
            :title="t('settings.ai.eyebrow')"
            :description="t('settings.ai.description')"
        >
            <div class="space-y-5">
                <section
                    class="flex gap-3 rounded-2xl bg-[#02CD86]/8 p-4 ring-1 ring-[#02CD86]/20"
                    aria-labelledby="ai-safety-title"
                >
                    <div
                        class="grid size-9 shrink-0 place-items-center rounded-xl bg-[#02CD86]/12"
                    >
                        <ShieldCheck class="size-4 text-[#02CD86]" />
                    </div>
                    <div>
                        <p
                            id="ai-safety-title"
                            class="text-sm font-medium text-white"
                        >
                            {{ t('settings.ai.safety.title') }}
                        </p>
                        <p
                            class="mt-1 max-w-prose text-sm leading-6 text-[#989898]"
                        >
                            {{ t('settings.ai.safety.description') }}
                        </p>
                    </div>
                </section>

                <!-- Connect your assistant -->
                <div
                    class="rounded-[22px] bg-[#252525] p-5 ring-1 ring-white/10"
                >
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
                                    selectedClient === option.key
                                        ? null
                                        : option.key
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
                            v-if="selectedInstallCommand"
                            class="flex flex-col gap-3 rounded-xl bg-[#02CD86]/8 p-3 ring-1 ring-[#02CD86]/20 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="text-sm font-medium text-white">
                                    {{
                                        t(
                                            'settings.ai.connect.quick_install.title',
                                        )
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs leading-5 text-[#989898]"
                                >
                                    {{
                                        t(
                                            'settings.ai.connect.quick_install.description',
                                        )
                                    }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#02CD86] px-3 py-2.5 text-sm font-medium text-[#101010] transition hover:brightness-110"
                                @click="
                                    copyText(
                                        selectedInstallCommand,
                                        `quick-install-${selectedClient}`,
                                    )
                                "
                            >
                                <Check
                                    v-if="
                                        copiedKey ===
                                        `quick-install-${selectedClient}`
                                    "
                                    class="size-4"
                                />
                                <Copy v-else class="size-4" />
                                {{
                                    copiedKey ===
                                    `quick-install-${selectedClient}`
                                        ? t(
                                              'settings.ai.connect.quick_install.copied',
                                          )
                                        : t(
                                              'settings.ai.connect.quick_install.button',
                                          )
                                }}
                            </button>
                        </div>

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
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsSection>

        <SettingsSection :icon="Plug" :title="t('settings.ai.connected_apps')">
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
                            <span class="mx-1 text-white/20">&middot;</span>
                            {{
                                t('settings.ai.active_sessions', {
                                    count: connection.active_sessions,
                                })
                            }}
                            <span class="mx-1 text-white/20">&middot;</span>
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
        </SettingsSection>

        <SettingsSection :icon="History" :title="t('settings.ai.history')">
            <p v-if="history.length === 0" class="text-sm text-[#989898]">
                {{ t('settings.ai.history_empty') }}
            </p>

            <ul v-else class="space-y-2">
                <li
                    v-for="entry in history"
                    :key="entry.id"
                    class="overflow-hidden rounded-xl bg-[#252525] ring-1 ring-white/5"
                >
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2 px-4 py-3 text-left transition hover:bg-white/[0.025] focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:outline-none focus-visible:ring-inset"
                        :aria-expanded="expandedHistoryId === entry.id"
                        @click="
                            expandedHistoryId =
                                expandedHistoryId === entry.id ? null : entry.id
                        "
                    >
                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate text-sm font-medium text-white"
                            >
                                {{ actionLabel(entry.action) }}
                                {{ resourceLabel(entry.resource_type) }}
                            </span>
                            <span class="mt-1 block text-xs text-[#989898]">
                                {{
                                    entry.client_name ??
                                    t('settings.ai.unknown_client')
                                }}
                                <span class="mx-1 text-white/20">&middot;</span>
                                {{
                                    formatDate(
                                        entry.consumed_at ?? entry.created_at,
                                    )
                                }}
                            </span>
                        </span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="statusClasses[entry.status]"
                        >
                            {{ t(`settings.ai.statuses.${entry.status}`) }}
                        </span>
                        <ChevronUp
                            v-if="expandedHistoryId === entry.id"
                            class="size-4 shrink-0 text-[#989898]"
                        />
                        <ChevronDown
                            v-else
                            class="size-4 shrink-0 text-[#989898]"
                        />
                    </button>
                    <div
                        v-if="expandedHistoryId === entry.id"
                        class="border-t border-white/5 bg-[#101010]/45 px-4 py-4"
                    >
                        <p
                            class="mb-3 text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                        >
                            {{ t('settings.ai.change_details') }}
                        </p>
                        <dl class="grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="(change, field) in entry.diff"
                                :key="field"
                                class="rounded-xl bg-[#252525] px-3 py-2.5"
                            >
                                <dt
                                    class="text-[11px] font-medium tracking-[0.08em] text-[#989898] uppercase"
                                >
                                    {{ fieldLabel(field) }}
                                </dt>
                                <dd class="mt-1 text-xs break-words text-white">
                                    <span class="text-[#989898]">{{
                                        formatChangeValue(change.old)
                                    }}</span>
                                    <span class="mx-1.5 text-[#02CD86]"
                                        >&rarr;</span
                                    >
                                    <span>{{
                                        formatChangeValue(change.new)
                                    }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </li>
            </ul>
        </SettingsSection>

        <ConfirmDeleteModal
            :open="revokeTarget !== null"
            :title="t('settings.ai.revoke')"
            :description="t('settings.ai.revoke_confirm')"
            @update:open="revokeTarget = null"
            @confirm="confirmRevoke"
        />
    </div>
</template>
