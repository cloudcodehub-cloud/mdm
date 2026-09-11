import { cn } from '@/lib/utils';
import type { TimelineSegment } from '@/types/scheduling';

const stateClass: Record<string, string> = {
    available: 'bg-emerald-500/70',
    unavailable: 'bg-slate-400/40',
    leave: 'bg-amber-500/80',
    occupied: 'bg-sky-600/80',
    requested: 'bg-teal-400',
    conflict: 'bg-rose-500',
};

export function AvailabilityStrip({
    segments,
    compact = false,
}: {
    segments: TimelineSegment[];
    compact?: boolean;
}) {
    return (
        <div
            className={cn(
                'bg-muted/40 relative overflow-hidden rounded-md',
                compact ? 'h-3' : 'h-8',
            )}
            role="img"
            aria-label="24-hour availability"
        >
            {segments.map((segment, index) => (
                <span
                    key={`${segment.start}-${index}`}
                    title={`${segment.state}${segment.label ? ` · ${segment.label}` : ''}`}
                    className={cn(
                        'absolute top-0 h-full',
                        stateClass[segment.state] ?? 'bg-muted',
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
