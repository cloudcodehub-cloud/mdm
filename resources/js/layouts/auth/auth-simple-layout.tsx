import { Link } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { PRODUCT_NAME, PRODUCT_TAGLINE } from '@/lib/brand';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-dvh flex-col items-center justify-center px-5 py-4 md:px-8 md:py-5">
            <div className="relative w-full max-w-md">
                <div className="surface-panel px-6 py-6 md:px-8">
                    <div
                        className={cn(
                            'mb-5 flex flex-col items-center text-center',
                            title ? 'gap-3' : 'gap-2',
                        )}
                    >
                        <Link
                            href={home()}
                            className="flex flex-col items-center"
                        >
                            <BrandMark
                                variant="noTagline"
                                className="mx-auto h-auto w-[11.5rem] max-w-full sm:w-[13.5rem]"
                            />
                            <span className="sr-only">{PRODUCT_NAME}</span>
                        </Link>
                        <div className="space-y-1">
                            {title ? (
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {title}
                                </h1>
                            ) : null}
                            {description ? (
                                <p className="text-muted-foreground text-sm">
                                    {description}
                                </p>
                            ) : null}
                        </div>
                    </div>
                    {children}
                    <p className="text-muted-foreground mt-5 text-center text-xs">
                        {PRODUCT_TAGLINE}
                    </p>
                </div>
            </div>
        </div>
    );
}
