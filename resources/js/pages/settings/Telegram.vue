<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { BotMessageSquare, CheckCircle2, ExternalLink, Unlink } from 'lucide-vue-next';
import { computed } from 'vue';
import { connect, disconnect, edit } from '@/routes/telegram';

type Props = {
    connected: boolean;
    telegramChatId: string | null;
    connectToken: string | null;
    botUsername: string | null;
};

const props = defineProps<Props>();

const deepLink = computed(() => {
    if (!props.connectToken || !props.botUsername) return null;
    const username = props.botUsername.replace(/^@/, '');
    return `https://t.me/${username}?start=${props.connectToken}`;
});

const botUrl = computed(() => {
    if (!props.botUsername) return null;
    return `https://t.me/${props.botUsername.replace(/^@/, '')}`;
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Telegram settings', href: edit() }],
    },
});

const commands = [
    { cmd: '/add_cost', desc: 'Record a cost transaction (guided step-by-step)' },
    { cmd: '/add_income', desc: 'Record an income transaction' },
    { cmd: '/add_investment', desc: 'Log a new investment position' },
    { cmd: '/list', desc: 'View recent transactions' },
    { cmd: '/report_today', desc: "Today's income, costs and net balance" },
    { cmd: '/report_week', desc: "This week's financial summary" },
    { cmd: '/report_month', desc: "This month's financial summary" },
];
</script>

<template>
    <Head title="Telegram settings" />

    <h1 class="sr-only">Telegram settings</h1>

    <!-- ── Connected state ─────────────────────────────────── -->
    <div v-if="connected" class="space-y-8">
        <!-- Header -->
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
            >
                Telegram Bot
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                Your account is linked. Use the bot to manage finances on the
                go.
            </p>
        </div>

        <!-- Status row -->
        <div
            class="flex items-center gap-3 rounded-xl bg-[#02CD86]/10 px-4 py-3"
        >
            <CheckCircle2 class="size-4 shrink-0 text-[#02CD86]" />
            <span class="text-sm font-medium text-[#02CD86]">Connected</span>
            <span class="ml-auto font-mono text-xs text-[#989898]">{{
                telegramChatId
            }}</span>
        </div>

        <!-- Open bot -->
        <a
            v-if="botUrl"
            :href="botUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-[#02CD86] hover:underline"
        >
            Open bot in Telegram
            <ExternalLink class="size-3.5" />
        </a>

        <!-- Divider -->
        <div class="border-t border-white/5" />

        <!-- Commands -->
        <div>
            <p
                class="mb-4 text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
            >
                Available commands
            </p>
            <ul class="space-y-2">
                <li
                    v-for="item in commands"
                    :key="item.cmd"
                    class="flex items-start gap-3 rounded-xl bg-[#252525] px-4 py-3"
                >
                    <code class="shrink-0 font-mono text-xs text-[#02CD86]">{{
                        item.cmd
                    }}</code>
                    <span class="text-sm text-[#989898]">{{ item.desc }}</span>
                </li>
            </ul>
        </div>

        <!-- Divider -->
        <div class="border-t border-white/5" />

        <!-- Disconnect -->
        <div>
            <p
                class="mb-1 text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
            >
                Disconnect
            </p>
            <p class="mb-4 text-sm text-[#989898]">
                Remove the link between your account and Telegram
            </p>
            <Form
                v-bind="disconnect.form()"
                :options="{ preserveScroll: true }"
                #default="{ processing }"
            >
                <button
                    type="submit"
                    :disabled="processing"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#E94E50]/10 px-5 py-2.5 text-sm font-medium text-[#E94E50] transition hover:bg-[#E94E50]/20 disabled:opacity-50"
                >
                    <Unlink class="size-4" />
                    Disconnect Telegram
                </button>
            </Form>
        </div>
    </div>

    <!-- ── Not connected state ─────────────────────────────── -->
    <div v-else class="space-y-8">
        <!-- Header -->
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
            >
                Telegram Bot
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                Link your Telegram account to manage transactions and get
                reports without opening the app.
            </p>
        </div>

        <p class="text-sm leading-relaxed text-[#989898]">
            Once connected, you can add costs, record income, log investments,
            and receive daily, weekly, or monthly financial summaries — all from
            inside Telegram.
        </p>

        <!-- Link ready -->
        <div v-if="deepLink" class="rounded-[22px] bg-[#252525] p-5 ring-1 ring-white/10">
            <p class="font-medium text-white">Your link is ready</p>
            <p class="mt-1 text-sm text-[#989898]">
                Click the button below to open the bot, then press
                <span class="font-medium text-white">Start</span> to complete
                the connection.
            </p>
            <a
                :href="deepLink"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#229ED9] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[#1d8fc4]"
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
                Open in Telegram
                <ExternalLink class="size-3.5" />
            </a>
        </div>

        <!-- Connect / regenerate -->
        <Form
            v-bind="connect.form()"
            :options="{ preserveScroll: true }"
            #default="{ processing }"
        >
            <button
                type="submit"
                :disabled="processing"
                class="inline-flex items-center gap-2 rounded-xl bg-[#02CD86] px-5 py-2.5 text-sm font-medium text-[#101010] transition hover:brightness-110 disabled:opacity-50"
            >
                <BotMessageSquare class="size-4" />
                {{ deepLink ? 'Generate new link' : 'Connect Telegram' }}
            </button>
        </Form>
    </div>
</template>
