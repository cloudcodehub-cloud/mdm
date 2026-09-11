import { Form, Head, Link, router } from '@inertiajs/react';
import { controlClassName } from '@/components/mdm/directory';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as visitsIndex } from '@/routes/scheduled-visits';
import type { OptionItem } from '@/types/directory';

type CalendarVisit = {
    id: number;
    service_date: string;
    service_type: string;
    services?: Array<{ id: number; name: string }>;
    status: string;
    status_label: string;
    time_label: string;
    dsp_name: string;
    client_name: string;
    starts_minute?: number;
    ends_minute?: number;
    needs_attention?: boolean;
};

type CalendarRow = {
    id: number;
    name: string;
    visits: CalendarVisit[];
};

function datesInRange(start: string, end: string): string[] {
    const days: string[] = [];
    const cursor = new Date(`${start}T00:00:00`);
    const last = new Date(`${end}T00:00:00`);

    while (cursor <= last) {
        days.push(cursor.toISOString().slice(0, 10));
        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
}

function weekdayLabel(date: string): string {
    return new Date(`${date}T12:00:00`).toLocaleDateString(undefined, {
        weekday: 'short',
    });
}

function dayNumber(date: string): string {
    return new Date(`${date}T12:00:00`).getDate().toString();
}

function VisitChip({
    visit,
    primary,
}: {
    visit: CalendarVisit;
    primary: string;
}) {
    const services =
        visit.services && visit.services.length > 0
            ? visit.services.map((service) => service.name).join(' · ')
            : visit.service_type;

    return (
        <button
            type="button"
            onClick={() => router.visit(`/scheduled-visits/${visit.id}`)}
            className={cn(
                'border-border w-full rounded-md border px-2 py-1.5 text-left text-xs',
                visit.needs_attention
                    ? 'border-warning/50 bg-warning/10'
                    : 'hover:bg-muted/40 bg-background/70',
            )}
        >
            <p className="truncate font-medium">{primary}</p>
            <p className="text-muted-foreground truncate">
                {visit.time_label} · {services}
            </p>
        </button>
    );
}

function WeekGrid({
    days,
    rows,
    group,
}: {
    days: string[];
    rows: CalendarRow[];
    group: string;
}) {
    return (
        <div className="overflow-x-auto">
            <div
                className="min-w-[52rem] grid gap-px rounded-lg border"
                style={{
                    gridTemplateColumns: `8rem repeat(${days.length}, minmax(0, 1fr))`,
                }}
            >
                <div className="bg-muted/40 p-2 text-xs font-medium">
                    {group === 'dsp' ? 'DSP' : 'Client'}
                </div>
                {days.map((day) => (
                    <div
                        key={day}
                        className="bg-muted/40 p-2 text-center text-xs font-medium"
                    >
                        <span className="block">{weekdayLabel(day)}</span>
                        <span className="text-muted-foreground">
                            {dayNumber(day)}
                        </span>
                    </div>
                ))}
                {rows.map((row) => (
                    <WeekRow
                        key={`${group}-${row.id}`}
                        row={row}
                        days={days}
                        group={group}
                    />
                ))}
            </div>
        </div>
    );
}

function WeekRow({
    row,
    days,
    group,
}: {
    row: CalendarRow;
    days: string[];
    group: string;
}) {
    return (
        <>
            <div className="bg-background p-2 text-sm font-medium">
                {row.name}
            </div>
            {days.map((day) => {
                const visits = row.visits.filter(
                    (visit) => visit.service_date === day,
                );
                return (
                    <div key={day} className="bg-background min-h-24 space-y-1 p-1">
                        {visits.map((visit) => (
                            <VisitChip
                                key={visit.id}
                                visit={visit}
                                primary={
                                    group === 'dsp'
                                        ? visit.client_name
                                        : visit.dsp_name
                                }
                            />
                        ))}
                    </div>
                );
            })}
        </>
    );
}

function DayTimetable({
    rows,
    group,
}: {
    rows: CalendarRow[];
    group: string;
}) {
    const hours = Array.from({ length: 17 }, (_, index) => index + 6);

    return (
        <div className="overflow-x-auto">
            <div
                className="min-w-[48rem] grid gap-px rounded-lg border"
                style={{
                    gridTemplateColumns: `4rem repeat(${Math.max(rows.length, 1)}, minmax(8rem, 1fr))`,
                }}
            >
                <div className="bg-muted/40 p-2 text-xs">Time</div>
                {rows.map((row) => (
                    <div
                        key={row.id}
                        className="bg-muted/40 p-2 text-xs font-medium"
                    >
                        {row.name}
                    </div>
                ))}
                {hours.map((hour) => (
                    <HourRow
                        key={hour}
                        hour={hour}
                        rows={rows}
                        group={group}
                    />
                ))}
            </div>
        </div>
    );
}

function HourRow({
    hour,
    rows,
    group,
}: {
    hour: number;
    rows: CalendarRow[];
    group: string;
}) {
    const start = hour * 60;
    const end = start + 60;
    const label = `${hour % 12 === 0 ? 12 : hour % 12}${hour < 12 ? 'a' : 'p'}`;

    return (
        <>
            <div className="text-muted-foreground bg-background p-1 text-[11px]">
                {label}
            </div>
            {rows.map((row) => {
                const visits = row.visits.filter((visit) => {
                    const from = visit.starts_minute ?? 0;
                    const to =
                        visit.ends_minute && visit.ends_minute > from
                            ? visit.ends_minute
                            : from + 60;
                    return from < end && to > start;
                });

                return (
                    <div
                        key={`${row.id}-${hour}`}
                        className={cn(
                            'relative min-h-12 p-1',
                            visits.length > 0
                                ? 'bg-sky-600/10'
                                : 'bg-background',
                        )}
                    >
                        {visits.map((visit) => (
                            <VisitChip
                                key={visit.id}
                                visit={visit}
                                primary={
                                    group === 'dsp'
                                        ? visit.client_name
                                        : visit.dsp_name
                                }
                            />
                        ))}
                    </div>
                );
            })}
        </>
    );
}

function MonthGrid({
    start,
    end,
    rows,
    group,
}: {
    start: string;
    end: string;
    rows: CalendarRow[];
    group: string;
}) {
    const days = datesInRange(start, end);
    const lead = new Date(`${start}T12:00:00`).getDay();
    const cells: Array<string | null> = [
        ...Array.from({ length: lead }, () => null),
        ...days,
    ];

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    const visitsByDay = new Map<string, CalendarVisit[]>();

    for (const row of rows) {
        for (const visit of row.visits) {
            const list = visitsByDay.get(visit.service_date) ?? [];
            list.push(visit);
            visitsByDay.set(visit.service_date, list);
        }
    }

    return (
        <div className="grid grid-cols-7 gap-px overflow-hidden rounded-lg border">
            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((label) => (
                <div
                    key={label}
                    className="bg-muted/40 p-2 text-center text-xs font-medium"
                >
                    {label}
                </div>
            ))}
            {cells.map((day, index) => (
                <div
                    key={day ?? `empty-${index}`}
                    className="bg-background min-h-28 space-y-1 p-1"
                >
                    {day && (
                        <>
                            <p className="text-muted-foreground px-1 text-xs">
                                {dayNumber(day)}
                            </p>
                            {(visitsByDay.get(day) ?? [])
                                .slice(0, 4)
                                .map((visit) => (
                                    <VisitChip
                                        key={visit.id}
                                        visit={visit}
                                        primary={
                                            group === 'dsp'
                                                ? visit.client_name
                                                : visit.dsp_name
                                        }
                                    />
                                ))}
                            {(visitsByDay.get(day) ?? []).length > 4 && (
                                <p className="text-muted-foreground px-1 text-[11px]">
                                    +{(visitsByDay.get(day) ?? []).length - 4}{' '}
                                    more
                                </p>
                            )}
                        </>
                    )}
                </div>
            ))}
        </div>
    );
}

