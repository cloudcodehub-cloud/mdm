import { Form, Head, Link, router } from '@inertiajs/react';
import { Pagination, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    create,
    index as visitsIndex,
    show,
} from '@/routes/scheduled-visits';
import type { OptionItem, Paginated, VisitRecord } from '@/types/directory';

export default function ScheduledVisitsIndex({
    visits,
    filters,
    clients,
    dsps,
    can,
}: {
    visits: Paginated<VisitRecord>;
    filters: {
        service_date: string;
        client_id: string;
        employee_id: string;
        status: string;
    };
    clients: OptionItem[];
    dsps: OptionItem[];
    can: { create: boolean };
}) {
    return (
        <>
            <Head title="Scheduled Visits" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Scheduled Visits
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Planned client visits, assigned DSPs, and shift
                            windows.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={create()}>Add Scheduled Visit</Link>
                        </Button>
                    )}
                </div>

                <Panel title="Search and filters">
                    <Form
                        action={visitsIndex.url()}
                        method="get"
                        className="grid gap-3 md:grid-cols-5"
                    >
                        <input
                            type="date"
                            name="service_date"
                            defaultValue={filters.service_date}
                            className={controlClassName}
                            aria-label="Service date"
                        />
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
                        <select
                            name="employee_id"
                            defaultValue={filters.employee_id}
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
                        <select
                            name="status"
                            defaultValue={filters.status}
                            className={controlClassName}
                            aria-label="Status"
                        >
                            <option value="">All statuses</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="completed">Completed</option>
                        </select>
                        <Button type="submit" variant="secondary">
                            Apply
                        </Button>
                    </Form>
                </Panel>

                {visits.data.length === 0 ? (
                    <Panel title="Visit schedule">
                        <EmptyState message="No scheduled visits match these filters." />
                    </Panel>
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {visits.data.map((visit) => (
                                <button
                                    key={visit.id}
                                    type="button"
                                    onClick={() =>
                                        router.visit(show.url(visit.id))
                                    }
                                    className="surface-panel hover:bg-muted/30 p-4 text-left transition-colors"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">
                                                {visit.client_name ??
                                                    visit.client?.name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {visit.service_date}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            status={visit.status}
                                            label={visit.status_label}
                                        />
                                    </div>
                                    <p className="text-muted-foreground mt-3 text-sm">
                                        DSP: {visit.dsp_name}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {visit.service_type}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {visit.time_label}
                                        {visit.shift_name
                                            ? ` · ${visit.shift_name}`
                                            : ''}
                                    </p>
                                </button>
                            ))}
                        </div>
                        <Pagination meta={visits.meta} links={visits.links} />
                    </>
                )}
            </div>
        </>
    );
}

ScheduledVisitsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scheduled Visits', href: visitsIndex() },
    ],
};
