import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center">
                <AppLogoIcon className="size-8 text-teal-800 dark:text-teal-300" />
            </div>
            <div className="ml-1 grid min-w-0 flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold tracking-tight">
                    {name}
                </span>
                <span className="text-muted-foreground truncate text-[11px] leading-tight">
                    Workforce operations
                </span>
            </div>
        </>
    );
}
