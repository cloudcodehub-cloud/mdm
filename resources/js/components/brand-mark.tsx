import { brandAssets, PRODUCT_NAME } from '@/lib/brand';
import { cn } from '@/lib/utils';

type BrandVariant = keyof typeof brandAssets;

export function BrandMark({
    variant,
    className,
    alt = PRODUCT_NAME,
}: {
    variant: BrandVariant;
    className?: string;
    alt?: string;
}) {
    return (
        <img
            src={brandAssets[variant]}
            alt={alt}
            className={cn('h-auto w-auto max-w-full object-contain', className)}
        />
    );
}
