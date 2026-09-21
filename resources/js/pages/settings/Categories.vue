<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    GripVertical,
    Pencil,
    Plus,
    Tags,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { orderByParent } from '@/lib/categories';
import {
    destroy as destroyCategory,
    reorder as reorderCategories,
    store as storeCategory,
    update as updateCategoryRoute,
} from '@/routes/categories';

type TransactionType = 'cost' | 'income';

type ParentCandidate = {
    id: number;
    name: string;
    slug: string;
    type: TransactionType;
    for_both_types: boolean;
    parent_id: number | null;
    color: string | null;
    is_default: boolean;
};

type Category = ParentCandidate & {
    transactions_count: number;
};

/** A row of a type's list: a category, or the heading over subcategories
 *  whose parent is not in that list (a default, or a shared category that
 *  lives under the other type). */
type ListRow =
    | (Category & { kind: 'category'; depth: 0 | 1 })
    | { kind: 'heading'; id: string; parentName: string };

/** reka's Select cannot hold an empty-string value, so "top level" needs one. */
const NO_PARENT = 'none';

const props = defineProps<{
    categories: Record<TransactionType, Category[]>;
    parentCandidates: ParentCandidate[];
}>();

const { t } = useI18n();
const page = usePage();
const editingId = ref<number | null>(null);
const deleteTarget = ref<Category | null>(null);
const draggingId = ref<number | null>(null);
const dragOverId = ref<number | null>(null);

const presetColors = [
    '#02CD86',
    '#6C4EE9',
    '#E94E50',
    '#F59E0B',
    '#0EA5E9',
    '#EC4899',
];
const defaultColor = presetColors[0];

const createForm = useForm({
    type: 'cost' as TransactionType,
    name: '',
    color: defaultColor,
    parent_id: NO_PARENT,
    for_both_types: false,
});

const editForm = useForm({
    name: '',
    parent_id: NO_PARENT,
    for_both_types: false,
});

const allCategories = computed(() => [
    ...(props.categories.cost ?? []),
    ...(props.categories.income ?? []),
]);

const editingCategory = computed(
    () =>
        allCategories.value.find(
            (category) => category.id === editingId.value,
        ) ?? null,
);

/**
 * Mirrors CategoryRules on the server: a parent is top-level, never the
 * category itself, and covers every type the category will be used for.
 */
function eligibleParents(
    type: TransactionType,
    forBothTypes: boolean,
    selfId: number | null = null,
): ParentCandidate[] {
    return props.parentCandidates.filter(
        (parent) =>
            parent.id !== selfId &&
            parent.parent_id === null &&
            (forBothTypes
                ? parent.for_both_types
                : parent.type === type || parent.for_both_types),
    );
}

const createParentOptions = computed(() =>
    eligibleParents(createForm.type, createForm.for_both_types),
);

const editParentOptions = computed(() =>
    editingCategory.value === null
        ? []
        : eligibleParents(
              editingCategory.value.type,
              editForm.for_both_types,
              editingCategory.value.id,
          ),
);

function hasChildren(category: Category): boolean {
    return allCategories.value.some((other) => other.parent_id === category.id);
}

// Changing the type or the sharing can rule the chosen parent out; falling
// back to top level beats submitting a pairing the server will refuse.
watch(createParentOptions, (options) => {
    if (!options.some((parent) => String(parent.id) === createForm.parent_id)) {
        createForm.parent_id = NO_PARENT;
    }
});

watch(editParentOptions, (options) => {
    if (!options.some((parent) => String(parent.id) === editForm.parent_id)) {
        editForm.parent_id = NO_PARENT;
    }
});

function parentPayload(value: string): number | null {
    return value === NO_PARENT ? null : Number(value);
}

function parentName(parentId: number): string {
    return (
        props.parentCandidates.find((parent) => parent.id === parentId)?.name ??
        ''
    );
}

/**
 * Parents with their subcategories beneath. A subcategory whose parent is not
 * in this list is gathered under a read-only heading naming that parent,
 * rather than shown as though it were top-level.
 */
