import AppLogoIcon from '@/components/app-logo-icon';
import { PRODUCT_SHORT_NAME, PRODUCT_SUBTITLE } from '@/lib/brand';
import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const organization = usePage().props.organization as
        | {
              shell_logo_url?: string | null;
              logo_url?: string | null;
          }
        | undefined;
    // MDM 11-06: Horizontal → Primary → MDM mark for shell/header.
    const logoUrl = organization?.shell_logo_url ?? organization?.logo_url;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden">
                {logoUrl ? (
                    <img
                        src={logoUrl}
                        alt=""
                        className="size-8 object-contain"
                    />
                ) : (
                    <AppLogoIcon className="size-8" />
                )}
            </div>
            <div className="ml-1 grid min-w-0 flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
                <span className="truncate leading-tight font-semibold tracking-tight">
                    {PRODUCT_SHORT_NAME}
                </span>
                <span className="text-muted-foreground truncate text-[11px] leading-tight">
                    {PRODUCT_SUBTITLE}
                </span>
            </div>
        </>
    );
}
