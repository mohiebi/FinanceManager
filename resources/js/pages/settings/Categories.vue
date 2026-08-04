<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
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
import {
    destroy as destroyCategory,
    store as storeCategory,
    update as updateCategoryRoute,
} from '@/routes/categories';

type TransactionType = 'cost' | 'income';

type Category = {
    id: number;
    name: string;
    slug: string;
    type: TransactionType;
    is_default: boolean;
    transactions_count: number;
};

const props = defineProps<{
    categories: Record<TransactionType, Category[]>;
}>();

const { t } = useI18n();
const page = usePage();
const editingId = ref<number | null>(null);
const deleteTarget = ref<Category | null>(null);

const createForm = useForm({
    type: 'cost' as TransactionType,
    name: '',
});

const editForm = useForm({
    name: '',
});

const deleteError = computed(() => {
    const errors = page.props.errors as
        | Record<string, string | undefined>
        | undefined;

    return errors?.category;
});

const groups = computed(() => [
    {
        type: 'cost' as TransactionType,
        title: t('finance.filters.costs'),
        categories: props.categories.cost ?? [],
    },
    {
        type: 'income' as TransactionType,
        title: t('finance.filters.incomes'),
        categories: props.categories.income ?? [],
    },
]);

function createCategory(): void {
    createForm.post(storeCategory.url(), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name'),
    });
}

function startEdit(category: Category): void {
    editingId.value = category.id;
    editForm.clearErrors();
    editForm.name = category.name;
}

function cancelEdit(): void {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function updateCategory(category: Category): void {
    editForm.patch(updateCategoryRoute.url(category.id), {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
}

function confirmDelete(): void {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(destroyCategory.url(deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Category settings', href: '/settings/categories' },
        ],
    },
});
</script>

<template>
    <Head :title="t('settings.categories.title')" />

    <div class="flex flex-col gap-[18px]">
        <SettingsSection
            :title="t('settings.categories.eyebrow')"
            :description="t('settings.categories.description')"
        >
            <form class="space-y-3" @submit.prevent="createCategory">
                <div
                    class="grid gap-3 sm:grid-cols-[140px_1fr_auto] sm:items-end"
                >
                    <div class="grid gap-2">
                        <Label class="finance-dialog-label" for="category_type">
                            {{ t('finance.fields.type') }}
                        </Label>
                        <Select id="category_type" v-model="createForm.type">
                            <SelectTrigger
                                class="finance-dialog-field finance-dialog-field-income"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent
                                class="finance-dialog-select-content"
                            >
                                <SelectItem value="cost">
                                    {{ t('finance.filters.costs') }}
                                </SelectItem>
                                <SelectItem value="income">
                                    {{ t('finance.filters.incomes') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label class="finance-dialog-label" for="category_name">
                            {{ t('settings.categories.name') }}
                        </Label>
                        <Input
                            id="category_name"
                            v-model="createForm.name"
                            class="finance-dialog-field finance-dialog-field-income"
                            :placeholder="
                                t('settings.categories.name_placeholder')
                            "
                        />
                    </div>
                    <Button
                        class="h-9 bg-[#02CD86] text-[#101010] hover:bg-[#08dd93]"
                        :disabled="createForm.processing"
                    >
                        <Spinner v-if="createForm.processing" />
                        <Plus v-else class="size-4" />
                        {{ t('settings.categories.add') }}
                    </Button>
                </div>
                <div class="flex items-center gap-3">
                    <InputError
                        :message="
                            createForm.errors.type || createForm.errors.name
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
            </form>
        </SettingsSection>

        <SettingsSection :title="t('settings.categories.title')">
            <InputError :message="deleteError" />

            <div class="space-y-6">
                <section
                    v-for="(group, groupIndex) in groups"
                    :key="group.type"
                    class="space-y-3"
                    :class="
                        groupIndex === 0 ? '' : 'border-t border-white/10 pt-5'
                    "
                >
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-medium text-white">
                            {{ group.title }}
                        </h2>
                        <span class="text-xs text-[#989898]">
                            {{ group.categories.length }}
                        </span>
                    </div>

                    <div v-if="group.categories.length > 0" class="space-y-2">
                        <div
                            v-for="category in group.categories"
                            :key="category.id"
                            class="rounded-xl bg-[#252525] px-3 py-3 ring-1 ring-white/10"
                        >
                            <form
                                v-if="editingId === category.id"
                                class="space-y-2"
                                @submit.prevent="updateCategory(category)"
                            >
                                <Input
                                    v-model="editForm.name"
                                    class="finance-dialog-field finance-dialog-field-income"
                                />
                                <InputError :message="editForm.errors.name" />
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
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-medium text-white"
                                    >
                                        {{ category.name }}
                                    </p>
                                    <p class="text-xs text-[#989898]">
                                        {{
                                            t('settings.categories.usage', {
                                                count: category.transactions_count,
                                            })
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    class="h-9 w-9 shrink-0 bg-white/5 p-0 text-white/70 shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white"
                                    :title="t('common.edit')"
                                    @click="startEdit(category)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    class="h-9 w-9 shrink-0 bg-[#E94E50]/10 p-0 text-[#E94E50] shadow-none ring-1 ring-[#E94E50]/20 hover:bg-[#E94E50]/20"
                                    :title="t('common.delete')"
                                    @click="deleteTarget = category"
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
                        {{ t('settings.categories.empty') }}
                    </p>
                </section>
            </div>
        </SettingsSection>

        <ConfirmDeleteModal
            :open="deleteTarget !== null"
            :title="
                t('settings.categories.delete_title', {
                    name: deleteTarget?.name ?? '',
                })
            "
            :description="t('settings.categories.delete_description')"
            @update:open="deleteTarget = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
