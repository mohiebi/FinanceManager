import type { Auth } from '@/types/auth';

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
            locale: 'en' | 'fa';
            dir: 'ltr' | 'rtl';
            calendar: 'gregorian' | 'jalali';
            translations: Record<string, unknown>;
            sidebarOpen: boolean;
            notifications: {
                unread_count: number;
                recent: {
                    id: string;
                    data: { type: string; title: string; body: string; bill_id?: number; due_date?: string };
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
