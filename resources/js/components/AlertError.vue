<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type Props = {
    errors: string[];
    title?: string;
};

const props = defineProps<Props>();
const { t } = useI18n();

const uniqueErrors = computed(() => Array.from(new Set(props.errors)));
const displayTitle = computed(
    () => props.title ?? t('buttons.something_went_wrong'),
);
</script>

<template>
    <Alert variant="destructive">
        <AlertCircle class="size-4" />
        <AlertTitle>{{ displayTitle }}</AlertTitle>
        <AlertDescription>
            <ul class="list-inside list-disc text-sm">
                <li v-for="(error, index) in uniqueErrors" :key="index">
                    {{ error }}
                </li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
