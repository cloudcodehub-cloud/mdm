import { cn } from '@/lib/utils';

export type ProfileHealthTone = 'danger' | 'warning' | 'success' | string;

export function ProfileHealthBadge({
    percent,
    status,
    statusLabel,
    tone,
    className,
}: {
    percent: number;
    status?: string;
    statusLabel?: string;
    tone?: ProfileHealthTone;
    className?: string;
}) {
    const resolvedTone =
        tone ??
        (percent <= 39 ? 'danger' : percent <= 69 ? 'warning' : 'success');
    const label =
        statusLabel ??
        (resolvedTone === 'danger'
            ? 'Critical'
            : resolvedTone === 'warning'
              ? 'Needs attention'
              : 'Healthy');

    return (
        <span
            className={cn(
                'inline-flex max-w-full items-center gap-1.5 text-sm font-medium',
                className,
            )}
            aria-label={`Profile health ${percent} percent, ${label}`}
        >
            <span
                className={cn(
                    'size-2.5 shrink-0 rounded-full',
                    resolvedTone === 'danger' && 'bg-destructive',
                    resolvedTone === 'warning' && 'bg-status-warning',
                    resolvedTone === 'success' && 'bg-status-success',
                    resolvedTone !== 'danger' &&
                        resolvedTone !== 'warning' &&
                        resolvedTone !== 'success' &&
                        'bg-muted-foreground',
                )}
                aria-hidden="true"
            />
            <span className="tabular-nums">{percent}%</span>
            <span className="text-muted-foreground font-normal">{label}</span>
        </span>
    );
}
