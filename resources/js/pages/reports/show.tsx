import { Form, Head, Link } from '@inertiajs/react';
import { Pagination, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { PrintPdfAction } from '@/components/mdm/print-pdf-action';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    download,
    index as reportsIndex,
    show as showReport,
} from '@/routes/reports';
import type { OptionItem, Paginated } from '@/types/directory';
import type {
    ReportColumn,
    ReportFilterVisibility,
    ReportFilters,
    ReportRow,
    ReportSummary,
    ReportTotals,
} from '@/types/reports';

export default function ReportShow({
    report,
    columns,
    rows,
    summary,
    filters,
    filter_visibility,
    clients,
    dsps,
    supervisors,
    statuses,
    timezone,
    can,
    hours_pdf_url = null,
}: {
    report: ReportSummary;
    columns: ReportColumn[];
    rows: Paginated<ReportRow>;
    summary: ReportTotals | null;
    filters: ReportFilters;
    filter_visibility: ReportFilterVisibility;
    clients: OptionItem[];
    dsps: OptionItem[];
    supervisors: OptionItem[];
    statuses: { value: string; label: string }[];
    timezone: string;
    can: { export: boolean; hours_pdf?: boolean };
    hours_pdf_url?: string | null;
}) {
    const exportUrl = download.url(report.key, {
        query: {
            from: filters.from,
            to: filters.to,
            employee_id: filters.employee_id,
            client_id: filters.client_id,
            supervisor_id: filters.supervisor_id,
            status: filters.status,
        },
    });

    return (
        <>
            <Head title={report.title} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            <Link
                                href={reportsIndex()}
                                className="hover:text-foreground"
                            >
                                Reports
                            </Link>
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {report.title}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {report.description} Times use {timezone}.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.hours_pdf && hours_pdf_url && (
                            <PrintPdfAction href={hours_pdf_url} />
                        )}
                        {can.export && (
                            <Button asChild variant="secondary">
                                <a href={exportUrl}>Export CSV</a>
                            </Button>
                        )}
                    </div>
                </div>

                <Panel title="Filters">
                    <Form
                        action={showReport.url(report.key)}
                        method="get"
                        className="grid gap-3 md:grid-cols-3 xl:grid-cols-7"
                    >
                        {filter_visibility.dates && (
                            <>
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
                            </>
                        )}
                        {filter_visibility.employee && (
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
                        )}
                        {filter_visibility.client && (
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
                        )}
                        {filter_visibility.supervisor &&
                            supervisors.length > 0 && (
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
                        {filter_visibility.status && (
                            <select
                                name="status"
                                defaultValue={filters.status}
                                className={controlClassName}
                                aria-label="Status"
                            >
                                <option value="">All statuses</option>
                                {statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        )}
                        <Button type="submit" variant="secondary">
                            Apply
                        </Button>
                    </Form>
                </Panel>

                {summary !== null && (
                    <Panel
                        title="Period totals"
                        description="Hours-only totals from completed visits. Approved attendance adjustments replace clock times for duration; original EVV clocks are unchanged."
                    >
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">
                                    Employees
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {summary.employee_count}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">
                                    Completed visits
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {summary.completed_visit_count}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs uppercase">
                                    Worked hours
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {summary.worked_hours}
                                </p>
                            </div>
                        </div>
                    </Panel>
                )}

                <Panel title={report.title}>
                    {rows.data.length === 0 ? (
                        <EmptyState message="No records match these filters." />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-muted-foreground text-xs uppercase">
                                        <tr>
                                            {columns.map((column) => (
                                                <th
                                                    key={column.key}
                                                    className="pb-2 font-medium"
                                                >
                                                    {column.label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.data.map((row, index) => (
                                            <tr
                                                key={index}
                                                className="border-t"
                                            >
                                                {columns.map((column) => (
                                                    <td
                                                        key={column.key}
                                                        className="py-3"
                                                    >
                                                        {column.key ===
                                                            'status_label' &&
                                                        typeof row.status ===
                                                            'string' ? (
                                                            <StatusBadge
                                                                status={
                                                                    row.status
                                                                }
                                                                label={String(
                                                                    row.status_label ??
                                                                        '',
                                                                )}
                                                            />
                                                        ) : (
                                                            (row[column.key] ??
                                                                '—')
                                                        )}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination meta={rows.meta} links={rows.links} />
                        </>
                    )}
                </Panel>
            </div>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reports', href: reportsIndex() },
    ],
};
