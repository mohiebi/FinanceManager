<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { BotMessageSquare, CheckCircle2, ExternalLink, Unlink } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
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
</script>

<template>
    <Head title="Telegram settings" />

    <h1 class="sr-only">Telegram settings</h1>

    <!-- ── Connected state ─────────────────────────────────── -->
    <div v-if="connected" class="space-y-6">
        <Heading
            variant="small"
            title="Telegram Bot"
            description="Your account is linked. Use the bot to manage finances on the go."
        />

        <!-- Status row -->
        <div class="flex items-center gap-2 text-sm">
            <CheckCircle2 class="h-4 w-4 text-emerald-500" aria-hidden="true" />
            <span class="font-medium">Connected</span>
            <span class="text-muted-foreground">·</span>
            <span class="font-mono text-xs text-muted-foreground">{{ telegramChatId }}</span>
        </div>

        <!-- Open bot link -->
        <a
            v-if="botUrl"
            :href="botUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            Open bot in Telegram
            <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
        </a>

        <Separator />

        <!-- Available commands -->
        <Heading
            variant="small"
            title="Available commands"
            description="Send these commands directly in the bot chat"
        />

        <ul class="grid gap-2 text-sm text-muted-foreground">
            <li class="flex items-start gap-2">
                <span class="mt-0.5 font-mono text-xs font-medium text-foreground">/add_cost</span>
                <span>— record a cost transaction (guided step-by-step)</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 font-mono text-xs font-medium text-foreground">/add_income</span>
                <span>— record an income transaction</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 font-mono text-xs font-medium text-foreground">/add_investment</span>
                <span>— log a new investment position</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 font-mono text-xs font-medium text-foreground">/list</span>
                <span>— view and delete recent transactions</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 font-mono text-xs font-medium text-foreground">/report_today</span>
                <span>— today's income, costs, and net balance</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 font-mono text-xs font-medium text-foreground">/report_week</span>
                <span>— this week's financial summary</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-0.5 font-mono text-xs font-medium text-foreground">/report_month</span>
                <span>— this month's financial summary</span>
            </li>
        </ul>

        <Separator />

        <!-- Disconnect -->
        <Heading
            variant="small"
            title="Disconnect"
            description="Remove the link between your account and Telegram"
        />

        <Form
            v-bind="disconnect.form()"
            :options="{ preserveScroll: true }"
            #default="{ processing }"
        >
            <Button variant="destructive" type="submit" :disabled="processing">
                <Unlink class="h-4 w-4" aria-hidden="true" />
                Disconnect Telegram
            </Button>
        </Form>
    </div>

    <!-- ── Not connected state ─────────────────────────────── -->
    <div v-else class="space-y-6">
        <Heading
            variant="small"
            title="Telegram Bot"
            description="Link your Telegram account to manage transactions and get reports without opening the app"
        />

        <p class="text-sm text-muted-foreground">
            Once connected, you can add costs, record income, log investments, and receive
            daily, weekly, or monthly financial summaries — all from inside Telegram.
        </p>

        <!-- Link ready state -->
        <div v-if="deepLink" class="space-y-4">
            <div class="rounded-lg border p-4 text-sm">
                <p class="font-medium">Your link is ready</p>
                <p class="mt-1 text-muted-foreground">
                    Click the button below to open the bot, then press
                    <span class="font-medium text-foreground">Start</span> to complete the connection.
                </p>

                <a
                    :href="deepLink"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-md bg-[#229ED9] px-4 py-2 text-sm font-medium text-white transition-colors duration-150 hover:bg-[#1d8fc4] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-label="Telegram">
                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                    </svg>
                    Open in Telegram
                    <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
                </a>
            </div>
        </div>

        <!-- Connect / regenerate button -->
        <Form
            v-bind="connect.form()"
            :options="{ preserveScroll: true }"
            #default="{ processing }"
        >
            <Button type="submit" :disabled="processing">
                <BotMessageSquare class="h-4 w-4" aria-hidden="true" />
                {{ deepLink ? 'Generate new link' : 'Connect Telegram' }}
            </Button>
        </Form>
    </div>
</template>
