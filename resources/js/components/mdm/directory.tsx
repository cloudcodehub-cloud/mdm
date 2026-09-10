import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export const controlClassName = cn(
    'border-input file:text-foreground placeholder:text-muted-foreground flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
);

export function StatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    const tone =
        status === 'active' || status === 'completed'
            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
            : status === 'terminated' ||
                status === 'discharged' ||
                status === 'cancelled' ||
                status === 'revoked' ||
                status === 'expired'
              ? 'bg-destructive/10 text-destructive'
              : 'bg-amber-500/10 text-amber-800 dark:text-amber-200';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium capitalize',
                tone,
            )}
        >
            {label}
        </span>
    );
}

export function ModuleTabs({
    tabs,
    value,
    onChange,
}: {
    tabs: Array<{ id: string; label: string }>;
    value: string;
    onChange: (id: string) => void;
}) {
    return (
        <div className="flex flex-wrap gap-1 rounded-lg bg-muted/70 p-1">
            {tabs.map((tab) => (
                <button
                    key={tab.id}
                    type="button"
                    onClick={() => onChange(tab.id)}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm transition-colors',
                        value === tab.id
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}

export function Field({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <label htmlFor={htmlFor} className="text-sm font-medium">
                {label}
            </label>
            {children}
            {error ? (
                <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
            ) : null}
        </div>
    );
}

export function Pagination({
    meta,
    links,
}: {
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    links: { prev: string | null; next: string | null };
}) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="text-muted-foreground flex items-center justify-between gap-3 text-sm">
            <p>
                Showing {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
            </p>
            <div className="flex gap-2">
                {links.prev ? (
                    <Link href={links.prev} className="hover:text-foreground">
                        Previous
                    </Link>
                ) : (
                    <span className="opacity-50">Previous</span>
                )}
                {links.next ? (
                    <Link href={links.next} className="hover:text-foreground">
                        Next
                    </Link>
                ) : (
                    <span className="opacity-50">Next</span>
                )}
            </div>
        </div>
    );
}
