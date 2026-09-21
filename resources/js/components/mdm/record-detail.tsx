import type { ReactNode } from 'react';
import { IdentityHeader } from '@/components/mdm/identity-header';
import { Panel } from '@/components/mdm/stat-card';
import { cn } from '@/lib/utils';

export function RecordPage({
    children,
    className,
    wide = false,
}: {
    children: ReactNode;
    className?: string;
    wide?: boolean;
}) {
    return (
        <div
            className={cn(
                wide ? 'page-shell-wide' : 'page-shell',
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
                'grid gap-4 sm:grid-cols-2 lg:grid-cols-3',
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
        <div className={cn('min-w-0', className)}>
            <dt className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                {label}
            </dt>
            <dd className="mt-0.5 break-words text-sm font-medium">{value}</dd>
        </div>
    );
}

export function ContextStrip({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'surface-panel grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 md:p-5',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function ContextGroup({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="min-w-0">
            <p className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                {label}
            </p>
            <div className="mt-1 text-sm font-medium">{children}</div>
        </div>
    );
}

export function StatusPills({ children }: { children: ReactNode }) {
    return (
        <div className="mt-3 flex flex-wrap items-center gap-1.5">{children}</div>
    );
}

export function RecordHero({
    eyebrow,
    title,
    subtitle,
    chips,
    aside,
    children,
    className,
}: {
    eyebrow?: ReactNode;
    title: ReactNode;
    subtitle?: ReactNode;
    chips?: ReactNode;
    aside?: ReactNode;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'surface-panel border-primary/20 from-primary/8 via-card to-brand-cyan/12 bg-gradient-to-br p-4 md:p-6',
                className,
            )}
        >
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    {eyebrow ? (
                        <p className="text-primary text-xs font-medium tracking-wide uppercase">
                            {eyebrow}
                        </p>
                    ) : null}
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight md:text-3xl">
                        {title}
                    </h2>
                    {subtitle ? (
                        <p className="text-muted-foreground mt-1 text-sm">
                            {subtitle}
                        </p>
                    ) : null}
                    {chips ? <StatusPills>{chips}</StatusPills> : null}
                    {children}
                </div>
                {aside}
            </div>
        </section>
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
    primary,
}: {
    children: ReactNode;
    className?: string;
    primary?: ReactNode;
}) {
    return (
        <div className={cn('flex flex-wrap items-center gap-2', className)}>
            {primary}
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
