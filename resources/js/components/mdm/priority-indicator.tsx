import { cn } from '@/lib/utils';

export function HighPriorityIndicator({
    active,
    label = 'High-priority exception',
    className,
}: {
    active: boolean;
    label?: string;
    className?: string;
}) {
    if (!active) {
        return null;
    }

    return (
        <span
            className={cn('inline-flex items-center gap-1.5', className)}
            title={label}
        >
            <span
                className="mdm-priority-pulse bg-destructive size-2 shrink-0 rounded-full"
                aria-hidden="true"
            />
            <span className="sr-only">{label}</span>
        </span>
    );
}

export function hasHighPriorityOpen(
    exceptions?: Array<{
        is_high_priority?: boolean;
        is_high_priority_open?: boolean;
        status?: string;
    }> | null,
): boolean {
    return (exceptions ?? []).some(
        (exception) =>
            exception.is_high_priority_open === true ||
            (exception.is_high_priority === true &&
                exception.status !== 'resolved'),
    );
}
