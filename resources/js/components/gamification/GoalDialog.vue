<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useVault } from '@/composables/useVault';
import { store as storeGoal } from '@/routes/savings-goals';
import type { AssetOption } from '@/types/gamification';

const props = defineProps<{
    assetOptions: AssetOption[];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const { sealForSubmit } = useVault();
const submitting = ref(false);

const form = useForm({
    investment_asset_id: '',
    title: '',
    target_quantity: '',
    target_date: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

async function submit(): Promise<void> {
    submitting.value = true;

    try {
        // 'quantity' rather than 'decimal': a 2dp seal would turn a target of
        // 0.00012345 into zero. No-op when the vault is not armed.
        const payload = await sealForSubmit(
            {
                title: form.title,
                target_quantity: form.target_quantity,
            },
            'savings_goals',
            { title: 'string', target_quantity: 'quantity' },
        );

        form.transform(() => ({
            investment_asset_id: form.investment_asset_id,
            target_date: form.target_date,
            ...payload,
        })).post(storeGoal.url(), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                submitting.value = false;
            },
        });
    } catch {
        submitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="finance-dialog sm:max-w-[440px]">
            <DialogHeader>
                <DialogTitle>{{ t('gamification.goals.new') }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="goal_asset">
                        {{ t('gamification.goals.asset') }}
                    </Label>
                    <Select
                        id="goal_asset"
                        v-model="form.investment_asset_id"
                    >
                        <SelectTrigger class="finance-dialog-field w-full">
                            <SelectValue
                                :placeholder="t('gamification.goals.asset_placeholder')"
                            />
                        </SelectTrigger>
                        <SelectContent class="finance-dialog-select-content">
                            <SelectItem
                                v-for="asset in props.assetOptions"
                                :key="asset.id"
                                :value="String(asset.id)"
                            >
                                {{ asset.label }} ({{ asset.unit }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.investment_asset_id" />
                </div>

                <div class="space-y-2">
                    <Label for="goal_target">
                        {{ t('gamification.goals.target') }}
                    </Label>
                    <Input
                        id="goal_target"
                        v-model="form.target_quantity"
                        class="finance-dialog-field"
                        type="number"
                        step="any"
                        min="0"
                        inputmode="decimal"
                        placeholder="3"
                    />
                    <InputError :message="form.errors.target_quantity" />
                </div>

                <div class="space-y-2">
                    <Label for="goal_date">
                        {{ t('gamification.goals.target_date') }}
                    </Label>
                    <!-- The app's calendar-aware picker: a jalali user cannot
                         express 1406-01-01 in a native date input at all. -->
                    <BirthdatePicker
                        v-model="form.target_date"
                        name="target_date"
                        :years-back="0"
                        :years-forward="10"
                    />
                    <InputError :message="form.errors.target_date" />
                </div>

                <div class="space-y-2">
                    <Label for="goal_title">
                        {{ t('gamification.goals.label') }}
                    </Label>
                    <Input
                        id="goal_title"
                        v-model="form.title"
                        class="finance-dialog-field"
                        type="text"
                        maxlength="120"
                        :placeholder="t('gamification.goals.label_placeholder')"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <DialogFooter>
                    <Button
                        type="submit"
                        class="bg-[#02CD86] text-[#101010] hover:bg-[#08dd93]"
                        :disabled="submitting || form.processing"
                    >
                        <Spinner v-if="submitting || form.processing" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