function rowsFor(list: Category[]): ListRow[] {
    const ordered = orderByParent(list);
    const underOutsideParent = ordered.filter(
        (category) => category.depth === 0 && category.parent_id !== null,
    );

    const rows: ListRow[] = ordered
        .filter(
            (category) => category.depth === 1 || category.parent_id === null,
        )
        .map((category) => ({ ...category, kind: 'category' }));

    const outsideParentIds = [
        ...new Set(
            underOutsideParent.flatMap((category) =>
                category.parent_id === null ? [] : [category.parent_id],
            ),
        ),
    ];

    for (const parentId of outsideParentIds) {
        rows.push({
            kind: 'heading',
            id: `under-${parentId}`,
            parentName: parentName(parentId),
        });

        for (const category of underOutsideParent) {
            if (category.parent_id === parentId) {
                rows.push({ ...category, kind: 'category', depth: 1 });
            }
        }
    }

    return rows;
}

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
        rows: rowsFor(props.categories.cost ?? []),
    },
    {
        type: 'income' as TransactionType,
        title: t('finance.filters.incomes'),
        categories: props.categories.income ?? [],
        rows: rowsFor(props.categories.income ?? []),
    },
]);

function createCategory(): void {
    // The parent and sharing are kept after a save, so a run of subcategories
    // can go under the same parent without re-picking it each time.
    createForm
        .transform((data) => ({
            ...data,
            parent_id: parentPayload(data.parent_id),
        }))
        .post(storeCategory.url(), {
            preserveScroll: true,
            onSuccess: () => createForm.reset('name', 'color'),
        });
}

function startEdit(category: Category): void {
    editingId.value = category.id;
    editForm.clearErrors();
    editForm.name = category.name;
    editForm.parent_id =
        category.parent_id === null ? NO_PARENT : String(category.parent_id);
    editForm.for_both_types = category.for_both_types;
}

function cancelEdit(): void {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function updateCategory(category: Category): void {
    editForm
        .transform((data) => ({
            ...data,
            color: category.color,
            parent_id: parentPayload(data.parent_id),
        }))
        .patch(updateCategoryRoute.url(category.id), {
            preserveScroll: true,
            onSuccess: cancelEdit,
        });
}

function updateColor(category: Category, color: string): void {
    router.patch(
        updateCategoryRoute.url(category.id),
        { name: category.name, color },
        { preserveScroll: true, preserveState: true },
    );
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

function handleDragStart(event: DragEvent, category: Category): void {
    draggingId.value = category.id;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(category.id));
    }
}

function handleDragEnter(category: Category): void {
    if (draggingId.value !== null && draggingId.value !== category.id) {
        dragOverId.value = category.id;
    }
}

function handleDragEnd(): void {
    draggingId.value = null;
    dragOverId.value = null;
}