export default function ScheduledVisitsCalendar({
    board,
    filters,
    clients,
    dsps,
    supervisors,
    can,
}: {
    board: {
        view: string;
        group: string;
        anchor: string;
        start: string;
        end: string;
        rows: CalendarRow[];
    };
    filters: Record<string, string>;
    clients: OptionItem[];
    dsps: OptionItem[];
    supervisors: OptionItem[];
    can: { create: boolean; filter_dsps?: boolean };
}) {
    const days = datesInRange(board.start, board.end);

    return (
        <>
            <Head title="Schedule Board" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Schedule Board
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {board.start} – {board.end}. Click a visit to open
                            it. Drag and drop is not enabled yet.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="secondary">
                            <Link href={visitsIndex()}>List</Link>
                        </Button>
                        {can.create && (
                            <Button asChild>
                                <Link href="/scheduled-visits/create">
                                    Add visit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Panel title="View and filters">
                    <Form
                        action="/scheduled-visits/calendar"
                        method="get"
                        className="grid gap-3 md:grid-cols-4 xl:grid-cols-8"
                    >
                        <select
                            name="view"
                            defaultValue={board.view}
                            className={controlClassName}
                            aria-label="View"
                        >
                            <option value="day">Day</option>
                            <option value="week">Week</option>
                            <option value="month">Month</option>
                        </select>
                        <select
                            name="group"
                            defaultValue={board.group}
                            className={controlClassName}
                            aria-label="Group by"
                        >
                            <option value="dsp">View by DSP</option>
                            <option value="client">View by client</option>
                        </select>
                        <input
                            type="date"
                            name="date"
                            defaultValue={board.anchor}
                            className={controlClassName}
                            aria-label="Date"
                        />
                        <select
                            name="client_id"
                            defaultValue={filters.client_id ?? ''}
                            className={controlClassName}
                            aria-label="Client"
                        >
                            <option value="">All clients</option>
                            {clients.map((client) => (
                                <option key={client.id} value={client.id}>
                                    {client.name}
                                </option>
                            ))}
                        </select>
                        {can.filter_dsps !== false && (
                            <select
                                name="employee_id"
                                defaultValue={filters.employee_id ?? ''}
                                className={controlClassName}
                                aria-label="DSP"
                            >
                                <option value="">All DSPs</option>
                                {dsps.map((dsp) => (
                                    <option key={dsp.id} value={dsp.id}>
                                        {dsp.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <select
                            name="supervisor_id"
                            defaultValue={filters.supervisor_id ?? ''}
                            className={controlClassName}
                            aria-label="Supervisor"
                        >
                            <option value="">All supervisors</option>
                            {supervisors.map((row) => (
                                <option key={row.id} value={row.id}>
                                    {row.name}
                                </option>
                            ))}
                        </select>
                        <select
                            name="status"
                            defaultValue={filters.status ?? ''}
                            className={controlClassName}
                            aria-label="Status"
                        >
                            <option value="">All statuses</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In progress</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="completed">Completed</option>
                        </select>
                        <Button type="submit" variant="secondary">
                            Apply
                        </Button>
                    </Form>
                </Panel>

                {board.rows.length === 0 ? (
                    <Panel title="Coverage">
                        <p className="text-muted-foreground text-sm">
                            No visits in this range.
                        </p>
                    </Panel>
                ) : board.view === 'day' ? (
                    <DayTimetable rows={board.rows} group={board.group} />
                ) : board.view === 'month' ? (
                    <MonthGrid
                        start={board.start}
                        end={board.end}
                        rows={board.rows}
                        group={board.group}
                    />
                ) : (
                    <WeekGrid
                        days={days}
                        rows={board.rows}
                        group={board.group}
                    />
                )}
            </div>
        </>
    );
}

ScheduledVisitsCalendar.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scheduled Visits', href: visitsIndex() },
        { title: 'Calendar', href: '/scheduled-visits/calendar' },
    ],
};
