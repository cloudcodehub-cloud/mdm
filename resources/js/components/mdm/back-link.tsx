import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { ComponentProps } from 'react';

export function BackLink({
    href,
    children,
    className,
}: {
    href: ComponentProps<typeof Link>['href'];
    children: ReactNode;
    className?: string;
}) {
    return (
        <Link
            href={href}
            className={cn('hover:text-foreground text-muted-foreground', className)}
        >
            {children}
        </Link>
    );
}