function handleDrop(type: TransactionType, targetCategory: Category): void {
    const sourceId = draggingId.value;
    dragOverId.value = null;
    draggingId.value = null;

    if (sourceId === null || sourceId === targetCategory.id) {
        return;
    }

    const list = props.categories[type] ?? [];
    const source = list.find((category) => category.id === sourceId);

    // Reordering only ever happens among siblings: dragging a subcategory onto
    // another parent's row would read as moving it there, which it does not do.
    if (source === undefined || source.parent_id !== targetCategory.parent_id) {
        return;
    }

    // The whole list goes back in display order, so sort_order follows what
    // the user sees; siblings stay contiguous, so moving within them is safe.
    const ids = rowsFor(list).flatMap((row) =>
        row.kind === 'category' ? [row.id] : [],
    );
    const fromIndex = ids.indexOf(sourceId);
    const toIndex = ids.indexOf(targetCategory.id);

    if (fromIndex === -1 || toIndex === -1) {
        return;
    }

    ids.splice(toIndex, 0, ids.splice(fromIndex, 1)[0]);

    router.patch(
        reorderCategories.url(),
        { type, ids },
        { preserveScroll: true, preserveState: true },
    );
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
            :icon="Plus"
            :title="t('settings.categories.eyebrow')"
            :description="t('settings.categories.description')"
        >
            <form class="space-y-3" @submit.prevent="createCategory">
                <div
                    class="grid gap-3 sm:grid-cols-[140px_1fr_auto_auto] sm:items-end"
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
                    <div class="grid gap-2">
                        <Label class="finance-dialog-label">
                            {{ t('settings.categories.color') }}
                        </Label>
                        <div class="flex h-9 items-center gap-1.5">
                            <button
                                v-for="c in presetColors"
                                :key="c"
                                type="button"
                                :style="{ backgroundColor: c }"
                                :class="[
                                    'size-6 shrink-0 cursor-pointer rounded-full transition-transform hover:scale-110',
                                    createForm.color === c
                                        ? 'scale-110 ring-2 ring-white ring-offset-1 ring-offset-[#1a1a1a]'
                                        : '',
                                ]"
                                @click="createForm.color = c"
                            />
                            <label
                                class="relative size-6 shrink-0 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20"
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
                    <Button
                        class="h-9 bg-[#02CD86] text-[#101010] hover:bg-[#08dd93]"
                        :disabled="createForm.processing"
                    >
                        <Spinner v-if="createForm.processing" />
                        <Plus v-else class="size-4" />
                        {{ t('settings.categories.add') }}
                    </Button>
                </div>
                <div
                    class="grid gap-3 sm:grid-cols-[minmax(0,260px)_auto] sm:items-end"
                >
                    <div class="grid gap-2">
                        <Label
                            class="finance-dialog-label"
                            for="category_parent"
                        >
                            {{ t('settings.categories.parent') }}
                        </Label>
                        <Select v-model="createForm.parent_id">
                            <SelectTrigger
                                id="category_parent"
                                class="finance-dialog-field finance-dialog-field-income"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent
                                class="finance-dialog-select-content"
                            >
                                <SelectItem :value="NO_PARENT">
                                    {{ t('settings.categories.no_parent') }}
                                </SelectItem>
                                <SelectItem
                                    v-for="parent in createParentOptions"
                                    :key="parent.id"
                                    :value="String(parent.id)"
                                >
                                    {{ parent.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <label
                        for="category_for_both_types"
                        class="inline-flex h-9 cursor-pointer items-center gap-3 rounded-xl bg-white/[0.035] px-4 ring-1 ring-white/10 transition-colors hover:bg-white/[0.06]"
                    >
                        <Checkbox
                            id="category_for_both_types"
                            :checked="createForm.for_both_types"
                            @update:checked="
                                createForm.for_both_types = $event === true
                            "
                        />
                        <span class="text-sm text-white">
                            {{ t('settings.categories.for_both_types') }}
                        </span>
                    </label>
                </div>
                <div class="flex items-center gap-3">
                    <InputError
                        :message="
                            createForm.errors.type ||
                            createForm.errors.name ||
                            createForm.errors.color ||
                            createForm.errors.parent_id ||
                            createForm.errors.for_both_types
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

        <SettingsSection :icon="Tags" :title="t('settings.categories.title')">
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
                        <template
                            v-for="category in group.rows"
                            :key="`${category.kind}-${category.id}`"
                        >
                            <p
                                v-if="category.kind === 'heading'"
                                class="px-1 pt-2 text-xs font-medium text-[#989898]"
                            >
                                {{
                                    t('settings.categories.under_parent', {
                                        name: category.parentName,
                                    })
                                }}
                            </p>
                            <div
                                v-else
                                draggable="true"
                                class="flex items-center gap-2 rounded-xl bg-[#252525] px-3 py-3 ring-1 ring-white/10 transition"
                                :class="[
                                    category.depth === 1 ? 'ms-6' : '',
                                    draggingId === category.id
                                        ? 'opacity-40'
                                        : '',
                                    dragOverId === category.id
                                        ? 'ring-2 ring-[#02CD86]'
                                        : '',
                                ]"
                                @dragstart="handleDragStart($event, category)"
                                @dragenter.prevent="handleDragEnter(category)"
                                @dragover.prevent
                                @dragend="handleDragEnd"
                                @drop.prevent="handleDrop(group.type, category)"
                            >
                                <span
                                    class="shrink-0 cursor-grab touch-none text-[#6b6b6b] active:cursor-grabbing"
                                    :title="
                                        t('settings.categories.drag_to_reorder')
                                    "
                                >
                                    <GripVertical class="size-4" />
                                </span>

                                <label
                                    class="relative size-6 shrink-0 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20"
                                    :style="{
                                        backgroundColor:
                                            category.color ?? defaultColor,
                                    }"
                                    :title="t('settings.categories.color')"
                                >
                                    <input
                                        :value="category.color ?? defaultColor"
                                        type="color"
                                        class="absolute inset-0 cursor-pointer opacity-0"
                                        @change="
                                            updateColor(
                                                category,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).value,
                                            )
                                        "
                                    />
                                </label>

                                <form
                                    v-if="editingId === category.id"
                                    class="min-w-0 flex-1 space-y-2"
                                    @submit.prevent="updateCategory(category)"
                                >
                                    <Input
                                        v-model="editForm.name"
                                        class="finance-dialog-field finance-dialog-field-income"
                                    />
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <Select
                                            v-model="editForm.parent_id"
                                            :disabled="hasChildren(category)"
                                        >
                                            <SelectTrigger
                                                class="finance-dialog-field finance-dialog-field-income"
                                                :aria-label="
                                                    t(
                                                        'settings.categories.parent',
                                                    )
                                                "
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent
                                                class="finance-dialog-select-content"
                                            >
                                                <SelectItem :value="NO_PARENT">
                                                    {{
                                                        t(
                                                            'settings.categories.no_parent',
                                                        )
                                                    }}
                                                </SelectItem>
                                                <SelectItem
                                                    v-for="parent in editParentOptions"
                                                    :key="parent.id"
                                                    :value="String(parent.id)"
                                                >
                                                    {{ parent.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <label
                                            for="edit_category_for_both_types"
                                            class="inline-flex h-9 cursor-pointer items-center gap-3 rounded-xl bg-white/[0.035] px-4 ring-1 ring-white/10 transition-colors hover:bg-white/[0.06]"
                                        >
                                            <Checkbox
                                                id="edit_category_for_both_types"
                                                :checked="
                                                    editForm.for_both_types
                                                "
                                                @update:checked="
                                                    editForm.for_both_types =
                                                        $event === true
                                                "
                                            />
                                            <span class="text-sm text-white">
                                                {{
                                                    t(
                                                        'settings.categories.for_both_types',
                                                    )
                                                }}
                                            </span>
                                        </label>
                                    </div>
                                    <p
                                        v-if="hasChildren(category)"
                                        class="text-xs text-[#989898]"
                                    >
                                        {{
                                            t(
                                                'settings.categories.has_children_hint',
                                            )
                                        }}
                                    </p>
                                    <InputError
                                        :message="
                                            editForm.errors.name ||
                                            editForm.errors.parent_id ||
                                            editForm.errors.for_both_types
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
                                            <Spinner
                                                v-if="editForm.processing"
                                            />
                                            <Check v-else class="size-4" />
                                            {{ t('common.save') }}
                                        </Button>
                                    </div>
                                </form>

                                <template v-else>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-medium text-white"
                                        >
                                            {{ category.name }}
                                        </p>
                                        <p
                                            class="flex flex-wrap items-center gap-2 text-xs text-[#989898]"
                                        >
                                            {{
                                                t('settings.categories.usage', {
                                                    count: category.transactions_count,
                                                })
                                            }}
                                            <span
                                                v-if="category.for_both_types"
                                                class="rounded-full bg-[#02CD86]/10 px-2 py-0.5 text-[10px] font-medium text-[#02CD86]"
                                            >
                                                {{
                                                    t(
                                                        'settings.categories.both_types_badge',
                                                    )
                                                }}
                                            </span>
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
                                </template>
                            </div>
                        </template>
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
