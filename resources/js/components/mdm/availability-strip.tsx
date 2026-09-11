import { cn } from '@/lib/utils';
import type { TimelineSegment } from '@/types/scheduling';

const stateClass: Record<string, string> = {
    available: 'bg-emerald-500/70',
    unconfirmed: 'bg-amber-400/35',
    unavailable: 'bg-slate-400/40',
    leave: 'bg-amber-500/80',
    occupied: 'bg-sky-600/80',
    requested: 'bg-teal-400',
    conflict: 'bg-rose-500',
};

export const availabilityLegend = [
    { state: 'available', label: 'Available', swatch: 'bg-emerald-500/70' },
    { state: 'occupied', label: 'Occupied', swatch: 'bg-sky-600/80' },
    { state: 'unavailable', label: 'Unavailable', swatch: 'bg-slate-400/40' },
    { state: 'unconfirmed', label: 'Not confirmed', swatch: 'bg-amber-400/35' },
    { state: 'leave', label: 'Leave', swatch: 'bg-amber-500/80' },
    { state: 'requested', label: 'Requested window', swatch: 'bg-teal-400' },
    { state: 'conflict', label: 'Conflict', swatch: 'bg-rose-500' },
] as const;

export function AvailabilityStrip({
    segments,
    compact = false,
}: {
    segments: TimelineSegment[];
    compact?: boolean;
}) {
    const summary = segments
        .map((segment) =>
            segment.label
                ? `${segment.state}: ${segment.label}`
                : segment.state,
        )
        .join('; ');

    return (
        <div
            className={cn(
                'bg-muted/40 relative overflow-hidden rounded-md',
                compact ? 'h-3' : 'h-8',
            )}
            role="img"
            aria-label={summary || '24-hour availability'}
        >
            {segments.map((segment, index) => (
                <span
                    key={`${segment.start}-${index}`}
                    title={`${segment.state}${segment.label ? ` · ${segment.label}` : ''}`}
                    className={cn(
                        'absolute top-0 h-full',
                        stateClass[segment.state] ?? 'bg-muted',
                        segment.state === 'unconfirmed' &&
                            'bg-[repeating-linear-gradient(135deg,rgba(245,158,11,0.45)_0_6px,rgba(148,163,184,0.25)_6px_12px)]',
                    )}
                    style={{
                        left: `${(segment.start / 1440) * 100}%`,
                        width: `${((segment.end - segment.start) / 1440) * 100}%`,
                    }}
                />
            ))}
            {!compact && (
                <div className="text-muted-foreground pointer-events-none absolute inset-x-0 bottom-0 flex justify-between px-1 text-[10px]">
                    <span>12a</span>
                    <span>6a</span>
                    <span>12p</span>
                    <span>6p</span>
                    <span>12a</span>
                </div>
            )}
        </div>
    );
}

export function AvailabilityLegend() {
    return (
        <ul className="flex flex-wrap gap-x-3 gap-y-1 text-[11px]">
            {availabilityLegend.map((item) => (
                <li key={item.state} className="flex items-center gap-1.5">
                    <span
                        className={cn('size-2.5 rounded-sm', item.swatch)}
                        aria-hidden="true"
                    />
                    <span className="text-muted-foreground">{item.label}</span>
                </li>
            ))}
        </ul>
    );
}
