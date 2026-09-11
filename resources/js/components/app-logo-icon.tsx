import { BrandMark } from '@/components/brand-mark';
import { cn } from '@/lib/utils';

export default function AppLogoIcon({ className }: { className?: string }) {
    return (
        <BrandMark
            variant="icon"
            alt=""
            className={cn('size-8', className)}
        />
    );
}
