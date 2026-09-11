import { Link } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { PRODUCT_NAME, PRODUCT_TAGLINE } from '@/lib/brand';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col justify-between p-10 lg:flex">
                <div className="surface-panel absolute inset-4" />
                <Link href={home()} className="relative z-20">
                    <BrandMark variant="horizontal" className="h-10" />
                    <span className="sr-only">{PRODUCT_NAME}</span>
                </Link>
                <p className="text-muted-foreground relative z-20 max-w-sm text-sm">
                    {PRODUCT_TAGLINE}
                </p>
            </div>
            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link
                        href={home()}
                        className="relative z-20 flex items-center justify-center lg:hidden"
                    >
                        <BrandMark variant="stacked" className="h-20" />
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-xl font-medium">{title}</h1>
                        <p className="text-muted-foreground text-sm text-balance">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
