import AppLogoIcon from '@/components/app-logo-icon';
import { PRODUCT_SHORT_NAME, PRODUCT_SUBTITLE } from '@/lib/brand';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center">
                <AppLogoIcon className="size-8" />
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
