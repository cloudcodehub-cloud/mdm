export type AppRole = 'ADMIN' | 'SUPERVISOR' | 'DSP';

export type User = {
    id: number;
    name: string;
    email: string;
    role: AppRole;
    appearance?: 'system' | 'light' | 'dark';
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type OrganizationSettings = {
    organization_name: string;
    timezone: string;
    timezone_label: string;
    date_format: string;
    time_format: string;
    first_day_of_week: number;
};

export type DemoWeather = {
    source: string;
    location: string;
    condition: string;
    temperature: string;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
