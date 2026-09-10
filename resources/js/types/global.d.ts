import type { Auth, DemoWeather, OrganizationSettings } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            organization: OrganizationSettings;
            sidebarOpen: boolean;
            demoWeather: DemoWeather;
            [key: string]: unknown;
        };
    }
}
