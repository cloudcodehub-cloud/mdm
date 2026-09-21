import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { ComponentProps } from 'react';

export function ViewAllLink({
    href,
    label = 'View all',
    className,
}: {
    href: ComponentProps<typeof Link>['href'];
    label?: string;
    className?: string;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'text-primary shrink-0 text-xs font-medium underline-offset-4 hover:underline',
                className,
            )}
        >
            {label} →
        </Link>
    );
}
