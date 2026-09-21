import type { ReactNode } from 'react';
import { IdentityHeader } from '@/components/mdm/identity-header';
import { Panel } from '@/components/mdm/stat-card';
import { cn } from '@/lib/utils';

export function RecordPage({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-1 flex-col gap-5 p-4 md:p-6',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function RecordHeader({
    eyebrow,
    title,
    meta,
    actions,
    leading,
}: {
    eyebrow?: ReactNode;
    title: ReactNode;
    meta?: ReactNode;
    actions?: ReactNode;
    leading?: ReactNode;
}) {
    return (
        <IdentityHeader
            eyebrow={eyebrow}
            title={title}
            meta={meta}
            actions={actions}
            leading={leading}
        />
    );
}

export function FactGrid({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <dl
            className={cn(
                'grid gap-2 sm:grid-cols-2 xl:grid-cols-4',
                className,
            )}
        >
            {children}
        </dl>
    );
}

export function FactItem({
    label,
    value,
    className,
}: {
    label: string;
    value: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'bg-muted/35 min-w-0 rounded-lg px-3 py-2',
                className,
            )}
        >
            <dt className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                {label}
            </dt>
            <dd className="mt-0.5 break-words text-sm font-medium">
                {value}
            </dd>
        </div>
    );
}

export function AttentionBanner({
    children,
    tone = 'warning',
}: {
    children: ReactNode;
    tone?: 'warning' | 'danger';
}) {
    return (
        <p
            className={cn(
                'rounded-md border px-3 py-2 text-sm',
                tone === 'danger'
                    ? 'border-destructive/40 bg-destructive/10'
                    : 'border-warning/40 bg-warning/10',
            )}
        >
            {children}
        </p>
    );
}

export function ActionGroup({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-wrap items-center gap-2', className)}>
            {children}
        </div>
    );
}

export function RecordSection({
    title,
    description,
    children,
    className,
    compact = false,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
    compact?: boolean;
}) {
    return (
        <Panel
            title={title}
            description={description}
            className={cn(compact && '[&>div:first-child]:mb-2', className)}
        >
            {children}
        </Panel>
    );
}
