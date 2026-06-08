export type User = {
    id: number;
    name: string;
    email: string;
    birthdate: string | null;
    locale: 'en' | 'fa';
    calendar: 'gregorian' | 'jalali';
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
