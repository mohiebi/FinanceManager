import type { Auth } from '@/types/auth';
import type { SubscriptionState } from '@/types/billing';
import type { FeatureMap } from '@/types/features';
import type { MilesShared } from '@/types/miles';
import type { NotificationData } from '@/types/notifications';
import type { VaultDescriptor } from '@/types/vault';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            locale: 'en' | 'fa' | 'de';
            locales: ('en' | 'fa' | 'de')[];
            dir: 'ltr' | 'rtl';
            calendar: 'gregorian' | 'jalali';
            timezone: string;
            flightTerminologyEnabled: boolean;
            amountMaskDefault: boolean;
            compactFiguresEnabled: boolean;
            translations: Record<string, unknown>;
            features: FeatureMap | null;
            /** Null for guests or while the Miles UI flag is disabled. */
            miles: MilesShared | null;
            milesClaim: MilesClaimed | null;
            /** Null until a vault exists for this user; see Stage 6. */
            vault: VaultDescriptor | null;
            /** Null for guests. */
            subscription: SubscriptionState | null;
            seo: {
                siteName: string;
                title: string;
                description: string;
                canonical: string;
                image: string;
                alternates: {
                    locale: string;
                    url: string;
                }[];
                xDefault: string;
                structuredData: string;
            } | null;
            sidebarOpen: boolean;
            notifications: {
                unread_count: number;
                recent: {
                    id: string;
                    data: NotificationData;
                    read_at: string | null;
                    created_at: string;
                }[];
            } | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
