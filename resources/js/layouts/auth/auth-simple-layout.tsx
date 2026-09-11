import { Link } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { PRODUCT_NAME, PRODUCT_TAGLINE } from '@/lib/brand';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div className="relative w-full max-w-md">
                <div className="surface-panel px-6 py-8 md:px-8">
                    <div className="mb-8 flex flex-col items-center gap-4 text-center">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-3"
                        >
                            <BrandMark
                                variant="stacked"
                                className="h-28 w-auto"
                            />
                            <span className="sr-only">{PRODUCT_NAME}</span>
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
                    <p className="text-muted-foreground mt-8 text-center text-xs">
                        {PRODUCT_TAGLINE}
                    </p>
                </div>
            </div>
        </div>
    );
}
