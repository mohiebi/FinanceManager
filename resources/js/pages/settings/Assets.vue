<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as destroyInvestmentAsset,
    store as storeInvestmentAsset,
    update as updateInvestmentAsset,
} from '@/routes/investment-assets';

type PriceSourceType = 'manual' | 'formula' | 'json' | 'xml';

type SourceConfig = {
    price?: string | number | null;
    formula?: string | null;
    url?: string | null;
    path?: string | null;
    xpath?: string | null;
    divide_by?: string | number | null;
};

type InvestmentAsset = {
    id: number;
    name: string;
    slug: string;
    unit: string;
    icon: string | null;
    icon_svg: string | null;
    color: string;
    is_default: boolean;
    price_source_type: PriceSourceType;
    price_source_config: SourceConfig;
    investments_count: number;
};

defineProps<{
    assets: InvestmentAsset[];
}>();

const { t } = useI18n();
const page = usePage();
const editingId = ref<number | null>(null);
const deleteTarget = ref<InvestmentAsset | null>(null);
const deleteProcessing = ref(false);

const showCreateEmoji = ref(false);
const showEditEmoji = ref(false);
const showCreateAdvanced = ref(false);
const showEditAdvanced = ref(false);

const presetColors = ['#F59E0B', '#6B7280', '#0EA5E9', '#8B5CF6', '#F97316'];

const emojiGroups = [
    {
        label: 'Metals & Coins',
        emojis: [
            '🥇',
            '🥈',
            '🪙',
            '💰',
            '💎',
            '🔶',
            '⭕',
            '🔵',
            '🟡',
            '🟠',
            '⚪',
            '🟤',
        ],
    },
    {
        label: 'Finance',
        emojis: [
            '💵',
            '💴',
            '💶',
            '💷',
            '💸',
            '💳',
            '🏦',
            '📈',
            '📉',
            '📊',
            '💹',
            '🤑',
        ],
    },
    {
        label: 'Crypto',
        emojis: [
            '₿',
            'Ξ',
            '◎',
            '⬡',
            '🔷',
            '🔸',
            '🟣',
            '🟢',
            '🔴',
            '⚡',
            '🌊',
            '🦊',
        ],
    },
    {
        label: 'Other',
        emojis: [
            '🏠',
            '🚗',
            '🛢',
            '⚙️',
            '🌾',
            '☕',
            '🍶',
            '💊',
            '🎯',
            '🚀',
            '⭐',
            '🔑',
        ],
    },
];

const emojiGroupLabelKeys: Record<string, string> = {
    'Metals & Coins': 'metals',
    Finance: 'finance',
    Crypto: 'crypto',
    Other: 'other',
};

const formulaVariables = [
    'goldprice',
    'gold_750',
    'gold_900',
    'usd',
    'eur',
    'silver',
    'coin',
    'bitcoin',
    'bitcoin_usd',
    'usdt',
];

function emojiGroupLabel(label: string): string {
    return t(
        `settings.assets.emoji_groups.${emojiGroupLabelKeys[label] ?? 'other'}`,
    );
}

const blankConfig = (): Required<Record<keyof SourceConfig, string>> => ({
    price: '',
    formula: '',
    url: '',
    path: '',
    xpath: '',
    divide_by: '',
});

const createForm = useForm({
    name: '',
    unit: '',
    icon: '',
    icon_svg: '',
    color: '#02CD86',
    price_source_type: 'manual' as PriceSourceType,
    price_source_config: blankConfig(),
});

const editForm = useForm({
    name: '',
    unit: '',
    icon: '',
    icon_svg: '',
    color: '#02CD86',
    price_source_type: 'manual' as PriceSourceType,
    price_source_config: blankConfig(),
});

const deleteError = computed(() => {
    const errors = page.props.errors as
        | Record<string, string | undefined>
        | undefined;

    return errors?.investment_asset;
});

function createAsset(): void {
    createForm.post(storeInvestmentAsset.url(), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.color = '#02CD86';
            createForm.price_source_type = 'manual';
            createForm.price_source_config = blankConfig();
        },
    });
}

