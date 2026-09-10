import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-slate-50 p-6 md:p-10 dark:bg-slate-950">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(15,118,110,0.08),_transparent_55%)]" />
            <div className="relative w-full max-w-md">
                <div className="surface-panel px-6 py-8 md:px-8">
                    <div className="mb-8 flex flex-col items-center gap-3 text-center">
                        <Link href={home()} className="flex flex-col items-center gap-3">
                            <AppLogoIcon className="size-11 text-teal-800 dark:text-teal-300" />
                            <span className="text-sm font-semibold tracking-tight">
                                MDM - Magic Data Management
                            </span>
                        </Link>
                        <div className="space-y-1">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
