import { AvailabilityLegend, AvailabilityStrip } from '@/components/mdm/availability-strip';
import { cn } from '@/lib/utils';
import type { AvailabilityBoard, AvailabilityDspRow } from '@/types/scheduling';

function badgesFor(row: AvailabilityDspRow): Array<{ label: string; tone: string }> {
    const badges: Array<{ label: string; tone: string }> = [];

    if (row.assigned_to_client) {
        badges.push({ label: 'Assigned Client', tone: 'bg-primary/10 text-primary' });
    }

    if (row.fully_available && row.availability_confirmed !== false) {
        badges.push({
            label: 'Full Availability',
            tone: 'bg-status-success/10 text-status-success',
        });
    }

    if (row.availability_confirmed === false) {
        badges.push({
            label: 'Availability not confirmed',
            tone: 'bg-status-warning/10 text-status-warning',
        });
    }

    if (row.history_count > 0) {
        badges.push({
            label: 'Prior Client History',
            tone: 'bg-status-info/10 text-status-info',
        });
    }

    if (row.workload.week_hours < 24 && !row.hard_blocked) {
        badges.push({
            label: 'Low Workload',
            tone: 'bg-muted text-muted-foreground',
        });
    }

    if (row.hard_blocked && row.block_reason?.toLowerCase().includes('leave')) {
        badges.push({ label: 'Leave', tone: 'bg-status-warning/10 text-status-warning' });
    } else if (row.hard_blocked) {
        badges.push({ label: 'Conflict', tone: 'bg-destructive/10 text-destructive' });
    }

    return badges;
}

export function DspAvailabilityBoard({
    board,
    employeeId,
    onSelect,
    error,
}: {
    board: AvailabilityBoard;
    employeeId: string;
    onSelect: (id: string) => void;
    error?: string;
}) {
    const selected = board.dsps.find((row) => String(row.id) === employeeId);

    return (
        <section className="surface-panel space-y-3 p-4 md:p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                        4 · Choose DSP
                    </p>
                    <h2 className="text-sm font-semibold">Availability cards</h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Ranked by assignment, availability, continuity, and
                        workload — not AI.
                    </p>
                </div>
                <AvailabilityLegend />
            </div>
            <input type="hidden" name="employee_id" value={employeeId} />
            {error && <p className="text-destructive text-sm">{error}</p>}
            <div className="grid gap-2">
                {board.dsps.map((row) => (
                    <DspCard
                        key={row.id}
                        row={row}
                        board={board}
                        selected={String(row.id) === employeeId}
                        expanded={selected?.id === row.id}
                        onSelect={() => onSelect(String(row.id))}
                    />
                ))}
            </div>
            {board.coverage && (
                <div className="border-border rounded-md border p-3 text-sm">
                    <p>{board.coverage.message}</p>
                    <ul className="mt-2 space-y-1 text-xs">
                        {board.coverage.options.map((option) => (
                            <li key={option.label}>
                                <button
                                    type="button"
                                    className="hover:text-foreground"
                                    onClick={() =>
                                        onSelect(String(option.employee_id))
                                    }
                                >
                                    {option.label}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}

function DspCard({
    row,
    board,
    selected,
    expanded,
    onSelect,
}: {
    row: AvailabilityDspRow;
    board: AvailabilityBoard;
    selected: boolean;
    expanded: boolean;
    onSelect: () => void;
}) {
    const badges = badgesFor(row);

    return (
        <button
            type="button"
            onClick={onSelect}
            className={cn(
                'w-full rounded-lg border p-3 text-left transition-colors',
                selected
                    ? 'border-primary/50 bg-primary/8 ring-primary/20 ring-1'
                    : 'border-border/70 hover:bg-muted/30',
                row.hard_blocked && !selected && 'opacity-80',
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="text-sm font-semibold">{row.name}</p>
                    <p className="text-muted-foreground text-xs">
                        {row.reason_label}
                    </p>
                </div>
                <p className="text-muted-foreground text-[11px]">
                    Today {row.workload.day_hours}h · Week{' '}
                    {row.workload.week_hours}h · {row.workload.visit_count}{' '}
                    visits
                </p>
            </div>
            <div className="mt-2 flex flex-wrap gap-1">
                {badges.map((badge) => (
                    <span
                        key={badge.label}
                        className={cn(
                            'inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium',
                            badge.tone,
                        )}
                    >
                        {badge.label}
                    </span>
                ))}
            </div>
            <div className="mt-2">
                <AvailabilityStrip
                    compact={!expanded}
                    segments={row.timeline}
                    requested={board.requested}
                />
            </div>
            {expanded && row.warnings.length > 0 && (
                <ul className="text-warning mt-2 space-y-0.5 text-xs">
                    {row.warnings.map((warning) => (
                        <li key={warning}>{warning}</li>
                    ))}
                </ul>
            )}
            {expanded && row.block_reason && (
                <p className="text-destructive mt-2 text-xs">{row.block_reason}</p>
            )}
        </button>
    );
}
