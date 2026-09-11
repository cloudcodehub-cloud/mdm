import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function IdentityHeader({
    eyebrow,
    title,
    meta,
    actions,
    className,
}: {
    eyebrow?: ReactNode;
    title: ReactNode;
    meta?: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-start justify-between gap-3',
                className,
            )}
        >
            <div className="min-w-0">
                {eyebrow ? (
                    <p className="text-muted-foreground text-sm">{eyebrow}</p>
                ) : null}
                <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                {meta ? (
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        {meta}
                    </div>
                ) : null}
            </div>
            {actions ? (
                <div className="flex flex-wrap gap-2">{actions}</div>
            ) : null}
        </div>
    );
}
