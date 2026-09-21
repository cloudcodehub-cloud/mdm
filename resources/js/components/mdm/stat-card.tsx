import type { ComponentProps, ReactNode } from 'react';
import { Inbox } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { ViewAllLink } from '@/components/mdm/view-all-link';
import { cn } from '@/lib/utils';

export function StatCard({
    label,
    value,
    hint,
    href,
}: {
    label: string;
    value: number;
    hint: string;
    href?: string | null;
}) {
    const body = (
        <>
            <p className="text-muted-foreground text-xs tracking-wide uppercase">
                {label}
            </p>
            <p className="mt-2 text-3xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            <p className="text-muted-foreground mt-1 text-xs">{hint}</p>
        </>
    );

    if (href) {
        return (
            <Link
                href={href}
                className="surface-panel interactive-surface block p-4"
            >
                {body}
            </Link>
        );
    }

    return (
        <div className="surface-panel p-4">
            {body}
        </div>
    );
}

export function Panel({
    title,
    description,
    children,
    className,
    viewAllHref,
    viewAllLabel = 'View all',
}: {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
    viewAllHref?: ComponentProps<typeof Link>['href'] | null;
    viewAllLabel?: string;
}) {
    return (
        <section className={cn('surface-panel p-4 md:p-5', className)}>
            <div className={cn('mb-3 flex items-start justify-between gap-3', !description && 'mb-2')}>
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold tracking-tight">
                        {title}
                    </h2>
                    {description && (
                        <p className="text-muted-foreground mt-1 text-xs">
                            {description}
                        </p>
                    )}
                </div>
                {viewAllHref ? (
                    <ViewAllLink href={viewAllHref} label={viewAllLabel} className="mt-0.5" />
                ) : null}
            </div>
            {children}
        </section>
    );
}

export function EmptyState({
    message,
    action,
    compact = false,
}: {
    message: string;
    action?: { href: ComponentProps<typeof Link>['href']; label: string };
    compact?: boolean;
}) {
    if (compact) {
        return (
            <div>
                <p className="text-muted-foreground text-sm">{message}</p>
                {action ? (
                    <p className="mt-2">
                        <Link
                            href={action.href}
                            className="text-primary text-sm font-medium hover:underline"
                        >
                            {action.label}
                        </Link>
                    </p>
                ) : null}
            </div>
        );
    }
    return (
        <div className="text-muted-foreground rounded-lg border border-dashed border-border/80 bg-muted/20 px-4 py-8 text-center">
            <Inbox className="mx-auto mb-2 size-5 opacity-60" aria-hidden="true" />
            <p className="text-sm">{message}</p>
            {action ? (
                <p className="mt-3">
                    <Link
                        href={action.href}
                        className="text-primary text-sm font-medium hover:underline"
                    >
                        {action.label}
                    </Link>
                </p>
            ) : null}
        </div>
    );
}
