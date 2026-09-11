import type { Auth, DemoWeather, OrganizationSettings } from '@/types/auth';
import type { DashboardActiveVisit } from '@/types/dashboard';
import type { InboxActivity } from '@/types/messaging';

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
            inbox: InboxActivity;
            activeWork: DashboardActiveVisit | null;
            [key: string]: unknown;
        };
    }
}