function startEdit(asset: InvestmentAsset): void {
    editingId.value = asset.id;
    editForm.clearErrors();
    editForm.name = asset.name;
    editForm.unit = asset.unit;
    editForm.icon = asset.icon ?? '';
    editForm.icon_svg = asset.icon_svg ?? '';
    editForm.color = asset.color;
    editForm.price_source_type = asset.price_source_type;
    editForm.price_source_config = {
        ...blankConfig(),
        ...Object.fromEntries(
            Object.entries(asset.price_source_config ?? {}).map(
                ([key, value]) => [key, value === null ? '' : String(value)],
            ),
        ),
    };
}

function cancelEdit(): void {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function saveAsset(asset: InvestmentAsset): void {
    editForm.patch(updateInvestmentAsset.url(asset.id), {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
}

function confirmDelete(): void {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(destroyInvestmentAsset.url(deleteTarget.value.id), {
        preserveScroll: true,
        onBefore: () => {
            if ((deleteTarget.value?.investments_count ?? 0) > 0) {
                return false;
            }

            return true;
        },
        onStart: () => {
            deleteProcessing.value = true;
        },
        onSuccess: () => {
            deleteTarget.value = null;
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Asset settings', href: '/settings/assets' }],
    },
});
</script>

<template>
    <Head :title="t('settings.assets.title')" />

    <div class="flex flex-col gap-[18px]">
        <SettingsSection
            :title="t('settings.assets.eyebrow')"
            :description="t('settings.assets.description')"
        >
            <form class="space-y-4" @submit.prevent="createAsset">
                <!-- Row 1: Name + Unit -->
                <div class="grid gap-3 sm:grid-cols-[1fr_100px]">
                    <div class="grid gap-2">
                        <Label class="finance-dialog-label" for="asset_name">{{
                            t('settings.assets.name')
                        }}</Label>
                        <Input
                            id="asset_name"
                            v-model="createForm.name"
                            class="finance-dialog-field finance-dialog-field-income"
                            :placeholder="t('settings.assets.name_placeholder')"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label class="finance-dialog-label" for="asset_unit">{{
                            t('settings.assets.unit')
                        }}</Label>
                        <Input
                            id="asset_unit"
                            v-model="createForm.unit"
                            class="finance-dialog-field finance-dialog-field-income"
                            :placeholder="t('settings.assets.unit_placeholder')"
                        />
                    </div>
                </div>

                <!-- Row 2: Emoji icon + Color swatches -->
                <div class="grid gap-3 sm:grid-cols-[120px_1fr]">
                    <div class="grid gap-2">
                        <Label class="finance-dialog-label">{{
                            t('settings.assets.icon')
                        }}</Label>
                        <div class="relative">
                            <button
                                type="button"
                                class="finance-dialog-field finance-dialog-field-income flex h-9 w-full items-center gap-2 px-3 text-start"
                                @click="showCreateEmoji = !showCreateEmoji"
                            >
                                <span
                                    v-if="createForm.icon"
                                    class="text-xl leading-none"
                                    >{{ createForm.icon }}</span
                                >
                                <span v-else class="text-sm text-white/50">{{
                                    t('settings.assets.pick_emoji')
                                }}</span>
                            </button>
                            <div
                                v-if="showCreateEmoji"
                                class="absolute top-full left-0 z-50 mt-1 w-[260px] rounded-xl border border-white/10 bg-[#1f1f1f] p-3 shadow-2xl"
                            >
                                <div
                                    v-for="group in emojiGroups"
                                    :key="group.label"
                                    class="mb-3 last:mb-0"
                                >
                                    <p
                                        class="mb-1.5 text-[10px] font-medium tracking-wider text-[#686868] uppercase"
                                    >
                                        {{ emojiGroupLabel(group.label) }}
                                    </p>
                                    <div class="grid grid-cols-6 gap-1">
                                        <button
                                            v-for="emoji in group.emojis"
                                            :key="emoji"
                                            type="button"
                                            :class="[
                                                'flex h-8 w-full cursor-pointer items-center justify-center rounded-lg text-lg transition-colors hover:bg-white/10',
                                                createForm.icon === emoji
                                                    ? 'bg-[#02CD86]/20 ring-1 ring-[#02CD86]/40'
                                                    : '',
                                            ]"
                                            @click="
                                                createForm.icon = emoji;
                                                showCreateEmoji = false;
                                            "
                                        >
                                            {{ emoji }}
                                        </button>
                                    </div>
                                </div>
                                <button
                                    v-if="createForm.icon"
                                    type="button"
                                    class="mt-1 w-full cursor-pointer rounded-lg py-1.5 text-xs text-[#989898] transition-colors hover:bg-white/5 hover:text-white"
                                    @click="
                                        createForm.icon = '';
                                        showCreateEmoji = false;
                                    "
                                >
                                    {{ t('common.clear') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label class="finance-dialog-label">{{
                            t('settings.assets.color')
                        }}</Label>
                        <div class="flex items-center gap-2 pt-1">
                            <button
                                v-for="c in presetColors"
                                :key="c"
                                type="button"
                                :style="{ backgroundColor: c }"
                                :class="[
                                    'size-6 cursor-pointer rounded-full transition-transform hover:scale-110',
                                    createForm.color === c
                                        ? 'scale-110 ring-2 ring-white ring-offset-1 ring-offset-[#1a1a1a]'
                                        : '',
                                ]"
                                @click="createForm.color = c"
                            />
                            <label
                                class="relative size-6 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20"
                                :style="{
                                    backgroundColor: presetColors.includes(
                                        createForm.color,
                                    )
                                        ? '#333'
                                        : createForm.color,
                                }"
                            >
                                <span
                                    class="absolute inset-0 flex items-center justify-center text-[9px] text-white/70"
                                    >+</span
                                >
                                <input
                                    v-model="createForm.color"
                                    type="color"
                                    class="absolute inset-0 cursor-pointer opacity-0"
                                />
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Price (manual default) -->
                <div class="grid gap-2">
                    <Label class="finance-dialog-label" for="asset_price">
                        {{ t('settings.assets.price_source') }}
                        <span class="ml-1 font-light text-[#989898]"
                            >({{ t('finance.fields.optional') }})</span
                        >
                    </Label>
                    <Input
                        v-if="createForm.price_source_type === 'manual'"
                        id="asset_price"
                        v-model="createForm.price_source_config.price"
                        class="finance-dialog-field finance-dialog-field-income"
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
                    @click="showCreateAdvanced = !showCreateAdvanced"
                >
                    <ChevronDown
                        :class="[
                            'size-3 transition-transform',
                            showCreateAdvanced ? 'rotate-180' : '',
                        ]"
                    />
                    {{ t('settings.assets.advanced_options') }}
                </button>

                <div
                    v-if="showCreateAdvanced"
                    class="space-y-3 rounded-[8px] border border-white/8 bg-black/20 p-3"
                >
                    <div class="grid gap-2">
                        <Label class="finance-dialog-label">{{
                            t('settings.assets.price_source')
                        }}</Label>
                        <select
                            v-model="createForm.price_source_type"
                            class="finance-dialog-field finance-dialog-field-income"
                        >
                            <option value="manual">
                                {{ t('settings.assets.sources.manual') }}
                            </option>
                            <option value="formula">
                                {{ t('settings.assets.sources.formula') }}
                            </option>
                            <option value="json">
                                {{ t('settings.assets.sources.json') }}
                            </option>
                            <option value="xml">
                                {{ t('settings.assets.sources.xml') }}
                            </option>
                        </select>
                    </div>
                    <div
                        v-if="createForm.price_source_type === 'formula'"
                        class="space-y-2"
                    >
                        <Input
                            v-model="createForm.price_source_config.formula"
                            class="finance-dialog-field finance-dialog-field-income"
                            :placeholder="
                                t('settings.assets.formula_placeholder')
                            "
                        />
                        <div
                            class="rounded-[6px] bg-black/20 p-2 text-xs leading-5 text-[#989898]"
                        >
                            <p class="font-medium text-white/70">
                                {{ t('settings.assets.formula_help_title') }}
                            </p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                <code
                                    v-for="variable in formulaVariables"
                                    :key="variable"
                                    class="rounded-md bg-black/30 px-1.5 py-0.5 text-[#02CD86]"
                                    >{{ variable }}</code
                                >
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="createForm.price_source_type === 'json'"
                        class="grid gap-2 sm:grid-cols-2"
                    >
                        <div class="grid gap-1">
                            <Label class="finance-dialog-label">{{
                                t('settings.assets.url')
                            }}</Label
                            ><Input
                                v-model="createForm.price_source_config.url"
                                class="finance-dialog-field finance-dialog-field-income"
                                placeholder="https://api.example.com/price"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="finance-dialog-label">{{
                                t('settings.assets.path')
                            }}</Label
                            ><Input
                                v-model="createForm.price_source_config.path"
                                class="finance-dialog-field finance-dialog-field-income"
                                :placeholder="
                                    t('settings.assets.path_placeholder')
                                "
                            />
                        </div>
                    </div>
                    <div
                        v-if="createForm.price_source_type === 'xml'"
                        class="grid gap-2 sm:grid-cols-2"
                    >
                        <div class="grid gap-1">
                            <Label class="finance-dialog-label">{{
                                t('settings.assets.url')
                            }}</Label
                            ><Input
                                v-model="createForm.price_source_config.url"
                                class="finance-dialog-field finance-dialog-field-income"
                                placeholder="https://example.com/feed.xml"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="finance-dialog-label">{{
                                t('settings.assets.xpath')
                            }}</Label
                            ><Input
                                v-model="createForm.price_source_config.xpath"
                                class="finance-dialog-field finance-dialog-field-income"
                                :placeholder="
                                    t('settings.assets.xpath_placeholder')
                                "
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="finance-dialog-label"
                            >{{ t('settings.assets.svg_icon') }}
                            <span class="ml-1 font-light text-[#989898]"
                                >({{ t('finance.fields.optional') }})</span
                            ></Label
                        >
                        <textarea
                            v-model="createForm.icon_svg"
                            rows="2"
                            class="finance-dialog-field finance-dialog-field-income min-h-[56px] resize-y font-mono text-xs"
                            :placeholder="
                                t('settings.assets.svg_icon_placeholder')
                            "
                        />
                        <p class="text-xs text-[#989898]">
                            {{ t('settings.assets.svg_icon_help') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <InputError
                            :message="
                                createForm.errors.name ||
                                createForm.errors.unit ||
                                createForm.errors.icon ||
                                createForm.errors.icon_svg ||
                                createForm.errors.color ||
                                createForm.errors.price_source_type ||
                                createForm.errors[
                                    'price_source_config.price'
                                ] ||
                                createForm.errors[
                                    'price_source_config.formula'
                                ] ||
                                createForm.errors['price_source_config.url'] ||
                                createForm.errors['price_source_config.path'] ||
                                createForm.errors['price_source_config.xpath']
                            "
                        />
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="createForm.recentlySuccessful"
                                class="text-sm text-[#02CD86]"
                            >
                                {{ t('common.saved') }}
                            </p>
                        </Transition>
                    </div>
                    <Button
                        class="h-9 bg-[#02CD86] text-[#101010] hover:bg-[#08dd93]"
                        :disabled="createForm.processing"
                    >
                        <Spinner v-if="createForm.processing" />
                        <Plus v-else class="size-4" />
                        {{ t('settings.assets.add') }}
                    </Button>
                </div>
            </form>
        </SettingsSection>

        <SettingsSection :title="t('settings.assets.custom_assets')">
            <div class="mb-3 flex items-center justify-between gap-3">
                <InputError :message="deleteError" />
                <span class="ms-auto text-xs text-[#989898]">
                    {{ assets.length }}
                </span>
            </div>

            <div v-if="assets.length > 0" class="space-y-2">
                <div
                    v-for="asset in assets"
                    :key="asset.id"
                    class="rounded-xl bg-[#252525] px-3 py-3 ring-1 ring-white/10"
                >
                    <form
                        v-if="editingId === asset.id"
                        class="space-y-3"
                        @submit.prevent="saveAsset(asset)"
                    >
                        <!-- Name + Unit -->
                        <div class="grid gap-2 sm:grid-cols-[1fr_100px]">
                            <div class="grid gap-1">
                                <Label class="finance-dialog-label">{{
                                    t('settings.assets.name')
                                }}</Label
                                ><Input
                                    v-model="editForm.name"
                                    class="finance-dialog-field finance-dialog-field-income"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label class="finance-dialog-label">{{
                                    t('settings.assets.unit')
                                }}</Label
                                ><Input
                                    v-model="editForm.unit"
                                    class="finance-dialog-field finance-dialog-field-income"
                                />
                            </div>
                        </div>
                        <!-- Icon + Color -->
                        <div class="grid gap-2 sm:grid-cols-[120px_1fr]">
                            <div class="grid gap-1">
                                <Label class="finance-dialog-label">{{
                                    t('settings.assets.icon')
                                }}</Label>
                                <div class="relative">
                                    <button
                                        type="button"
                                        class="finance-dialog-field finance-dialog-field-income flex h-9 w-full items-center gap-2 px-3 text-start"
                                        @click="showEditEmoji = !showEditEmoji"
                                    >
                                        <span
                                            v-if="editForm.icon"
                                            class="text-xl leading-none"
                                            >{{ editForm.icon }}</span
                                        >
                                        <span
                                            v-else
                                            class="text-sm text-white/50"
                                            >{{
                                                t('settings.assets.pick_emoji')
                                            }}</span
                                        >
                                    </button>
                                    <div
                                        v-if="showEditEmoji"
                                        class="absolute top-full left-0 z-50 mt-1 w-[260px] rounded-xl border border-white/10 bg-[#1f1f1f] p-3 shadow-2xl"
                                    >
                                        <div
                                            v-for="group in emojiGroups"
                                            :key="group.label"
                                            class="mb-3 last:mb-0"
                                        >
                                            <p
                                                class="mb-1.5 text-[10px] font-medium tracking-wider text-[#686868] uppercase"
                                            >
                                                {{
                                                    emojiGroupLabel(group.label)
                                                }}
                                            </p>
                                            <div class="grid grid-cols-6 gap-1">
                                                <button
                                                    v-for="emoji in group.emojis"
                                                    :key="emoji"
                                                    type="button"
                                                    :class="[
                                                        'flex h-8 w-full cursor-pointer items-center justify-center rounded-lg text-lg transition-colors hover:bg-white/10',
                                                        editForm.icon === emoji
                                                            ? 'bg-[#02CD86]/20 ring-1 ring-[#02CD86]/40'
                                                            : '',
                                                    ]"
                                                    @click="
                                                        editForm.icon = emoji;
                                                        showEditEmoji = false;
                                                    "
                                                >
                                                    {{ emoji }}
                                                </button>
                                            </div>
                                        </div>
                                        <button
                                            v-if="editForm.icon"
                                            type="button"
                                            class="mt-1 w-full cursor-pointer rounded-lg py-1.5 text-xs text-[#989898] transition-colors hover:bg-white/5 hover:text-white"
                                            @click="
                                                editForm.icon = '';
                                                showEditEmoji = false;
                                            "
                                        >
                                            {{ t('common.clear') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <Label class="finance-dialog-label">{{
                                    t('settings.assets.color')
                                }}</Label>
                                <div class="flex items-center gap-2 pt-1">
                                    <button
                                        v-for="c in presetColors"
                                        :key="c"
                                        type="button"
                                        :style="{ backgroundColor: c }"
                                        :class="[
                                            'size-6 cursor-pointer rounded-full transition-transform hover:scale-110',
                                            editForm.color === c
                                                ? 'scale-110 ring-2 ring-white ring-offset-1 ring-offset-[#252525]'
                                                : '',
                                        ]"
                                        @click="editForm.color = c"
                                    />
                                    <label
                                        class="relative size-6 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20"
                                        :style="{
                                            backgroundColor:
                                                presetColors.includes(
                                                    editForm.color,
                                                )
                                                    ? '#333'
                                                    : editForm.color,
                                        }"
                                    >
                                        <span
                                            class="absolute inset-0 flex items-center justify-center text-[9px] text-white/70"
                                            >+</span
                                        >
                                        <input
                                            v-model="editForm.color"
                                            type="color"
                                            class="absolute inset-0 cursor-pointer opacity-0"
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>
                        <!-- Price source -->
                        <div class="grid gap-1.5">
                            <Label class="finance-dialog-label">{{
                                t('settings.assets.price_source')
                            }}</Label>
                            <Input
                                v-if="editForm.price_source_type === 'manual'"
                                v-model="editForm.price_source_config.price"
                                class="finance-dialog-field finance-dialog-field-income"
                                type="number"
                                min="0"
                                step="any"
                                :placeholder="
                                    t('settings.assets.price_placeholder')
                                "
                            />
                        </div>
                        <!-- Advanced -->
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-[11px] text-white/40 transition-colors hover:text-white/70"
                            @click="showEditAdvanced = !showEditAdvanced"
                        >
                            <ChevronDown
                                :class="[
                                    'size-3 transition-transform',
                                    showEditAdvanced ? 'rotate-180' : '',
                                ]"
                            />
                            {{ t('settings.assets.advanced_options') }}
                        </button>
                        <div
                            v-if="showEditAdvanced"
                            class="space-y-3 rounded-[8px] border border-white/8 bg-black/20 p-3"
                        >
                            <div class="grid gap-1.5">
                                <Label class="finance-dialog-label">{{
                                    t('settings.assets.price_source')
                                }}</Label>
                                <select
                                    v-model="editForm.price_source_type"
                                    class="finance-dialog-field finance-dialog-field-income"
                                >
                                    <option value="manual">
                                        {{
                                            t('settings.assets.sources.manual')
                                        }}
                                    </option>
                                    <option value="formula">
                                        {{
                                            t('settings.assets.sources.formula')
                                        }}
                                    </option>
                                    <option value="json">
                                        {{ t('settings.assets.sources.json') }}
                                    </option>
                                    <option value="xml">
                                        {{ t('settings.assets.sources.xml') }}
                                    </option>
                                </select>
                            </div>
                            <div
                                v-if="editForm.price_source_type === 'formula'"
                                class="space-y-2"
                            >
                                <Input
                                    v-model="
                                        editForm.price_source_config.formula
                                    "
                                    class="finance-dialog-field finance-dialog-field-income"
                                    :placeholder="
                                        t('settings.assets.formula_placeholder')
                                    "
                                />
                                <div
                                    class="rounded-[6px] bg-black/20 p-2 text-xs leading-5 text-[#989898]"
                                >
                                    <p class="font-medium text-white/70">
                                        {{
                                            t(
                                                'settings.assets.formula_help_title',
                                            )
                                        }}
                                    </p>
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        <code
                                            v-for="variable in formulaVariables"
                                            :key="variable"
                                            class="rounded-md bg-black/30 px-1.5 py-0.5 text-[#02CD86]"
                                            >{{ variable }}</code
                                        >
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="editForm.price_source_type === 'json'"
                                class="grid gap-2 sm:grid-cols-2"
                            >
                                <div class="grid gap-1">
                                    <Label class="finance-dialog-label">{{
                                        t('settings.assets.url')
                                    }}</Label
                                    ><Input
                                        v-model="
                                            editForm.price_source_config.url
                                        "
                                        class="finance-dialog-field finance-dialog-field-income"
                                        placeholder="https://api.example.com/price"
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="finance-dialog-label">{{
                                        t('settings.assets.path')
                                    }}</Label
                                    ><Input
                                        v-model="
                                            editForm.price_source_config.path
                                        "
                                        class="finance-dialog-field finance-dialog-field-income"
                                        :placeholder="
                                            t(
                                                'settings.assets.path_placeholder',
                                            )
                                        "
                                    />
                                </div>
                            </div>
                            <div
                                v-if="editForm.price_source_type === 'xml'"
                                class="grid gap-2 sm:grid-cols-2"
                            >
                                <div class="grid gap-1">
                                    <Label class="finance-dialog-label">{{
                                        t('settings.assets.url')
                                    }}</Label
                                    ><Input
                                        v-model="
                                            editForm.price_source_config.url
                                        "
                                        class="finance-dialog-field finance-dialog-field-income"
                                        placeholder="https://example.com/feed.xml"
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="finance-dialog-label">{{
                                        t('settings.assets.xpath')
                                    }}</Label
                                    ><Input
                                        v-model="
                                            editForm.price_source_config.xpath
                                        "
                                        class="finance-dialog-field finance-dialog-field-income"
                                        :placeholder="
                                            t(
                                                'settings.assets.xpath_placeholder',
                                            )
                                        "
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="finance-dialog-label">{{
                                    t('settings.assets.svg_icon')
                                }}</Label>
                                <textarea
                                    v-model="editForm.icon_svg"
                                    rows="2"
                                    class="finance-dialog-field finance-dialog-field-income min-h-[56px] resize-y font-mono text-xs"
                                    :placeholder="
                                        t(
                                            'settings.assets.svg_icon_placeholder',
                                        )
                                    "
                                />
                            </div>
                        </div>
                        <InputError
                            :message="
                                editForm.errors.name ||
                                editForm.errors.unit ||
                                editForm.errors.icon ||
                                editForm.errors.icon_svg ||
                                editForm.errors.color ||
                                editForm.errors.price_source_type ||
                                editForm.errors['price_source_config.price'] ||
                                editForm.errors[
                                    'price_source_config.formula'
                                ] ||
                                editForm.errors['price_source_config.url'] ||
                                editForm.errors['price_source_config.path'] ||
                                editForm.errors['price_source_config.xpath']
                            "
                        />
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                class="h-9 flex-1 bg-white/5 text-white/70 shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white"
                                @click="cancelEdit"
                                ><X class="size-4" />{{
                                    t('common.cancel')
                                }}</Button
                            >
                            <Button
                                class="h-9 flex-1 bg-[#02CD86] text-[#101010] hover:bg-[#08dd93]"
                                :disabled="editForm.processing"
                                ><Spinner v-if="editForm.processing" /><Check
                                    v-else
                                    class="size-4"
                                />{{ t('common.save') }}</Button
                            >
                        </div>
                    </form>

                    <div v-else class="flex items-center gap-3">
                        <AssetIcon
                            :icon="asset.icon"
                            :icon-svg="asset.icon_svg"
                            :label="asset.name"
                            :color="asset.color"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-white">
                                {{ asset.name }}
                            </p>
                            <p class="text-xs text-[#989898]">
                                {{
                                    t('settings.assets.usage', {
                                        count: asset.investments_count,
                                    })
                                }}
                                · {{ asset.unit }} ·
                                {{
                                    t(
                                        `settings.assets.sources.${asset.price_source_type}`,
                                    )
                                }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            class="h-9 w-9 shrink-0 bg-white/5 p-0 text-white/70 shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white"
                            :title="t('common.edit')"
                            @click="startEdit(asset)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            class="h-9 w-9 shrink-0 bg-[#E94E50]/10 p-0 text-[#E94E50] shadow-none ring-1 ring-[#E94E50]/20 hover:bg-[#E94E50]/20"
                            :title="t('common.delete')"
                            @click="deleteTarget = asset"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-xl bg-[#252525] px-4 py-4 text-sm text-[#989898]"
            >
                {{ t('settings.assets.empty') }}
            </p>
        </SettingsSection>

        <ConfirmDeleteModal
            :open="deleteTarget !== null"
            :title="
                t('settings.assets.delete_title', {
                    name: deleteTarget?.name ?? '',
                })
            "
            :description="
                (deleteTarget?.investments_count ?? 0) > 0
                    ? t('settings.assets.delete_blocked_description', {
                          count: deleteTarget?.investments_count ?? 0,
                      })
                    : t('settings.assets.delete_description')
            "
            :processing="deleteProcessing"
            :confirm-disabled="(deleteTarget?.investments_count ?? 0) > 0"
            @update:open="deleteTarget = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
