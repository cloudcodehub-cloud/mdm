import { Head, Link, router } from '@inertiajs/react';
import { Pagination, StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as incidentsIndex, show as showIncident } from '@/routes/incidents';
import { index as operationsIndex } from '@/routes/operations';
import type { Paginated } from '@/types/directory';
import type { IncidentListItem } from '@/types/incident';

export default function IncidentsIndex({
    incidents,
    acknowledgment_threshold_minutes,
}: {
    incidents: Paginated<IncidentListItem>;
    acknowledgment_threshold_minutes: number;
}) {
    return (
        <>
            <Head title="Incident register" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            <Link
                                href={operationsIndex()}
                                className="hover:text-foreground"
                            >
                                Operations
                            </Link>
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Incident register
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Formal submitted Incidents on your permitted scope.
                            Acknowledgment threshold: {acknowledgment_threshold_minutes}{' '}
                            minutes.
                        </p>
                    </div>
                    <Button asChild variant="secondary">
                        <Link href={operationsIndex()}>Operations board</Link>
                    </Button>
                </div>

                <Panel title="Incidents">
                    {incidents.data.length === 0 ? (
                        <EmptyState message="No formal Incidents in register." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-muted-foreground text-xs uppercase">
                                    <tr>
                                        <th className="pb-2 font-medium">Incident</th>
                                        <th className="pb-2 font-medium">Client</th>
                                        <th className="pb-2 font-medium">Category</th>
                                        <th className="pb-2 font-medium">Lifecycle</th>
                                        <th className="pb-2 font-medium">Acknowledgment</th>
                                        <th className="pb-2 font-medium">Submitted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {incidents.data.map((incident) => (
                                        <tr
                                            key={incident.id}
                                            className="hover:bg-muted/40 cursor-pointer border-t"
                                            onClick={() =>
                                                router.visit(showIncident.url(incident.id))
                                            }
                                        >
                                            <td className="py-3 font-medium">
                                                {incident.tracking_number ??
                                                    `#${incident.id}`}
                                            </td>
                                            <td className="py-3">
                                                {incident.client.name}
                                            </td>
                                            <td className="py-3">
                                                {incident.original_category}
                                                {incident.original_subcategory
                                                    ? ` / ${incident.original_subcategory}`
                                                    : ''}
                                            </td>
                                            <td className="py-3">
                                                <StatusBadge
                                                    status={incident.status}
                                                    label={incident.status_label}
                                                />
                                            </td>
                                            <td className="py-3">
                                                {incident.overdue_for_acknowledgment ? (
                                                    <StatusBadge
                                                        status="attention"
                                                        label="Overdue"
                                                    />
                                                ) : (
                                                    incident.acknowledgment_condition
                                                )}
                                            </td>
                                            <td className="py-3">
                                                {incident.submitted_at_label}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="mt-4">
                        <Pagination
                            links={incidents.links}
                            meta={incidents.meta}
                        />
                    </div>
                </Panel>
            </div>
        </>
    );
}

IncidentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Incidents', href: incidentsIndex() },
    ],
};
