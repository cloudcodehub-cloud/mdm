import { Inbox } from 'lucide-react';
import { Link } from '@inertiajs/react';
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
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('surface-panel p-4 md:p-5', className)}>
            <div className="mb-4">
                <h2 className="text-sm font-semibold tracking-tight">
                    {title}
                </h2>
                {description && (
                    <p className="text-muted-foreground mt-1 text-xs">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

export function EmptyState({
    message,
    action,
}: {
    message: string;
    action?: { href: string; label: string };
}) {
    return (
        <div className="text-muted-foreground rounded-lg border border-dashed border-border/80 bg-muted/20 px-4 py-6 text-center">
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
