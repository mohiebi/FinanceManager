import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type TranslationKey = string;

export type UseNavigationNamingReturn = {
    flightTerminologyEnabled: ComputedRef<boolean>;
    navigationName: (
        standardKey: TranslationKey,
        flightKey?: TranslationKey,
    ) => string;
};

/** Resolve one visible name so standard and flight terminology never mix. */
export function useNavigationNaming(): UseNavigationNamingReturn {
    const page = usePage();
    const { t } = useI18n();
    const flightTerminologyEnabled = computed(
        () => page.props.flightTerminologyEnabled !== false,
    );

    function navigationName(
        standardKey: TranslationKey,
        flightKey?: TranslationKey,
    ): string {
        return t(
            flightTerminologyEnabled.value && flightKey
                ? flightKey
                : standardKey,
        );
    }

    return { flightTerminologyEnabled, navigationName };
}
