<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        invalid?: boolean;
        disabled?: boolean;
    }>(),
    {
        modelValue: '',
        invalid: false,
        disabled: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const digits = ref<string[]>(Array.from({ length: 6 }, () => ''));
const inputRefs = ref<Array<HTMLInputElement | null>>([]);

const slotClass = computed(() =>
    cn(
        'auth-otp-slot text-center text-sm font-medium transition outline-none',
        props.invalid ? 'border-red-300' : 'border-[#e7e0ff]',
    ),
);

const syncDigitsFromValue = (value: string): void => {
    const cleanValue = value.replace(/\D/g, '').slice(0, 6);

    digits.value = Array.from(
        { length: 6 },
        (_, index) => cleanValue[index] ?? '',
    );
};

watch(
    () => props.modelValue,
    (value) => {
        syncDigitsFromValue(value ?? '');
    },
    { immediate: true },
);

const emitValue = (): void => {
    emit('update:modelValue', digits.value.join(''));
};

const focusInput = async (index: number): Promise<void> => {
    await nextTick();
    inputRefs.value[index]?.focus();
    inputRefs.value[index]?.select();
};

const setInputRef = (element: HTMLInputElement | null, index: number): void => {
    inputRefs.value[index] = element;
};

const handleInput = async (index: number, event: Event): Promise<void> => {
    const target = event.target as HTMLInputElement;
    const cleanValue = target.value.replace(/\D/g, '');

    if (cleanValue === '') {
        digits.value[index] = '';
        emitValue();
        return;
    }

    if (cleanValue.length > 1) {
        const pastedDigits = cleanValue.slice(0, 6).split('');

        digits.value = Array.from(
            { length: 6 },
            (_, digitIndex) => pastedDigits[digitIndex] ?? '',
        );
        emitValue();

        await focusInput(Math.min(pastedDigits.length, 5));
        return;
    }

    digits.value[index] = cleanValue;
    emitValue();

    if (index < 5) {
        await focusInput(index + 1);
    }
};

const handleKeydown = async (
    index: number,
    event: KeyboardEvent,
): Promise<void> => {
    const target = event.target as HTMLInputElement;

    if (event.key === 'Backspace' && target.value === '' && index > 0) {
        digits.value[index - 1] = '';
        emitValue();
        event.preventDefault();
        await focusInput(index - 1);
        return;
    }

    if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        await focusInput(index - 1);
        return;
    }

    if (event.key === 'ArrowRight' && index < 5) {
        event.preventDefault();
        await focusInput(index + 1);
    }
};

const handlePaste = async (event: ClipboardEvent): Promise<void> => {
    const pastedValue =
        event.clipboardData?.getData('text')?.replace(/\D/g, '').slice(0, 6) ??
        '';

    if (pastedValue === '') {
        return;
    }

    event.preventDefault();
    syncDigitsFromValue(pastedValue);
    emitValue();
    await focusInput(Math.min(pastedValue.length, 5));
};
</script>

<template>
    <div class="flex items-center justify-center gap-2" @paste="handlePaste">
        <input
            v-for="(_, index) in digits"
            :key="index"
            :ref="
                (element) =>
                    setInputRef(element as HTMLInputElement | null, index)
            "
            :value="digits[index]"
            :class="slotClass"
            :aria-label="`Verification digit ${index + 1}`"
            :aria-invalid="invalid ? 'true' : undefined"
            :disabled="disabled"
            :autofocus="index === 0"
            inputmode="numeric"
            maxlength="1"
            pattern="[0-9]*"
            autocomplete="one-time-code"
            @input="handleInput(index, $event)"
            @keydown="handleKeydown(index, $event)"
        />
    </div>
</template>
