import { Form, Head, Link, router } from '@inertiajs/react';
import { controlClassName } from '@/components/mdm/directory';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as visitsIndex } from '@/routes/scheduled-visits';
import type { OptionItem } from '@/types/directory';

type CalendarVisit = {
    id: number;
    service_date: string;
    service_type: string;
    status: string;
    status_label: string;
    time_label: string;
    dsp_name: string;
    client_name: string;
    needs_attention?: boolean;
};

type CalendarRow = {
    id: number;
    name: string;
    visits: CalendarVisit[];
};

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
    return (
        <>
            <Head title="Schedule Board" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
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
                ) : (
                    <div className="space-y-4">
                        {board.rows.map((row) => (
                            <Panel
                                key={`${board.group}-${row.id}`}
                                title={row.name}
                                description={
                                    board.group === 'dsp'
                                        ? 'Staffing and workload'
                                        : 'Coverage continuity'
                                }
                            >
                                <div className="flex flex-wrap gap-2">
                                    {row.visits.map((visit) => (
                                        <button
                                            key={visit.id}
                                            type="button"
                                            onClick={() =>
                                                router.visit(
                                                    `/scheduled-visits/${visit.id}`,
                                                )
                                            }
                                            className="border-border hover:bg-muted/40 min-w-[12rem] rounded-md border px-3 py-2 text-left text-sm"
                                        >
                                            <p className="font-medium">
                                                {board.group === 'dsp'
                                                    ? visit.client_name
                                                    : visit.dsp_name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {visit.service_date} ·{' '}
                                                {visit.time_label}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {visit.service_type} ·{' '}
                                                {visit.status_label}
                                                {visit.needs_attention
                                                    ? ' · needs attention'
                                                    : ''}
                                            </p>
                                        </button>
                                    ))}
                                </div>
                            </Panel>
                        ))}
                    </div>
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
