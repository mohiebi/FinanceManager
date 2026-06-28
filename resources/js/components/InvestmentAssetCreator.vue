<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeInvestmentAsset } from '@/routes/investment-assets';
import { useForm, usePage } from '@inertiajs/vue3';
import { Check, Plus, X } from 'lucide-vue-next';
import { ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useI18n } from 'vue-i18n';

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

const form = useForm({
    name: '',
    unit: '',
    icon: '',
    icon_svg: '',
    color: '#02CD86',
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

function close(): void {
    isOpen.value = false;
    form.reset();
    form.clearErrors();
    form.color = '#02CD86';
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
        <button
            v-if="!isOpen"
            type="button"
            class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-medium text-white/55 transition-colors hover:text-white"
            @click="isOpen = true"
        >
            <Plus class="size-3.5" />
            {{ t('settings.assets.add_custom') }}
        </button>

        <div
            v-else
            class="w-full rounded-[10px] border border-white/10 bg-white/[0.03] p-2"
        >
            <div class="grid gap-2">
                <div class="grid gap-2 sm:grid-cols-[1fr_72px]">
                    <div class="grid gap-1">
                        <Label class="sr-only" for="new-asset-name">
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
                    <Input
                        v-model="form.unit"
                        :class="fieldClass"
                        :placeholder="t('settings.assets.unit_placeholder')"
                        autocomplete="off"
                    />
                </div>
                <div class="grid gap-2 sm:grid-cols-[72px_1fr]">
                    <Input
                        v-model="form.icon"
                        :class="fieldClass"
                        :placeholder="t('settings.assets.icon_placeholder')"
                        autocomplete="off"
                    />
                    <textarea
                        v-model="form.icon_svg"
                        rows="2"
                        class="finance-dialog-field min-h-[64px] resize-y"
                        :class="fieldClass"
                        :placeholder="t('settings.assets.svg_icon_placeholder')"
                    />
                </div>

                <select
                    v-model="form.price_source_type"
                    class="finance-dialog-field"
                    :class="fieldClass"
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
                    v-if="form.price_source_type === 'manual'"
                    v-model="form.price_source_config.price"
                    :class="fieldClass"
                    type="number"
                    min="0"
                    step="any"
                    :placeholder="t('settings.assets.price_placeholder')"
                />
                <Input
                    v-if="form.price_source_type === 'formula'"
                    v-model="form.price_source_config.formula"
                    :class="fieldClass"
                    :placeholder="t('settings.assets.formula_placeholder')"
                />
                <div
                    v-if="form.price_source_type === 'formula'"
                    class="rounded-[8px] bg-black/20 p-2 text-xs leading-5 text-[#989898]"
                >
                    <p class="font-medium text-white/80">
                        {{ t('settings.assets.formula_help_title') }}
                    </p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <code
                            v-for="variable in formulaVariables"
                            :key="variable"
                            class="rounded-md bg-black/30 px-1.5 py-0.5 text-[#02CD86]"
                        >
                            {{ variable }}
                        </code>
                    </div>
                    <p class="mt-1">
                        {{ t('settings.assets.formula_example') }}
                        <code class="text-[#02CD86]">{{ formulaExample }}</code>
                    </p>
                </div>
                <template v-if="form.price_source_type === 'json'">
                    <Input
                        v-model="form.price_source_config.url"
                        :class="fieldClass"
                        placeholder="https://api.example.com/price"
                    />
                    <Input
                        v-model="form.price_source_config.path"
                        :class="fieldClass"
                        :placeholder="t('settings.assets.path_placeholder')"
                    />
                </template>
                <template v-if="form.price_source_type === 'xml'">
                    <Input
                        v-model="form.price_source_config.url"
                        :class="fieldClass"
                        placeholder="https://example.com/feed.xml"
                    />
                    <Input
                        v-model="form.price_source_config.xpath"
                        :class="fieldClass"
                        :placeholder="t('settings.assets.xpath_placeholder')"
                    />
                </template>

                <div class="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        class="h-9 rounded-[8px] bg-white/5 p-0 text-white/70 shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white"
                        :title="t('common.cancel')"
                        @click="close"
                    >
                        <X class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        class="h-9 rounded-[8px] bg-[#02CD86] p-0 text-[#101010] shadow-none hover:bg-[#08dd93]"
                        :title="t('common.save')"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        <Check class="size-4" />
                    </Button>
                </div>
            </div>
            <InputError
                :message="
                    form.errors.name ||
                    form.errors.unit ||
                    form.errors.icon ||
                    form.errors.icon_svg ||
                    form.errors.price_source_type ||
                    form.errors['price_source_config.price'] ||
                    form.errors['price_source_config.formula'] ||
                    form.errors['price_source_config.url'] ||
                    form.errors['price_source_config.path'] ||
                    form.errors['price_source_config.xpath']
                "
            />
        </div>
    </div>
</template>
