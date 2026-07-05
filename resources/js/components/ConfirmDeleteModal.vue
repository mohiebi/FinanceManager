<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';

defineProps<{
    open: boolean;
    title?: string;
    description?: string;
    processing?: boolean;
    confirmDisabled?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
}>();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                @click.self="emit('update:open', false)"
            >
                <div
                    class="w-full max-w-sm rounded-[22px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.5)] ring-1 ring-white/10"
                >
                    <div class="flex flex-col items-center gap-4 text-center">
                        <div
                            class="flex size-14 items-center justify-center rounded-full bg-[#E94E50]/10"
                        >
                            <Trash2 class="size-6 text-[#E94E50]" />
                        </div>
                        <div>
                            <p class="text-[17px] font-medium text-white">
                                {{ title ?? 'Delete?' }}
                            </p>
                            <p class="mt-1 text-sm text-[#989898]">
                                {{
                                    description ??
                                    'This action cannot be undone.'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-white/10 py-2.5 text-sm font-medium text-white transition hover:bg-white/15"
                            @click="emit('update:open', false)"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-[#E94E50] py-2.5 text-sm font-medium text-white transition hover:bg-[#d43e40] disabled:opacity-50"
                            :disabled="processing || confirmDisabled"
                            @click="emit('confirm')"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
