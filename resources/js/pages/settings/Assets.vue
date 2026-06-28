<script setup lang="ts">
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import AssetIcon from '@/components/AssetIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as destroyInvestmentAsset,
    store as storeInvestmentAsset,
    update as updateInvestmentAsset,
} from '@/routes/investment-assets';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

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

const formulaExample = 'goldprice * 900 / 750 * 8.133 / 2';

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

    <div class="space-y-7">
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
            >
                {{ t('settings.assets.eyebrow') }}
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                {{ t('settings.assets.description') }}
            </p>
        </div>

        <form class="space-y-3" @submit.prevent="createAsset">
            <div class="grid gap-3 sm:grid-cols-[1fr_90px_90px]">
                <div class="grid gap-2">
                    <Label class="finance-dialog-label" for="asset_name">
                        {{ t('settings.assets.name') }}
                    </Label>
                    <Input
                        id="asset_name"
                        v-model="createForm.name"
                        class="finance-dialog-field finance-dialog-field-income"
                        :placeholder="t('settings.assets.name_placeholder')"
                    />
                </div>
                <div class="grid gap-2">
                    <Label class="finance-dialog-label" for="asset_unit">
                        {{ t('settings.assets.unit') }}
                    </Label>
                    <Input
                        id="asset_unit"
                        v-model="createForm.unit"
                        class="finance-dialog-field finance-dialog-field-income"
                        :placeholder="t('settings.assets.unit_placeholder')"
                    />
                </div>
                <div class="grid gap-2">
                    <Label class="finance-dialog-label" for="asset_color">
                        {{ t('settings.assets.color') }}
                    </Label>
                    <Input
                        id="asset_color"
                        v-model="createForm.color"
                        class="finance-dialog-field finance-dialog-field-income"
                    />
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-[120px_1fr]">
                <div class="grid gap-2">
                    <Label class="finance-dialog-label" for="asset_icon">
                        {{ t('settings.assets.icon') }}
                    </Label>
                    <Input
                        id="asset_icon"
                        v-model="createForm.icon"
                        class="finance-dialog-field finance-dialog-field-income"
                        :placeholder="t('settings.assets.icon_placeholder')"
                    />
                </div>
                <div class="grid gap-2">
                    <Label class="finance-dialog-label" for="asset_icon_svg">
                        {{ t('settings.assets.svg_icon') }}
                    </Label>
                    <textarea
                        id="asset_icon_svg"
                        v-model="createForm.icon_svg"
                        rows="3"
                        class="finance-dialog-field finance-dialog-field-income min-h-[76px] resize-y"
                        :placeholder="t('settings.assets.svg_icon_placeholder')"
                    />
                    <p class="text-xs leading-5 text-[#989898]">
                        {{ t('settings.assets.svg_icon_help') }}
                    </p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-[150px_1fr]">
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
                <Input
                    v-if="createForm.price_source_type === 'manual'"
                    v-model="createForm.price_source_config.price"
                    class="finance-dialog-field finance-dialog-field-income"
                    type="number"
                    min="0"
                    step="any"
                    :placeholder="t('settings.assets.price_placeholder')"
                />
                <Input
                    v-else-if="createForm.price_source_type === 'formula'"
                    v-model="createForm.price_source_config.formula"
                    class="finance-dialog-field finance-dialog-field-income"
                    :placeholder="t('settings.assets.formula_placeholder')"
                />
                <div
                    v-if="createForm.price_source_type === 'formula'"
                    class="rounded-xl bg-white/[0.03] p-3 text-xs leading-5 text-[#989898] ring-1 ring-white/10 sm:col-start-2"
                >
                    <p class="font-medium text-white/80">
                        {{ t('settings.assets.formula_help_title') }}
                    </p>
                    <p class="mt-1">
                        {{ t('settings.assets.formula_help_body') }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <code
                            v-for="variable in formulaVariables"
                            :key="variable"
                            class="rounded-md bg-black/30 px-1.5 py-0.5 text-[#02CD86]"
                        >
                            {{ variable }}
                        </code>
                    </div>
                    <p class="mt-2">
                        {{ t('settings.assets.formula_example') }}
                        <code class="text-[#02CD86]">{{ formulaExample }}</code>
                    </p>
                </div>
                <div
                    v-else-if="createForm.price_source_type === 'json'"
                    class="grid gap-2 sm:grid-cols-2"
                >
                    <Input
                        v-model="createForm.price_source_config.url"
                        class="finance-dialog-field finance-dialog-field-income"
                        placeholder="https://api.example.com/price"
                    />
                    <Input
                        v-model="createForm.price_source_config.path"
                        class="finance-dialog-field finance-dialog-field-income"
                        :placeholder="t('settings.assets.path_placeholder')"
                    />
                </div>
                <div v-else class="grid gap-2 sm:grid-cols-2">
                    <Input
                        v-model="createForm.price_source_config.url"
                        class="finance-dialog-field finance-dialog-field-income"
                        placeholder="https://example.com/feed.xml"
                    />
                    <Input
                        v-model="createForm.price_source_config.xpath"
                        class="finance-dialog-field finance-dialog-field-income"
                        :placeholder="t('settings.assets.xpath_placeholder')"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <InputError
                    :message="
                        createForm.errors.name ||
                        createForm.errors.unit ||
                        createForm.errors.icon ||
                        createForm.errors.icon_svg ||
                        createForm.errors.color ||
                        createForm.errors.price_source_type ||
                        createForm.errors['price_source_config.price'] ||
                        createForm.errors['price_source_config.formula'] ||
                        createForm.errors['price_source_config.url'] ||
                        createForm.errors['price_source_config.path'] ||
                        createForm.errors['price_source_config.xpath']
                    "
                />
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

        <InputError :message="deleteError" />

        <section class="space-y-3">
            <div
                class="flex items-center justify-between border-t border-white/10 pt-5"
            >
                <h2 class="text-sm font-medium text-white">
                    {{ t('settings.assets.custom_assets') }}
                </h2>
                <span class="text-xs text-[#989898]">{{ assets.length }}</span>
            </div>

            <div v-if="assets.length > 0" class="space-y-2">
                <div
                    v-for="asset in assets"
                    :key="asset.id"
                    class="rounded-xl bg-[#252525] px-3 py-3 ring-1 ring-white/10"
                >
                    <form
                        v-if="editingId === asset.id"
                        class="space-y-2"
                        @submit.prevent="saveAsset(asset)"
                    >
                        <div class="grid gap-2 sm:grid-cols-[1fr_80px_80px_90px]">
                            <Input
                                v-model="editForm.name"
                                class="finance-dialog-field finance-dialog-field-income"
                            />
                            <Input
                                v-model="editForm.unit"
                                class="finance-dialog-field finance-dialog-field-income"
                            />
                            <Input
                                v-model="editForm.icon"
                                class="finance-dialog-field finance-dialog-field-income"
                                :placeholder="t('settings.assets.icon_placeholder')"
                            />
                            <Input
                                v-model="editForm.color"
                                class="finance-dialog-field finance-dialog-field-income"
                            />
                        </div>
                        <textarea
                            v-model="editForm.icon_svg"
                            rows="3"
                            class="finance-dialog-field finance-dialog-field-income min-h-[76px] resize-y"
                            :placeholder="t('settings.assets.svg_icon_placeholder')"
                        />
                        <select
                            v-model="editForm.price_source_type"
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
                        <Input
                            v-if="editForm.price_source_type === 'manual'"
                            v-model="editForm.price_source_config.price"
                            class="finance-dialog-field finance-dialog-field-income"
                            type="number"
                            min="0"
                            step="any"
                        />
                        <Input
                            v-else-if="editForm.price_source_type === 'formula'"
                            v-model="editForm.price_source_config.formula"
                            class="finance-dialog-field finance-dialog-field-income"
                        />
                        <div
                            v-if="editForm.price_source_type === 'formula'"
                            class="rounded-xl bg-white/[0.03] p-3 text-xs leading-5 text-[#989898] ring-1 ring-white/10"
                        >
                            <p class="font-medium text-white/80">
                                {{ t('settings.assets.formula_help_title') }}
                            </p>
                            <p class="mt-1">
                                {{ t('settings.assets.formula_help_body') }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <code
                                    v-for="variable in formulaVariables"
                                    :key="variable"
                                    class="rounded-md bg-black/30 px-1.5 py-0.5 text-[#02CD86]"
                                >
                                    {{ variable }}
                                </code>
                            </div>
                            <p class="mt-2">
                                {{ t('settings.assets.formula_example') }}
                                <code class="text-[#02CD86]">{{
                                    formulaExample
                                }}</code>
                            </p>
                        </div>
                        <div
                            v-else-if="editForm.price_source_type === 'json'"
                            class="grid gap-2 sm:grid-cols-2"
                        >
                            <Input
                                v-model="editForm.price_source_config.url"
                                class="finance-dialog-field finance-dialog-field-income"
                            />
                            <Input
                                v-model="editForm.price_source_config.path"
                                class="finance-dialog-field finance-dialog-field-income"
                            />
                        </div>
                        <div v-else class="grid gap-2 sm:grid-cols-2">
                            <Input
                                v-model="editForm.price_source_config.url"
                                class="finance-dialog-field finance-dialog-field-income"
                            />
                            <Input
                                v-model="editForm.price_source_config.xpath"
                                class="finance-dialog-field finance-dialog-field-income"
                            />
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
                                editForm.errors['price_source_config.formula'] ||
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
                            >
                                <X class="size-4" />
                                {{ t('common.cancel') }}
                            </Button>
                            <Button
                                class="h-9 flex-1 bg-[#02CD86] text-[#101010] hover:bg-[#08dd93]"
                                :disabled="editForm.processing"
                            >
                                <Spinner v-if="editForm.processing" />
                                <Check v-else class="size-4" />
                                {{ t('common.save') }}
                            </Button>
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

            <p v-else class="rounded-xl bg-[#252525] px-4 py-4 text-sm text-[#989898]">
                {{ t('settings.assets.empty') }}
            </p>
        </section>

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
