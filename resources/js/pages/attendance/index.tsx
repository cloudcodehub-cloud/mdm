import { Form, Head, Link, router } from '@inertiajs/react';
import { Pagination, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as attendanceIndex, show } from '@/routes/attendance';
import {
    approve as approveCorrection,
    reject as rejectCorrection,
} from '@/routes/attendance/corrections';
import type {
    AttendanceCorrectionRecord,
    AttendanceRecord,
} from '@/types/attendance';
import type { OptionItem, Paginated } from '@/types/directory';

export default function AttendanceIndex({
    records,
    filters,
    pending_corrections,
    clients,
    dsps,
    supervisors,
    statuses,
    can,
}: {
    records: Paginated<AttendanceRecord>;
    filters: {
        from: string;
        to: string;
        employee_id: string;
        client_id: string;
        supervisor_id: string;
        status: string;
    };
    pending_corrections: AttendanceCorrectionRecord[];
    clients: OptionItem[];
    dsps: OptionItem[];
    supervisors: OptionItem[];
    statuses: { value: string; label: string }[];
    can: { review_corrections: boolean };
}) {
    return (
        <>
            <Head title="Attendance" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Attendance
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Scheduled visits with actual clock times, duration, and
                        exceptions in the organization timezone.
                    </p>
                </div>

                <Panel title="Search and filters">
                    <Form
                        action={attendanceIndex.url()}
                        method="get"
                        className="grid gap-3 md:grid-cols-3 xl:grid-cols-7"
                    >
                        <input
                            type="date"
                            name="from"
                            defaultValue={filters.from}
                            className={controlClassName}
                            aria-label="From date"
                        />
                        <input
                            type="date"
                            name="to"
                            defaultValue={filters.to}
                            className={controlClassName}
                            aria-label="To date"
                        />
                        <select
                            name="employee_id"
                            defaultValue={filters.employee_id}
                            className={controlClassName}
                            aria-label="Employee"
                        >
                            <option value="">All employees</option>
                            {dsps.map((dsp) => (
                                <option key={dsp.id} value={dsp.id}>
                                    {dsp.name}
                                </option>
                            ))}
                        </select>
                        <select
                            name="client_id"
                            defaultValue={filters.client_id}
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
                        {supervisors.length > 0 && (
                            <select
                                name="supervisor_id"
                                defaultValue={filters.supervisor_id}
                                className={controlClassName}
                                aria-label="Supervisor"
                            >
                                <option value="">All supervisors</option>
                                {supervisors.map((supervisor) => (
                                    <option
                                        key={supervisor.id}
                                        value={supervisor.id}
                                    >
                                        {supervisor.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <select
                            name="status"
                            defaultValue={filters.status}
                            className={controlClassName}
                            aria-label="Status"
                        >
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                        <Button type="submit" variant="secondary">
                            Apply
                        </Button>
                    </Form>
                </Panel>

                {can.review_corrections && pending_corrections.length > 0 && (
                    <Panel
                        title="Pending correction requests"
                        description="Approve or reject without changing original DSP clock events."
                    >
                        <div className="space-y-3">
                            {pending_corrections.map((correction) => (
                                <div
                                    key={correction.id}
                                    className="border-border flex flex-wrap items-start justify-between gap-3 border-t pt-3 first:border-t-0 first:pt-0"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {correction.employee_name} ·{' '}
                                            {correction.client_name}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {correction.reason}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Original{' '}
                                            {correction.original_clock_in ?? '—'}{' '}
                                            –{' '}
                                            {correction.original_clock_out ??
                                                '—'}
                                        </p>
                                        <p className="text-xs">
                                            Requested{' '}
                                            {correction.requested_clock_in ??
                                                'unchanged'}{' '}
                                            –{' '}
                                            {correction.requested_clock_out ??
                                                'unchanged'}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button asChild variant="secondary">
                                            <Link
                                                href={show.url(
                                                    correction.scheduled_visit_id,
                                                )}
                                            >
                                                Review
                                            </Link>
                                        </Button>
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                router.patch(
                                                    approveCorrection.url(
                                                        correction.id,
                                                    ),
                                                )
                                            }
                                        >
                                            Approve
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                router.patch(
                                                    rejectCorrection.url(
                                                        correction.id,
                                                    ),
                                                )
                                            }
                                        >
                                            Reject
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Panel>
                )}

                {records.data.length === 0 ? (
                    <Panel title="Attendance">
                        <EmptyState message="No attendance records match these filters." />
                    </Panel>
                ) : (
                    <Panel title="Attendance records">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-muted-foreground text-xs uppercase">
                                    <tr>
                                        <th className="pb-2 font-medium">
                                            Employee
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Client
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Scheduled
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Clock-in
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Clock-out
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Duration
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.data.map((record) => (
                                        <tr
                                            key={record.id}
                                            className="hover:bg-muted/40 cursor-pointer border-t"
                                            onClick={() =>
                                                router.visit(show.url(record.id))
                                            }
                                        >
                                            <td className="py-3">
                                                {record.employee.name}
                                            </td>
                                            <td className="py-3">
                                                {record.client.name}
                                            </td>
                                            <td className="py-3">
                                                <p>{record.service_date}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {record.scheduled_time}
                                                </p>
                                            </td>
                                            <td className="py-3">
                                                <ClockCell record={record} kind="in" />
                                            </td>
                                            <td className="py-3">
                                                <ClockCell
                                                    record={record}
                                                    kind="out"
                                                />
                                            </td>
                                            <td className="py-3">
                                                {record.worked_duration ?? '—'}
                                            </td>
                                            <td className="py-3">
                                                <div className="flex flex-col gap-1">
                                                    <StatusBadge
                                                        status={record.status}
                                                        label={record.status_label}
                                                    />
                                                    {record.has_gps_issue && (
                                                        <span className="text-muted-foreground text-xs">
                                                            GPS issue
                                                        </span>
                                                    )}
                                                    {record.has_exception && (
                                                        <span className="text-muted-foreground text-xs">
                                                            Exception
                                                        </span>
                                                    )}
                                                    {record.adjustment_label && (
                                                        <span className="text-xs">
                                                            {record.adjustment_label}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination
                            meta={records.meta}
                            links={records.links}
                        />
                    </Panel>
                )}
            </div>
        </>
    );
}

function ClockCell({
    record,
    kind,
}: {
    record: AttendanceRecord;
    kind: 'in' | 'out';
}) {
    const original =
        kind === 'in' ? record.original_clock_in : record.original_clock_out;
    const effective =
        kind === 'in'
            ? record.effective_clock_in
            : record.effective_clock_out;

    if (record.is_adjusted && original !== effective) {
        return (
            <div>
                <p className="text-muted-foreground text-xs">Original</p>
                <p>{original ?? '—'}</p>
                <p className="text-muted-foreground mt-1 text-xs">Adjusted</p>
                <p>{effective ?? '—'}</p>
            </div>
        );
    }

    return <p>{effective ?? original ?? '—'}</p>;
}

AttendanceIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Attendance', href: attendanceIndex() },
    ],
};
