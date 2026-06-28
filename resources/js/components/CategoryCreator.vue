<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Check, Plus, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeCategory } from '@/routes/categories';

type TransactionType = 'cost' | 'income';
type CreatedCategory = { id: number; type: TransactionType } | null;

const props = defineProps<{
    type: TransactionType;
    fieldClass: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    created: [id: string];
}>();

const { t } = useI18n();
const page = usePage();
const isOpen = ref(false);

const form = useForm({
    type: props.type,
    name: '',
});

watch(
    () => props.type,
    (type) => {
        form.type = type;
        form.clearErrors();
    },
);

function close(): void {
    isOpen.value = false;
    form.reset('name');
    form.clearErrors();
}

function submit(): void {
    form.type = props.type;

    form.post(storeCategory.url(), {
        only: ['categories', 'createdCategory', 'errors'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const createdCategory = page.props
                .createdCategory as CreatedCategory;

            if (createdCategory?.type === props.type) {
                emit('created', String(createdCategory.id));
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
            {{ t('finance.categories.add_custom') }}
        </button>

        <div
            v-else
            class="w-full rounded-[10px] border border-white/10 bg-white/[0.03] p-2"
        >
            <Label class="sr-only" for="new-category-name">
                {{ t('finance.categories.new_name') }}
            </Label>
            <div class="grid gap-2">
                <Input
                    id="new-category-name"
                    v-model="form.name"
                    :class="fieldClass"
                    :placeholder="t('finance.categories.name_placeholder')"
                    autocomplete="off"
                    @keydown.enter.prevent="submit"
                    @keydown.esc.prevent="close"
                />
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
            <InputError :message="form.errors.name || form.errors.type" />
        </div>
    </div>
</template>
