<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
import {
    store as storeGoal,
    update as updateGoal,
} from '@/routes/savings-goals';
import type { AssetOption, GoalCard } from '@/types/gamification';

const props = defineProps<{
    assetOptions: AssetOption[];
    /** The goal being edited, or null to create a new one. */
    goal?: GoalCard | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const { revealAsync, sealForSubmit } = useVault();
const submitting = ref(false);

const form = useForm({
    investment_asset_id: '',
    title: '',
    target_quantity: '',
    target_date: '',
});

const isEditing = computed(() => (props.goal ?? null) !== null);

watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.reset();
    form.clearErrors();

    const goal = props.goal ?? null;

    if (goal === null) {
        return;
    }

    form.investment_asset_id = String(goal.asset.id);
    form.target_quantity = String(goal.target_quantity);
    form.target_date = goal.target_date;
    // The quantity is already decrypted on the card; the title is not, because
    // nothing on the card needed it as a string until now.
    form.title = (await revealAsync<string>(goal.title, 'savings_goals')) ?? '';
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

        const goal = props.goal ?? null;

        const submission = form.transform(() => ({
            investment_asset_id: form.investment_asset_id,
            target_date: form.target_date,
            ...payload,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                submitting.value = false;
            },
        };

        // SaveGoal leaves `started_on` alone on update, so the pace baseline of
        // an existing goal survives an edit rather than resetting to today.
        if (goal === null) {
            submission.post(storeGoal.url(), options);
        } else {
            submission.put(updateGoal.url(goal.id), options);
        }
    } catch {
        submitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="finance-dialog sm:max-w-[440px]">
            <DialogHeader>
                <DialogTitle>{{
                    isEditing
                        ? t('gamification.goals.edit')
                        : t('gamification.goals.new')
                }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="goal_asset">
                        {{ t('gamification.goals.asset') }}
                    </Label>
                    <Select id="goal_asset" v-model="form.investment_asset_id">
                        <SelectTrigger class="finance-dialog-field w-full">
                            <SelectValue
                                :placeholder="
                                    t('gamification.goals.asset_placeholder')
                                "
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
