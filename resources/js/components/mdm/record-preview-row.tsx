import type { ComponentProps, ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export function RecordPreviewRow({
    href,
    children,
    className,
}: {
    href?: ComponentProps<typeof Link>['href'] | null;
    children: ReactNode;
    className?: string;
}) {
    const classes = cn(
        'rounded-md px-1 py-1.5',
        href && 'interactive-row focus-visible:ring-ring/50 -mx-1 flex outline-none focus-visible:ring-2',
        className,
    );

    if (href) {
        return (
            <Link href={href} className={classes}>
                {children}
            </Link>
        );
    }

    return <div className={classes}>{children}</div>;
}
