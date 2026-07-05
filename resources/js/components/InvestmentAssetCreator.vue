<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Plus, X } from 'lucide-vue-next';
import { ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeInvestmentAsset } from '@/routes/investment-assets';

type CreatedInvestmentAsset = { id: number } | null;
type PriceSourceType = 'manual' | 'formula' | 'json' | 'xml';

defineProps<{
    fieldClass: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    created: [id: string];
}>();

const { t } = useI18n();
const page = usePage();
const isOpen = ref(false);
const showAdvanced = ref(false);
const showEmojiPicker = ref(false);

// 5 preset colors + custom
const presetColors = [
    '#F59E0B', // gold
    '#6B7280', // silver/gray
    '#0EA5E9', // sky blue
    '#8B5CF6', // purple
    '#F97316', // orange
];

// Emoji picker — grouped by category relevant to investments
const emojiGroups = [
    { label: 'Metals & Coins', emojis: ['🥇','🥈','🪙','💰','💎','🔶','⭕','🔵','🟡','🟠','⚪','🟤'] },
    { label: 'Finance', emojis: ['💵','💴','💶','💷','💸','💳','🏦','📈','📉','📊','💹','🤑'] },
    { label: 'Crypto', emojis: ['₿','Ξ','◎','⬡','🔷','🔸','🟣','🟢','🔴','⚡','🌊','🦊'] },
    { label: 'Other', emojis: ['🏠','🚗','🛢','⚙️','🌾','☕','🍶','💊','🎯','🚀','⭐','🔑'] },
];

const emojiGroupLabelKeys: Record<string, string> = {
    'Metals & Coins': 'metals',
    Finance: 'finance',
    Crypto: 'crypto',
    Other: 'other',
};

const formulaVariables = [
    'goldprice', 'gold_750', 'gold_900',
    'usd', 'eur', 'silver', 'coin',
    'bitcoin', 'bitcoin_usd', 'usdt',
];

function emojiGroupLabel(label: string): string {
    return t(`settings.assets.emoji_groups.${emojiGroupLabelKeys[label] ?? 'other'}`);
}


const form = useForm({
    name: '',
    unit: '',
    icon: '',
    icon_svg: '',
    color: '#F59E0B',
    price_source_type: 'manual' as PriceSourceType,
    price_source_config: {
        price: '',
        formula: '',
        url: '',
        path: '',
        xpath: '',
        divide_by: '',
    },
});

function pickEmoji(emoji: string): void {
    form.icon = emoji;
    showEmojiPicker.value = false;
}

function close(): void {
    isOpen.value = false;
    showAdvanced.value = false;
    showEmojiPicker.value = false;
    form.reset();
    form.clearErrors();
    form.color = '#F59E0B';
    form.price_source_type = 'manual';
}

function submit(): void {
    form.post(storeInvestmentAsset.url(), {
        only: ['assetTypes', 'createdInvestmentAsset', 'errors'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const createdAsset = page.props
                .createdInvestmentAsset as CreatedInvestmentAsset;

            if (createdAsset?.id) {
                emit('created', String(createdAsset.id));
            }

            close();
        },
    });
}
</script>

