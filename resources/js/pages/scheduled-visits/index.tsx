import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pagination, StatusBadge, controlClassName } from '@/components/mdm/directory';
import { countActiveFilters, FilterBar } from '@/components/mdm/filter-bar';
import { RecordPage } from '@/components/mdm/record-detail';
import { EmptyState } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create, index as visitsIndex, show } from '@/routes/scheduled-visits';
import type { OptionItem, Paginated, VisitRecord } from '@/types/directory';

const TABS = [
    { id: 'upcoming', label: 'Upcoming' },
    { id: 'in_progress', label: 'In progress' },
    { id: 'completed', label: 'Completed' },
    { id: 'cancelled', label: 'Cancelled' },
    { id: 'all', label: 'All' },
] as const;

export default function ScheduledVisitsIndex({
    visits,
    filters,
    clients,
    dsps,
    supervisors = [],
    service_types = [],
    can,
    board_url,
}: {
    visits: Paginated<VisitRecord>;
    filters: {
        phase: string;
        from: string;
        to: string;
        service_date: string;
        client_id: string;
        employee_id: string;
        supervisor_id: string;
        service_type: string;
        status: string;
    };
    clients: OptionItem[];
    dsps: OptionItem[];
    supervisors?: OptionItem[];
    service_types?: string[];
    can: { create: boolean; filter_dsps?: boolean; filter_supervisors?: boolean };
    board_url: string;
}) {
    const role = usePage().props.auth.user.role;
    const phase = filters.phase || 'upcoming';
    const active = countActiveFilters(filters, ['phase', 'status']);
    const tabHref = (id: string) =>
        visitsIndex.url({
            query: {
                phase: id,
                from: filters.from,
                to: filters.to,
                client_id: filters.client_id,
                employee_id: filters.employee_id,
                supervisor_id: filters.supervisor_id,
                service_type: filters.service_type,
            },
        });

    return (
        <>
            <Head title="Visits" />
            <RecordPage>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Visits
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {role === 'DSP'
                                ? 'Your upcoming work and visit history.'
                                : 'Upcoming work, live visits, and completed visit history.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="secondary">
                            <Link href={board_url}>Calendar view</Link>
                        </Button>
                        {can.create && (
                            <Button asChild>
                                <Link href={create()}>Schedule visit</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div
                    className="flex flex-wrap gap-1"
                    role="tablist"
                    aria-label="Visit status"
                >
                    {TABS.map((tab) => (
                        <Link
                            key={tab.id}
                            href={tabHref(tab.id)}
                            role="tab"
                            aria-selected={phase === tab.id}
                            className={cn(
                                'rounded-full px-3 py-1.5 text-sm',
                                phase === tab.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted/70',
                            )}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </div>

                <FilterBar
                    action={visitsIndex.url()}
                    resetHref={visitsIndex.url({ query: { phase } })}
                    activeCount={active}
                    className="xl:grid-cols-6"
                >
                    <input type="hidden" name="phase" value={phase} />
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
                    {can.filter_dsps !== false && (
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
                    )}
                    {can.filter_supervisors && supervisors.length > 0 && (
                        <select
                            name="supervisor_id"
                            defaultValue={filters.supervisor_id}
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
                    )}
                    <select
                        name="service_type"
                        defaultValue={filters.service_type}
                        className={controlClassName}
                        aria-label="Service"
                    >
                        <option value="">All services</option>
                        {service_types.map((service) => (
                            <option key={service} value={service}>
                                {service}
                            </option>
                        ))}
                    </select>
                </FilterBar>

                {visits.data.length === 0 ? (
                    <EmptyState
                        message={
                            active > 0
                                ? 'No visits match these filters.'
                                : phase === 'completed'
                                  ? 'No completed visits in this view yet.'
                                  : 'No upcoming visits in this view.'
                        }
                    />
                ) : (
                    <>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {visits.data.map((visit) => (
                                <VisitHistoryCard key={visit.id} visit={visit} />
                            ))}
                        </div>
                        <Pagination meta={visits.meta} links={visits.links} />
                    </>
                )}
            </RecordPage>
        </>
    );
}

function VisitHistoryCard({ visit }: { visit: VisitRecord }) {
    const completed = visit.status === 'completed';
    const chips = [
        visit.status_label,
        completed && visit.duration_label ? visit.duration_label : null,
        completed && (visit.exception_count ?? 0) > 0
            ? `${visit.exception_count} exception${visit.exception_count === 1 ? '' : 's'}`
            : null,
    ].filter(Boolean);

    return (
        <button
            type="button"
            onClick={() => router.visit(show.url(visit.id))}
            className="surface-panel interactive-surface p-4 text-left"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate font-semibold">
                        {visit.client_name ?? visit.client?.name}
                    </p>
                    <p className="text-muted-foreground mt-0.5 text-sm">
                        {visit.service_type} · {visit.service_date}
                    </p>
                </div>
                <StatusBadge status={visit.status} label={visit.status_label} />
            </div>
            <p className="text-muted-foreground mt-3 text-sm">
                {visit.dsp_name}
                {visit.time_label ? ` · ${visit.time_label}` : ''}
            </p>
            {completed && chips.length > 1 ? (
                <p className="text-muted-foreground mt-2 text-xs">
                    {chips.slice(1).join(' · ')}
                </p>
            ) : null}
        </button>
    );
}

ScheduledVisitsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scheduled Visits', href: visitsIndex() },
    ],
};
