import { usePage } from '@inertiajs/react';

export type ModuleAccent =
    | 'dashboard'
    | 'employees'
    | 'clients'
    | 'visits'
    | 'compliance'
    | 'reports'
    | 'messages'
    | 'core';

export function moduleAccentFromPath(pathname: string): ModuleAccent {
    if (pathname.startsWith('/employees') || pathname.startsWith('/supervisors')) {
        return 'employees';
    }

    if (pathname.startsWith('/clients')) {
        return 'clients';
    }

    if (
        pathname.startsWith('/scheduled-visits') ||
        pathname.startsWith('/visits') ||
        pathname.startsWith('/operations') ||
        pathname.startsWith('/attendance') ||
        pathname.startsWith('/visit-exceptions')
    ) {
        return 'visits';
    }

    if (pathname.startsWith('/compliance')) {
        return 'compliance';
    }

    if (pathname.startsWith('/reports')) {
        return 'reports';
    }

    if (
        pathname.startsWith('/messages') ||
        pathname.startsWith('/announcements')
    ) {
        return 'messages';
    }

    return 'dashboard';
}

export function useModuleAccent(): ModuleAccent {
    const page = usePage();
    const pathname = new URL(
        page.url,
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost',
    ).pathname;

    return moduleAccentFromPath(pathname);
}
