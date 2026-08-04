<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    BotMessageSquare,
    CheckCircle2,
    ExternalLink,
    Unlink,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { connect, disconnect, edit } from '@/routes/telegram';
import type { FeatureKey } from '@/types/features';

type Props = {
    connected: boolean;
    telegramChatId: string | null;
    connectToken: string | null;
    botUsername: string | null;
};

const { t } = useI18n();
const page = usePage();
const props = defineProps<Props>();

const deepLink = computed(() => {
    if (!props.connectToken || !props.botUsername) {
        return null;
    }

    const username = props.botUsername.replace(/^@/, '');

    return `https://t.me/${username}?start=${props.connectToken}`;
});

const botUrl = computed(() => {
    if (!props.botUsername) {
        return null;
    }

    return `https://t.me/${props.botUsername.replace(/^@/, '')}`;
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Telegram settings', href: edit() }],
    },
});

/**
 * What the bot will actually answer, for this user.
 *
 * `feature: null` means the command is always available; the rest mirror the
 * gates in App\Telegraph\TelegramHandler::mainKeyboard(), so the page never
 * advertises a command that would reply "that module is switched off".
 */
const commands = computed(() => {
    const features = page.props.features;

    const entries: { cmd: string; key: string; feature: FeatureKey | null }[] =
        [
            { cmd: '/help', key: 'help', feature: null },
            { cmd: '/add_cost', key: 'add_cost', feature: null },
            { cmd: '/add_income', key: 'add_income', feature: null },
            { cmd: '/list', key: 'list', feature: null },
            { cmd: '/delete_tx', key: 'delete_tx', feature: null },
            {
                cmd: '/add_investment',
                key: 'add_investment',
                feature: 'investments',
            },
            { cmd: '/add_bill', key: 'add_bill', feature: 'bills' },
            { cmd: '/list_bills', key: 'list_bills', feature: 'bills' },
            { cmd: '/portfolio', key: 'portfolio', feature: 'portfolio' },
            { cmd: '/budget', key: 'budget', feature: 'budgets' },
            { cmd: '/no_spend', key: 'no_spend', feature: 'gamification' },
            { cmd: '/report_today', key: 'report_today', feature: null },
            { cmd: '/report_daily', key: 'report_daily', feature: null },
            { cmd: '/report_week', key: 'report_week', feature: null },
            { cmd: '/report_month', key: 'report_month', feature: null },
        ];

    return (
        entries
            // A missing prop means a partial page rather than a disabled module, so
            // the command is kept rather than silently dropped.
            .filter(
                (entry) =>
                    entry.feature === null ||
                    (features?.[entry.feature]?.enabled ?? true),
            )
            .map((entry) => ({
                cmd: entry.cmd,
                desc: t(`settings.telegram.commands.${entry.key}`),
            }))
    );
});
</script>

<template>
    <Head :title="t('settings.telegram.title')" />

    <div class="flex flex-col gap-[18px]">
        <!-- ── Connected state ─────────────────────────────────── -->
        <template v-if="connected">
            <SettingsSection
                :title="t('settings.telegram.bot')"
                :description="t('settings.telegram.connected_description')"
            >
                <div class="space-y-5">
                    <div
                        class="flex items-center gap-3 rounded-xl bg-[#02CD86]/10 px-4 py-3"
                    >
                        <CheckCircle2 class="size-4 shrink-0 text-[#02CD86]" />
                        <span class="text-sm font-medium text-[#02CD86]">{{
                            t('settings.telegram.connected')
                        }}</span>
                        <span
                            class="ml-auto font-mono text-xs text-[#989898]"
                            >{{ telegramChatId }}</span
                        >
                    </div>

                    <a
                        v-if="botUrl"
                        :href="botUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-[#02CD86] hover:underline"
                    >
                        {{ t('settings.telegram.open_bot') }}
                        <ExternalLink class="size-3.5" />
                    </a>

                    <div>
                        <p
                            class="mb-4 text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >
                            {{ t('settings.telegram.available_commands') }}
                        </p>
                        <ul class="space-y-2">
                            <li
                                v-for="item in commands"
                                :key="item.cmd"
                                class="flex items-start gap-3 rounded-xl bg-[#252525] px-4 py-3"
                            >
                                <code
                                    class="shrink-0 font-mono text-xs text-[#02CD86]"
                                    >{{ item.cmd }}</code
                                >
                                <span class="text-sm text-[#989898]">{{
                                    item.desc
                                }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </SettingsSection>

            <!-- Its own card: disconnecting is destructive enough to want a
                 red edge, and to not share a surface with the command list. -->
            <SettingsSection
                danger
                :title="t('settings.telegram.disconnect')"
                :description="t('settings.telegram.disconnect_description')"
            >
                <Form
                    v-bind="disconnect.form()"
                    :options="{ preserveScroll: true }"
                    #default="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#E94E50]/10 px-5 py-2.5 text-sm font-medium text-[#E94E50] transition-colors duration-200 hover:bg-[#E94E50]/20 focus-visible:ring-2 focus-visible:ring-[#E94E50] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1414] focus-visible:outline-none disabled:opacity-50"
                    >
                        <Unlink class="size-4" />
                        {{ t('settings.telegram.disconnect_button') }}
                    </button>
                </Form>
            </SettingsSection>
        </template>

        <!-- ── Not connected state ─────────────────────────────── -->
        <SettingsSection
            v-else
            :title="t('settings.telegram.bot')"
            :description="t('settings.telegram.not_connected_description')"
        >
            <div class="space-y-5">
                <p class="text-sm leading-relaxed text-[#989898]">
                    {{ t('settings.telegram.intro') }}
                </p>

                <div
                    v-if="deepLink"
                    class="rounded-[22px] bg-[#252525] p-5 ring-1 ring-white/10"
                >
                    <p class="font-medium text-white">
                        {{ t('settings.telegram.link_ready') }}
                    </p>
                    <p class="mt-1 text-sm text-[#989898]">
                        {{ t('settings.telegram.link_ready_before') }}
                        <span class="font-medium text-white">{{
                            t('settings.telegram.link_ready_start')
                        }}</span>
                        {{ t('settings.telegram.link_ready_after') }}
                    </p>
                    <a
                        :href="deepLink"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#229ED9] px-4 py-2.5 text-sm font-medium text-white transition-colors duration-200 hover:bg-[#1d8fc4]"
                    >
                        <svg
                            class="size-4 shrink-0"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-label="Telegram"
                        >
                            <path
                                d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"
                            />
                        </svg>
                        {{ t('settings.telegram.open_in_telegram') }}
                        <ExternalLink class="size-3.5" />
                    </a>
                </div>

                <Form
                    v-bind="connect.form()"
                    :options="{ preserveScroll: true }"
                    #default="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#02CD86] px-5 py-2.5 text-sm font-medium text-[#101010] transition-[filter] duration-200 hover:brightness-110 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:opacity-50"
                    >
                        <BotMessageSquare class="size-4" />
                        {{
                            deepLink
                                ? t('settings.telegram.generate_new_link')
                                : t('settings.telegram.connect_telegram')
                        }}
                    </button>
                </Form>
            </div>
        </SettingsSection>
    </div>
</template>
