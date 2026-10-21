import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * MDM 13 §18.8 — operational lists stack as cards on phone; denser tables
 * remain on tablet/desktop with contained horizontal scroll when needed.
 */
export function ResponsiveOpsList({
    cards,
    table,
    className,
}: {
    cards: ReactNode;
    table: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn(className)} data-responsive-ops-list>
            <div
                className="space-y-2 md:hidden"
                data-responsive-ops-cards
            >
                {cards}
            </div>
            <div
                className="hidden overflow-x-auto md:block"
                data-responsive-ops-table
            >
                {table}
            </div>
        </div>
    );
}

export function OpsRecordCard({
    title,
    subtitle,
    meta,
    status,
    onClick,
    children,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    meta?: ReactNode;
    status?: ReactNode;
    onClick?: () => void;
    children?: ReactNode;
}) {
    const interactive = typeof onClick === 'function';

    return (
        <div
            role={interactive ? 'button' : undefined}
            tabIndex={interactive ? 0 : undefined}
            onClick={onClick}
            onKeyDown={
                interactive
                    ? (event) => {
                          if (event.key === 'Enter' || event.key === ' ') {
                              event.preventDefault();
                              onClick();
                          }
                      }
                    : undefined
            }
            className={cn(
                'surface-panel min-w-0 p-3 text-left',
                interactive &&
                    'interactive-surface focus-visible:ring-ring cursor-pointer focus-visible:ring-2 focus-visible:outline-none',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="font-medium break-words">{title}</p>
                    {subtitle ? (
                        <p className="text-muted-foreground mt-0.5 text-sm break-words">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
                {status ? (
                    <div className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                        {status}
                    </div>
                ) : null}
            </div>
            {meta ? (
                <div className="text-muted-foreground mt-2 space-y-0.5 text-xs break-words">
                    {meta}
                </div>
            ) : null}
            {children}
        </div>
    );
}