<template>
    <div class="space-y-2">
        <!-- Trigger -->
        <button
            v-if="!isOpen"
            type="button"
            class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-medium text-white/55 transition-colors hover:text-white"
            @click="isOpen = true"
        >
            <Plus class="size-3.5" />
            {{ t('settings.assets.add_custom') }}
        </button>

        <!-- Expanded form -->
        <div
            v-else
            class="w-full rounded-[10px] border border-white/10 bg-white/[0.03] p-4"
        >
            <div class="space-y-4">

                <!-- Row 1: Name + Unit -->
                <div class="grid gap-3 sm:grid-cols-[1fr_100px]">
                    <div class="grid gap-1.5">
                        <Label class="finance-dialog-label" for="new-asset-name">
                            {{ t('settings.assets.name') }}
                        </Label>
                        <Input
                            id="new-asset-name"
                            v-model="form.name"
                            :class="fieldClass"
                            :placeholder="t('settings.assets.name_placeholder')"
                            autocomplete="off"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="finance-dialog-label" for="new-asset-unit">
                            {{ t('settings.assets.unit') }}
                        </Label>
                        <Input
                            id="new-asset-unit"
                            v-model="form.unit"
                            :class="fieldClass"
                            :placeholder="t('settings.assets.unit_placeholder')"
                            autocomplete="off"
                        />
                    </div>
                </div>

                <!-- Row 2: Icon (with emoji picker) + Color (one line) -->
                <div class="grid gap-3 sm:grid-cols-[120px_1fr]">

                    <!-- Icon -->
                    <div class="grid gap-1.5">
                        <Label class="finance-dialog-label">
                            {{ t('settings.assets.icon') }}
                        </Label>
                        <div class="relative">
                            <!-- Trigger button showing current emoji -->
                            <button
                                type="button"
                                :class="[fieldClass, 'flex h-9 w-full cursor-pointer items-center gap-2 rounded-md border px-3 text-left text-base transition-colors']"
                                @click="showEmojiPicker = !showEmojiPicker"
                            >
                                <span v-if="form.icon" class="text-xl leading-none">{{ form.icon }}</span>
                                <span v-else class="text-sm text-white/50">{{ t('settings.assets.pick_emoji') }}</span>
                            </button>

                            <!-- Emoji picker popover -->
                            <div
                                v-if="showEmojiPicker"
                                class="absolute top-full left-0 z-50 mt-1 w-[260px] rounded-xl border border-white/10 bg-[#1f1f1f] p-3 shadow-2xl"
                            >
                                <div
                                    v-for="group in emojiGroups"
                                    :key="group.label"
                                    class="mb-3 last:mb-0"
                                >
                                    <p class="mb-1.5 text-[10px] font-medium uppercase tracking-wider text-[#686868]">
                                        {{ emojiGroupLabel(group.label) }}
                                    </p>
                                    <div class="grid grid-cols-6 gap-1">
                                        <button
                                            v-for="emoji in group.emojis"
                                            :key="emoji"
                                            type="button"
                                            :class="[
                                                'flex h-8 w-full cursor-pointer items-center justify-center rounded-lg text-lg transition-colors hover:bg-white/10',
                                                form.icon === emoji ? 'bg-[#02CD86]/20 ring-1 ring-[#02CD86]/40' : '',
                                            ]"
                                            @click="pickEmoji(emoji)"
                                        >
                                            {{ emoji }}
                                        </button>
                                    </div>
                                </div>
                                <!-- Clear selection -->
                                <button
                                    v-if="form.icon"
                                    type="button"
                                    class="mt-1 w-full cursor-pointer rounded-lg py-1.5 text-xs text-[#989898] transition-colors hover:bg-white/5 hover:text-white"
                                    @click="form.icon = ''; showEmojiPicker = false"
                                >
                                    {{ t('common.clear') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Color -->
                    <div class="grid gap-1.5">
                        <Label class="finance-dialog-label">{{ t('settings.assets.color') }}</Label>
                        <div class="flex items-center gap-2 pt-1">
                            <button
                                v-for="c in presetColors"
                                :key="c"
                                type="button"
                                :style="{ backgroundColor: c }"
                                :class="[
                                    'size-6 cursor-pointer rounded-full transition-transform hover:scale-110',
                                    form.color === c ? 'ring-2 ring-white ring-offset-1 ring-offset-[#252525] scale-110' : '',
                                ]"
                                :title="c"
                                @click="form.color = c"
                            />
                            <!-- Custom color -->
                            <label
                                class="relative size-6 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20"
                                :style="{ backgroundColor: presetColors.includes(form.color) ? '#333' : form.color }"
                                :title="t('settings.assets.custom_color')"
                            >
                                <span class="absolute inset-0 flex items-center justify-center text-[9px] leading-none text-white/70">+</span>
                                <input
                                    v-model="form.color"
                                    type="color"
                                    class="absolute inset-0 cursor-pointer opacity-0"
                                />
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Price (optional) -->
                <div class="grid gap-1.5">
                    <Label class="finance-dialog-label" for="new-asset-price">
                        {{ t('settings.assets.price_source') }}
                        <span class="ml-1 font-light text-[#989898]">({{ t('finance.fields.optional') }})</span>
                    </Label>
                    <Input
                        v-if="form.price_source_type === 'manual'"
                        id="new-asset-price"
                        v-model="form.price_source_config.price"
                        :class="fieldClass"
                        type="number"
                        min="0"
                        step="any"
                        :placeholder="t('settings.assets.price_placeholder')"
                    />
                </div>

                <!-- Advanced toggle -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1 text-[11px] text-white/40 transition-colors hover:text-white/70"
                    @click="showAdvanced = !showAdvanced"
                >
                    <ChevronDown :class="['size-3 transition-transform', showAdvanced ? 'rotate-180' : '']" />
                    {{ t('settings.assets.advanced_options') }}
                </button>

                <!-- Advanced section -->
                <div v-if="showAdvanced" class="space-y-3 rounded-[8px] border border-white/8 bg-black/20 p-3">
                    <div class="grid gap-1.5">
                        <Label class="finance-dialog-label" for="new-asset-source">
                            {{ t('settings.assets.price_source') }}
                        </Label>
                        <select id="new-asset-source" v-model="form.price_source_type" class="finance-dialog-field" :class="fieldClass">
                            <option value="manual">{{ t('settings.assets.sources.manual') }}</option>
                            <option value="formula">{{ t('settings.assets.sources.formula') }}</option>
                            <option value="json">{{ t('settings.assets.sources.json') }}</option>
                            <option value="xml">{{ t('settings.assets.sources.xml') }}</option>
                        </select>
                    </div>
                    <div v-if="form.price_source_type === 'formula'" class="space-y-2">
                        <Input v-model="form.price_source_config.formula" :class="fieldClass" :placeholder="t('settings.assets.formula_placeholder')" />
                        <div class="rounded-[6px] bg-black/20 p-2 text-xs leading-5 text-[#989898]">
                            <p class="font-medium text-white/70">{{ t('settings.assets.formula_help_title') }}</p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                <code v-for="variable in formulaVariables" :key="variable" class="rounded-md bg-black/30 px-1.5 py-0.5 text-[#02CD86]">{{ variable }}</code>
                            </div>
                        </div>
                    </div>
                    <div v-if="form.price_source_type === 'json'" class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-1"><Label class="finance-dialog-label">{{ t('settings.assets.url') }}</Label><Input v-model="form.price_source_config.url" :class="fieldClass" placeholder="https://api.example.com/price" /></div>
                        <div class="grid gap-1"><Label class="finance-dialog-label">{{ t('settings.assets.path') }}</Label><Input v-model="form.price_source_config.path" :class="fieldClass" :placeholder="t('settings.assets.path_placeholder')" /></div>
                    </div>
                    <div v-if="form.price_source_type === 'xml'" class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-1"><Label class="finance-dialog-label">{{ t('settings.assets.url') }}</Label><Input v-model="form.price_source_config.url" :class="fieldClass" placeholder="https://example.com/feed.xml" /></div>
                        <div class="grid gap-1"><Label class="finance-dialog-label">{{ t('settings.assets.xpath') }}</Label><Input v-model="form.price_source_config.xpath" :class="fieldClass" :placeholder="t('settings.assets.xpath_placeholder')" /></div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="finance-dialog-label" for="new-asset-svg">{{ t('settings.assets.svg_icon') }} <span class="ml-1 font-light text-[#989898]">({{ t('finance.fields.optional') }})</span></Label>
                        <textarea id="new-asset-svg" v-model="form.icon_svg" rows="2" class="finance-dialog-field min-h-[56px] resize-y font-mono text-xs" :class="fieldClass" :placeholder="t('settings.assets.svg_icon_placeholder')" />
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 pt-1">
                    <Button type="button" class="h-8 rounded-[8px] bg-white/5 px-3 text-xs text-white/70 shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white" @click="close">
                        <X class="mr-1 size-3.5" />{{ t('common.cancel') }}
                    </Button>
                    <Button type="button" class="h-8 rounded-[8px] bg-[#02CD86] px-3 text-xs text-[#101010] shadow-none hover:bg-[#08dd93]" :disabled="form.processing" @click="submit">
                        <Check class="mr-1 size-3.5" />{{ t('common.save') }}
                    </Button>
                </div>

                <InputError :message="form.errors.name || form.errors.unit || form.errors.icon || form.errors.icon_svg || form.errors.price_source_type || form.errors['price_source_config.price'] || form.errors['price_source_config.formula'] || form.errors['price_source_config.url'] || form.errors['price_source_config.path'] || form.errors['price_source_config.xpath']" />
            </div>
        </div>
    </div>
</template>
