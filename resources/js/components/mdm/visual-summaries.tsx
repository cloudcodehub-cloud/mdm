import { cn } from '@/lib/utils';

export type StatusTone =
    | 'success'
    | 'warning'
    | 'critical'
    | 'info'
    | 'neutral'
    | 'brand';

export type StatusSegment = {
    key: string;
    label: string;
    value: number;
    tone?: StatusTone;
};

export type TrendPoint = {
    date: string;
    label: string;
    value: number;
};

const toneFill: Record<StatusTone, string> = {
    success: 'bg-status-success',
    warning: 'bg-status-warning',
    critical: 'bg-status-critical',
    info: 'bg-status-info',
    neutral: 'bg-status-neutral/70',
    brand: 'bg-primary',
};

export function SegmentedStatusBar({
    segments,
    className,
}: {
    segments: StatusSegment[];
    className?: string;
}) {
    const total = segments.reduce((sum, segment) => sum + segment.value, 0);

    return (
        <div className={cn('space-y-3', className)}>
            <div
                className="bg-muted/70 flex h-3 overflow-hidden rounded-full"
                role="img"
                aria-label={segments
                    .map((segment) => `${segment.label} ${segment.value}`)
                    .join(', ')}
            >
                {total === 0 ? (
                    <span className="bg-muted block h-full w-full" />
                ) : (
                    segments.map((segment) =>
                        segment.value <= 0 ? null : (
                            <span
                                key={segment.key}
                                className={cn(
                                    'h-full min-w-0',
                                    toneFill[segment.tone ?? 'neutral'],
                                )}
                                style={{
                                    width: `${(segment.value / total) * 100}%`,
                                }}
                                title={`${segment.label}: ${segment.value}`}
                            />
                        ),
                    )
                )}
            </div>
            <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                {segments.map((segment) => (
                    <li
                        key={segment.key}
                        className="text-muted-foreground flex items-center gap-1.5"
                    >
                        <span
                            className={cn(
                                'size-2 rounded-full',
                                toneFill[segment.tone ?? 'neutral'],
                            )}
                        />
                        <span>{segment.label}</span>
                        <span className="text-foreground font-medium tabular-nums">
                            {segment.value}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function ProgressRing({
    value,
    label,
    detail,
    className,
}: {
    value: number;
    label?: string;
    detail?: string;
    className?: string;
}) {
    const clamped = Math.max(0, Math.min(100, value));
    const radius = 34;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (clamped / 100) * circumference;

    return (
        <div className={cn('flex items-center gap-3', className)}>
            <svg
                viewBox="0 0 80 80"
                className="size-20 shrink-0"
                aria-hidden="true"
            >
                <circle
                    cx="40"
                    cy="40"
                    r={radius}
                    fill="none"
                    className="stroke-muted"
                    strokeWidth="8"
                />
                <circle
                    cx="40"
                    cy="40"
                    r={radius}
                    fill="none"
                    className="stroke-primary"
                    strokeWidth="8"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    transform="rotate(-90 40 40)"
                />
            </svg>
            <div>
                <p className="text-2xl font-semibold tracking-tight tabular-nums">
                    {label ?? `${clamped}%`}
                </p>
                {detail && (
                    <p className="text-muted-foreground text-xs">{detail}</p>
                )}
            </div>
        </div>
    );
}

export function MiniBarChart({
    points,
    className,
}: {
    points: TrendPoint[];
    className?: string;
}) {
    const max = Math.max(1, ...points.map((point) => point.value));

    return (
        <div className={cn('flex h-24 items-end gap-2', className)}>
            {points.map((point) => (
                <div
                    key={point.date}
                    className="flex min-w-0 flex-1 flex-col items-center gap-1"
                >
                    <span className="text-muted-foreground text-[10px] tabular-nums">
                        {point.value}
                    </span>
                    <div className="bg-muted/80 flex h-16 w-full items-end overflow-hidden rounded-sm">
                        <span
                            className="bg-primary/70 w-full rounded-sm"
                            style={{
                                height: `${(point.value / max) * 100}%`,
                            }}
                            title={`${point.date}: ${point.value}`}
                        />
                    </div>
                    <span className="text-muted-foreground text-[10px]">
                        {point.label}
                    </span>
                </div>
            ))}
        </div>
    );
}

export function MetricSummary({
    label,
    value,
    hint,
    className,
}: {
    label: string;
    value: number | string;
    hint?: string;
    className?: string;
}) {
    return (
        <div className={cn('min-w-0', className)}>
            <p className="text-muted-foreground text-xs tracking-wide uppercase">
                {label}
            </p>
            <p className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            {hint && (
                <p className="text-muted-foreground mt-1 text-xs">{hint}</p>
            )}
        </div>
    );
}
